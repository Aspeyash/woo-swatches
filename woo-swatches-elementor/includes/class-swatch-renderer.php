<?php
/**
 * Core swatch HTML renderer.
 *
 * This class is the heart of Widget 1. It intercepts WooCommerce's
 * dropdown_variation_attribute_options_html filter and replaces the
 * native <select> with visual swatch HTML — while keeping the original
 * <select> hidden in the DOM so wc-add-to-cart-variation.js continues
 * to work without any modification (Emran Ahmed's battle-tested pattern).
 *
 * Flow per attribute on a product page:
 *   1. WC fires woocommerce_dropdown_variation_attribute_options_html
 *   2. render() detects attribute type (global or local)
 *   3. Checks transient cache for this product's swatch data
 *   4. If miss: build_swatch_data() queries term meta + availability
 *   5. Cache the data for future requests
 *   6. Render via locate_template() → include_template()
 *   7. Return: hidden <select> + swatch list markup
 *
 * Gap 2  — woocommerce_dropdown_variation_attribute_options_html filter
 * Gap 4  — locate_template() with child → parent → plugin resolution
 * Gap 38 — Both global (taxonomy) and local (per-product) attributes
 * Gap 39 — Default variation pre-selection on page load
 * Gap 40 — Clear/reset button wired to WC reset_data event
 * Gap 46 — LFI path validation on every template include
 * Gap 55 — find_matching_variation() for image fallback chain
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Swatch_Renderer {

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected static ?WSE_Swatch_Renderer $instance = null;

	/**
	 * Request-scoped cache for get_available_variations() results.
	 * Avoids calling this expensive method more than once per product
	 * when a product has multiple swatch attributes.
	 *
	 * @var array<int, array<int, array<string, mixed>>>
	 */
	private array $variation_cache = array();

	/**
	 * Request-scoped cache for validated safe template paths.
	 *
	 * @var array<string, string>
	 */
	private array $template_path_cache = array();

	// ─────────────────────────────────────────────────────────────────────
	// Singleton
	// ─────────────────────────────────────────────────────────────────────

	public static function instance(): static {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {
		$this->hooks();
	}

	private function __clone() {}

	// ─────────────────────────────────────────────────────────────────────
	// Hooks
	// ─────────────────────────────────────────────────────────────────────

	private function hooks(): void {
		/**
		 * Gap 2 — THE core WC filter.
		 * Priority 20: fires after WC's own processing (priority 10)
		 * but before most third-party plugins.
		 *
		 * Emran Ahmed uses the same hook — this is the confirmed approach.
		 * The original $html (with the <select>) is preserved hidden in DOM.
		 */
		add_filter(
			'woocommerce_dropdown_variation_attribute_options_html',
			array( $this, 'render' ),
			20,
			2
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Main render entry point
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Intercepts the WC dropdown filter and returns swatch HTML.
	 *
	 * If the attribute type is not one of our supported swatch types,
	 * the original $html is returned untouched — WC's native dropdown
	 * is preserved for unsupported types.
	 *
	 * @param  string               $html WooCommerce's native <select> HTML.
	 * @param  array<string, mixed> $args Filter arguments from WC.
	 * @return string               Swatch HTML or original $html if not applicable.
	 */
	public function render( string $html, array $args ): string {

		/** @var \WC_Product_Variable|null $product */
		$product = $args['product'] ?? null;

		if ( ! $product instanceof \WC_Product ) {
			return $html;
		}

		$attribute = (string) ( $args['attribute'] ?? '' );
		if ( empty( $attribute ) ) {
			return $html;
		}

		// ── Developer filter — allow disabling per product or context ──────
		if ( ! (bool) apply_filters( 'wse_is_enabled', true, $attribute, $product ) ) {
			return $html;
		}

		// ── Detect attribute type ─────────────────────────────────────────
		$type = $this->get_type_for_attribute( $attribute, $product );

		// Not our type — leave WC's dropdown untouched
		if ( ! in_array( $type, WSE_Attribute_Types::SUPPORTED_TYPES, true ) ) {
			/**
			 * Fix (v1.0.3, Bug A): wc-add-to-cart-variation.js collects
			 * "chosen attributes" via `.variations select` to build the
			 * variation-matching payload. A bare passthrough <select> here
			 * has no .variations ancestor inside .wse-variations-form, so
			 * this attribute is silently excluded — find_matching_variation()
			 * can then never match any variation that specifies a non-"Any"
			 * value for it, leaving Add to Cart permanently disabled even
			 * after every swatch-type attribute is correctly selected.
			 *
			 * This wrapper is purely structural: same visible <select>,
			 * same classes — just inside an ancestor carrying .variations.
			 */

			/**
			 * v1.1.6 (Bug #5): Respect wse_renderer_emit_select on this
			 * early-exit path too. Widget 1 sets emit_select=false during
			 * its render so the canonical form (Widget 2) is the sole owner
			 * of every attribute's <select> — including non-swatch (select /
			 * text) types. Before this fix, the early-return passthrough
			 * bypassed the filter, so Widget 1 emitted a duplicate <select>
			 * for dropdown-typed attributes, producing two visible dropdowns
			 * with the same name and breaking variation matching until both
			 * were independently selected.
			 */
			$emit_select = (bool) apply_filters(
				'wse_renderer_emit_select',
				true,
				$attribute,
				$product,
				$type
			);
			if ( ! $emit_select ) {
				return '';
			}

			return '<div class="wse-native-attr variations">' . $html . '</div>';
		}

		// ── Selected value (pre-selection — Gap 39) ───────────────────────
		// WC already resolves default attributes + URL params into $args['selected']
		$selected_value = (string) ( $args['selected'] ?? '' );

		// ── Build or retrieve cached swatch data ──────────────────────────
		$product_id   = $product->get_id();
		$all_cached   = WSE_Cache::get( $product_id );
		$all_cached   = is_array( $all_cached ) ? $all_cached : array();

		if ( isset( $all_cached[ $attribute ] ) ) {
			$swatch_data = $all_cached[ $attribute ];
		} else {
			$swatch_data = $this->build_swatch_data( $args, $type, $selected_value );

			// Developer filter — modify full swatch data array
			$swatch_data = (array) apply_filters(
				'wse_swatch_data',
				$swatch_data,
				$attribute,
				$product
			);

			// Store this attribute's data in the product-level cache entry
			$all_cached[ $attribute ] = $swatch_data;
			WSE_Cache::set( $product_id, $all_cached );
		}

		if ( empty( $swatch_data ) ) {
			return $html;
		}

		// ── Build individual swatch item HTML strings ─────────────────────
		// B5 — Track whether any swatch in this group is currently selected
		// so the first available one becomes keyboard-focusable when none is.
		$any_selected = false;
		foreach ( $swatch_data as $value => $swatch ) {
			if ( (string) $value === $selected_value ) {
				$any_selected = true;
				break;
			}
		}

		$items_html              = '';
		$first_available_emitted = false;

		foreach ( $swatch_data as $value => $swatch ) {

			// is_selected applied at render-time so URL params are respected
			$swatch['is_selected'] = ( (string) $value === $selected_value );

			// B5 — First-available focus fallback. When NO swatch in the
			// group is selected (no default attribute, no URL param), the
			// first non-disabled swatch in DOM order becomes the keyboard
			// entry point. swatches.js takes over once focus moves.
			$is_first_focusable = false;
			if ( ! $any_selected
				&& ! $first_available_emitted
				&& ! empty( $swatch['is_available'] ) ) {
				$is_first_focusable      = true;
				$first_available_emitted = true;
			}

			/**
			 * v1.1.6 (Bug #4): Button-type swatches are rendered through
			 * label.php — the label template already supports the button
			 * variant via the .wse-swatch-label--button modifier class
			 * (driven by $swatch['type'] === 'button'). Previous code looked
			 * for templates/swatches/button.php which has never existed in
			 * the plugin, so locate_template() returned empty, the LFI guard
			 * silently failed the include, and button-typed attributes
			 * rendered an empty <ul> on every product page.
			 */
			$template_name = ( 'button' === $type ) ? 'label.php' : ( $type . '.php' );

			$item_html = $this->include_template(
				$template_name,
				array(
					'value'              => (string) $value,
					'swatch'             => $swatch,
					'attribute'          => $attribute,
					'is_selected'        => $swatch['is_selected'],
					'is_first_focusable' => $is_first_focusable, // v1.1.0 (B5)
				)
			);

			// Developer filter — modify per-swatch HTML
			$item_html = (string) apply_filters(
				'wse_swatch_item_html',
				$item_html,
				(string) $value,
				$swatch,
				$attribute
			);

			$items_html .= $item_html;
		}

		// ── Wrap and return ───────────────────────────────────────────────
		do_action( 'wse_before_render_swatches', $attribute, $product );

		/**
		 * v1.1.0 (B3) — Renderer emit-mode filters.
		 *
		 * Two boolean context filters control which structural pieces appear
		 * in the rendered HTML:
		 *
		 *   wse_renderer_emit_select   — when false, the hidden native <select>
		 *                                is omitted. Widget 1 sets this so the
		 *                                canonical form (Widget 2) is the sole
		 *                                owner of variation form fields.
		 *
		 *   wse_renderer_emit_swatches — when false, the visual swatch <ul>
		 *                                is omitted. Widget 2 (canonical mode)
		 *                                sets this so swatch UI is owned by
		 *                                Widget 1 alone, eliminating duplication.
		 *
		 * Both default to true so direct calls (e.g. on classic single-product
		 * pages without either widget) behave exactly as in v1.0.5.
		 */
		$emit_select   = (bool) apply_filters( 'wse_renderer_emit_select',   true, $attribute, $product, $type );
		$emit_swatches = (bool) apply_filters( 'wse_renderer_emit_swatches', true, $attribute, $product, $type );

		// If neither piece is requested, return the original HTML untouched.
		if ( ! $emit_select && ! $emit_swatches ) {
			return $html;
		}

		$output = $this->include_template(
			'wrapper.php',
			array(
				'html'          => $html,        // Gap 2 — hidden but intact in DOM
				'attribute'     => $attribute,
				'product'       => $product,
				'type'          => $type,
				'items_html'    => $items_html,
				'emit_select'   => $emit_select,   // v1.1.0
				'emit_swatches' => $emit_swatches, // v1.1.0
			)
		);

		do_action( 'wse_after_render_swatches', $attribute, $product );

		// Developer filter — modify final swatch block HTML
		return (string) apply_filters( 'wse_swatch_html', $output, $attribute, $product );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Swatch data builders
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Builds the complete swatch data array for all options of an attribute.
	 *
	 * @param  array<string, mixed> $args          WC filter args.
	 * @param  string               $type          Swatch type: color|image|label|button.
	 * @param  string               $selected_value Currently selected option value.
	 * @return array<string, array<string, mixed>>  Swatch data keyed by option value.
	 */
	private function build_swatch_data(
		array $args,
		string $type,
		string $selected_value
	): array {

		$product   = $args['product'];
		$attribute = $args['attribute'];
		$options   = $args['options'] ?? array();

		if ( empty( $options ) ) {
			return array();
		}

		// Determine if this is a global (taxonomy) or local attribute
		$product_attributes = $product->get_attributes();
		$is_taxonomy = isset( $product_attributes[ $attribute ] )
			&& $product_attributes[ $attribute ]->is_taxonomy();

		$swatch_data = array();

		foreach ( $options as $option_value ) {
			$swatch_data[ $option_value ] = $this->build_single_swatch(
				(string) $option_value,
				$attribute,
				$type,
				$product,
				$is_taxonomy
			);
		}

		return $swatch_data;
	}

	/**
	 * Builds swatch data for a single option value.
	 *
	 * @param  string      $option_value Raw option value (term slug or text).
	 * @param  string      $attribute    Attribute name e.g. 'pa_color'.
	 * @param  string      $type         Swatch type.
	 * @param  \WC_Product $product      Product object.
	 * @param  bool        $is_taxonomy  Whether this is a global taxonomy attribute.
	 * @return array<string, mixed>      Single swatch data array.
	 */
	private function build_single_swatch(
		string $option_value,
		string $attribute,
		string $type,
		\WC_Product $product,
		bool $is_taxonomy
	): array {

		// ── Label / term data ─────────────────────────────────────────────
		$term_id = 0;
		$label   = $option_value;

		if ( $is_taxonomy ) {
			$term = get_term_by( 'slug', $option_value, $attribute );
			if ( $term instanceof \WP_Term ) {
				$label   = $term->name;
				$term_id = $term->term_id;
			}
		}

		// ── Core swatch entry ─────────────────────────────────────────────
		$swatch = array(
			'value'        => $option_value,
			'label'        => $label,
			'type'         => $type,
			'is_available' => $this->is_term_available( $product, $attribute, $option_value ),
		);

		// ── Type-specific data ────────────────────────────────────────────
		switch ( $type ) {

			case 'color':
				if ( $is_taxonomy && $term_id ) {
					$swatch['color'] = WSE_Term_Meta::get_color( $term_id );
				} else {
					// Gap 38 — Local attribute color
					$swatch['color'] = WSE_Local_Attributes::get_color(
						$product->get_id(),
						$attribute,
						sanitize_title( $option_value )
					);
				}
				// Fallback to a light grey when no color is configured
				if ( empty( $swatch['color'] ) ) {
					$swatch['color'] = '#e0e0e0';
				}
				break;

			case 'image':
				if ( $is_taxonomy && $term_id ) {
					$swatch['image_id']  = WSE_Term_Meta::get_image_id( $term_id );
					$swatch['image_url'] = $this->get_image_with_fallback(
						$product,
						$attribute,
						$option_value,
						$term_id
					);
				} else {
					// Gap 38 — Local attribute image
					$local_img_id = WSE_Local_Attributes::get_image_id(
						$product->get_id(),
						$attribute,
						sanitize_title( $option_value )
					);
					$swatch['image_id']  = $local_img_id;
					$swatch['image_url'] = $local_img_id
						? (string) ( wp_get_attachment_image_url( $local_img_id, 'wse_swatch' ) ?: wc_placeholder_img_src( 'wse_swatch' ) )
						: $this->get_image_with_fallback( $product, $attribute, $option_value, 0 );
				}
				break;

			// label and button use only value + label — no extra data needed
		}

		return $swatch;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Type detection
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the swatch type for an attribute, handling both global and local.
	 *
	 * @param string      $attribute Attribute name.
	 * @param \WC_Product $product   Product object.
	 * @return string Type string: color|image|label|button|select.
	 */
	private function get_type_for_attribute( string $attribute, \WC_Product $product ): string {

		$product_attributes = $product->get_attributes();

		// Gap 38 — Check if local (non-taxonomy) attribute
		if ( isset( $product_attributes[ $attribute ] )
			 && ! $product_attributes[ $attribute ]->is_taxonomy() ) {
			return WSE_Local_Attributes::get_attribute_type(
				$product->get_id(),
				$attribute
			);
		}

		// Global taxonomy attribute
		return WSE_Attribute_Types::get_attribute_type( $attribute );
	}

	// ─────────────────────────────────────────────────────────────────────
	// OOS (out-of-stock) detection
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns true if a term value has at least one available variation.
	 *
	 * A variation is "available" if WooCommerce's get_available_variations()
	 * returns it (i.e. it's in stock and purchasable).
	 *
	 * Empty-string attribute values in a variation mean "any term matches" —
	 * WC supports this for wildcard variations.
	 *
	 * @param  \WC_Product $product   Product.
	 * @param  string      $attribute Attribute name e.g. 'pa_color'.
	 * @param  string      $value     Term slug or option value.
	 * @return bool
	 */
	private function is_term_available(
		\WC_Product $product,
		string $attribute,
		string $value
	): bool {

		if ( ! $product instanceof \WC_Product_Variable ) {
			return true;
		}

		$variations = $this->get_available_variations( $product );
		$attr_key   = 'attribute_' . sanitize_title( $attribute );

		foreach ( $variations as $variation ) {
			$attr_val = $variation['attributes'][ $attr_key ] ?? null;
			if ( null === $attr_val ) {
				continue;
			}
			// Empty string = "any" — matches all terms
			if ( '' === $attr_val || $attr_val === $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns available variations for a product with request-scoped caching.
	 * Prevents calling get_available_variations() more than once per product
	 * when a product has multiple swatch attributes.
	 *
	 * @param  \WC_Product $product Variable product.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_available_variations( \WC_Product $product ): array {
		$id = $product->get_id();
		if ( ! isset( $this->variation_cache[ $id ] ) ) {
			if ( $product instanceof \WC_Product_Variable ) {
				$this->variation_cache[ $id ] = $product->get_available_variations();
			} else {
				$this->variation_cache[ $id ] = array();
			}
		}
		return $this->variation_cache[ $id ];
	}

	// ─────────────────────────────────────────────────────────────────────
	// Image fallback chain (Gap 55)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the best available image URL for an image-type swatch.
	 *
	 * Priority chain (matches ShopBuilder's confirmed approach):
	 *   1. Term meta image  (wse_image term meta → attachment ID)
	 *   2. Variation image  (find_matching_variation → variation thumbnail)
	 *   3. Parent product featured image
	 *   4. WooCommerce placeholder image
	 *
	 * @param  \WC_Product $product   Variable product.
	 * @param  string      $attribute Attribute name.
	 * @param  string      $value     Term slug/value.
	 * @param  int         $term_id   Term ID (0 for local attributes).
	 * @return string                 Image URL.
	 */
	private function get_image_with_fallback(
		\WC_Product $product,
		string $attribute,
		string $value,
		int $term_id
	): string {

		// ── 1. Term meta image ────────────────────────────────────────────
		if ( $term_id > 0 ) {
			$url = WSE_Term_Meta::get_image_url( $term_id, 'wse_swatch' );
			if ( ! empty( $url ) ) {
				return $url;
			}
		}

		// ── 2. Variation image (Gap 55) ───────────────────────────────────
		$variation = $this->find_matching_variation( $product, $attribute, $value );
		if ( $variation instanceof \WC_Product_Variation ) {
			$img_id = $variation->get_image_id();
			if ( $img_id ) {
				$url = wp_get_attachment_image_url( $img_id, 'wse_swatch' );
				if ( $url ) {
					return $url;
				}
			}
		}

		// ── 3. Parent product featured image ──────────────────────────────
		$parent_img_id = $product->get_image_id();
		if ( $parent_img_id ) {
			$url = wp_get_attachment_image_url( $parent_img_id, 'wse_swatch' );
			if ( $url ) {
				return $url;
			}
		}

		// ── 4. WooCommerce placeholder ────────────────────────────────────
		return wc_placeholder_img_src( 'wse_swatch' );
	}

	/**
	 * Finds the first available variation that matches a given attribute value.
	 *
	 * Used as the 2nd fallback in the image priority chain.
	 * Gap 55 — find_matching_variation() helper.
	 *
	 * @param  \WC_Product $product   Variable product.
	 * @param  string      $attribute Attribute name e.g. 'pa_color'.
	 * @param  string      $value     Term slug/value to match.
	 * @return \WC_Product_Variation|null First matching variation with an image, or null.
	 */
	private function find_matching_variation(
		\WC_Product $product,
		string $attribute,
		string $value
	): ?\WC_Product_Variation {

		if ( ! $product instanceof \WC_Product_Variable ) {
			return null;
		}

		$attr_key  = 'attribute_' . sanitize_title( $attribute );
		$variations = $this->get_available_variations( $product );

		foreach ( $variations as $variation_data ) {
			$attr_val = $variation_data['attributes'][ $attr_key ] ?? null;

			// Match exact value or "any" (empty string)
			if ( null === $attr_val || ( '' !== $attr_val && $attr_val !== $value ) ) {
				continue;
			}

			$variation = wc_get_product( $variation_data['variation_id'] );
			if ( $variation instanceof \WC_Product_Variation && $variation->get_image_id() ) {
				return $variation;
			}
		}

		return null;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Template system — Gap 4 + Gap 46
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Resolves the path to a swatch template file.
	 *
	 * Resolution order (Gap 4):
	 *   1. Child theme:   {child-theme}/woo-swatches-elementor/swatches/{template}
	 *   2. Parent theme:  {parent-theme}/woo-swatches-elementor/swatches/{template}
	 *   3. Plugin:        {plugin}/templates/swatches/{template}
	 *
	 * @param  string $template_name Template file name e.g. 'color.php'.
	 * @return string                Absolute path, or empty string if not found.
	 */
	public function locate_template( string $template_name ): string {

		// Sanitise template name — only allow filename chars, no directory traversal
		$template_name = basename( $template_name );
		if ( empty( $template_name ) || ! str_ends_with( $template_name, '.php' ) ) {
			return '';
		}

		// Request-scoped cache
		if ( isset( $this->template_path_cache[ $template_name ] ) ) {
			return $this->template_path_cache[ $template_name ];
		}

		$sub_dir   = 'woo-swatches-elementor/swatches/';
		$locations = array(
			get_stylesheet_directory() . '/' . $sub_dir . $template_name,
			get_template_directory()   . '/' . $sub_dir . $template_name,
			WSE_PATH . 'templates/swatches/' . $template_name,
		);

		$found = '';
		foreach ( $locations as $path ) {
			if ( file_exists( $path ) ) {
				$found = $path;
				break;
			}
		}

		$this->template_path_cache[ $template_name ] = $found;
		return $found;
	}

	/**
	 * Locates, validates, and includes a swatch template, returning its output.
	 *
	 * Gap 46 — LFI (Local File Inclusion) path validation.
	 * Before including ANY file, we verify that the resolved path sits within
	 * one of our allowed directories. This prevents template name manipulation
	 * from including arbitrary files on the server (a real vulnerability that
	 * ShopBuilder's changelog records fixing).
	 *
	 * @param  string               $template_name Template file name e.g. 'color.php'.
	 * @param  array<string, mixed> $args          Variables to extract into template scope.
	 * @return string                              Rendered template HTML or empty string.
	 */
	public function include_template( string $template_name, array $args = array() ): string {

		$path = $this->locate_template( $template_name );

		if ( empty( $path ) ) {
			return '';
		}

		// Gap 46 — Validate the resolved path before including
		if ( ! $this->is_safe_template_path( $path ) ) {
			return '';
		}

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $args, EXTR_SKIP );
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * Validates that a template path sits within an allowed directory.
	 *
	 * Prevents Local File Inclusion (LFI) attacks where an attacker could
	 * manipulate template names to include arbitrary server-side files.
	 * Gap 46.
	 *
	 * @param  string $path Absolute path to validate.
	 * @return bool         True if path is within an allowed directory.
	 */
	private function is_safe_template_path( string $path ): bool {

		$real_path = realpath( $path );
		if ( false === $real_path ) {
			return false;
		}

		$allowed = array(
			realpath( WSE_PATH . 'templates' ),
			realpath( get_stylesheet_directory() . '/woo-swatches-elementor' ),
			realpath( get_template_directory()   . '/woo-swatches-elementor' ),
		);

		foreach ( $allowed as $dir ) {
			if ( $dir && str_starts_with( $real_path, $dir . DIRECTORY_SEPARATOR ) ) {
				return true;
			}
		}

		return false;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Public helpers — used by Elementor widgets (Phase 10, Phase 12)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Renders all swatches for a product attribute.
	 * Public wrapper for Elementor widget render() methods.
	 *
	 * @param  \WC_Product $product   The product.
	 * @param  string      $attribute Attribute name.
	 * @param  array       $options   Option values (term slugs or text values).
	 * @param  string      $selected  Currently selected value.
	 * @return string                 Swatch HTML.
	 */
	public function render_for_widget(
		\WC_Product $product,
		string $attribute,
		array $options,
		string $selected = ''
	): string {

		// Build the args array WC would pass to the filter
		$args = array(
			'options'   => $options,
			'attribute' => $attribute,
			'product'   => $product,
			'selected'  => $selected,
			'name'      => 'attribute_' . sanitize_title( $attribute ),
			'id'        => 'attribute_' . sanitize_title( $attribute ),
			'class'     => '',
			'show_option_none' => __( 'Choose an option', 'woo-swatches-elementor' ),
		);

		// Generate the original select HTML WC would produce
		ob_start();
		wc_dropdown_variation_attribute_options( $args );
		$original_html = (string) ob_get_clean();

		// Now pass through our filter (which we also hook into via add_filter)
		// Since we call render() directly here, bypass the filter chain
		$type = $this->get_type_for_attribute( $attribute, $product );
		if ( ! in_array( $type, WSE_Attribute_Types::SUPPORTED_TYPES, true ) ) {
			return $original_html;
		}

		return $this->render( $original_html, $args );
	}
}
