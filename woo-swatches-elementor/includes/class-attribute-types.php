<?php
/**
 * WooCommerce attribute type registration and detection.
 *
 * Registers Color, Image, Label, and Button as native WooCommerce
 * attribute types so they appear in the WC attribute type dropdown at
 * Products → Attributes → Add/Edit Attribute.
 *
 * Also provides the central get_attribute_type() static method used by
 * every other class that needs to know what kind of swatch to render.
 *
 * Gap 5  — product_attributes_type_selector filter
 * Gap 7  — wp_woocommerce_attribute_taxonomies DB read
 * Gap 42 — $wpdb->prepare() on all direct DB queries
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Attribute_Types {

	// ─────────────────────────────────────────────────────────────────────
	// Constants
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * All swatch types this plugin supports.
	 * Used by the renderer to decide whether to replace a WC <select> or
	 * leave it untouched.
	 */
	const SUPPORTED_TYPES = array( 'color', 'image', 'label', 'button' );

	/**
	 * Fallback type when an attribute has no type set or is unrecognised.
	 * 'select' means: leave WooCommerce's native dropdown in place.
	 */
	const FALLBACK_TYPE = 'select';

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected static ?WSE_Attribute_Types $instance = null;

	/**
	 * In-memory cache for attribute type lookups.
	 * Keyed by sanitised attribute name (without pa_ prefix).
	 * Lives for the duration of the request — avoids repeated DB queries
	 * when multiple swatches render on the same page.
	 *
	 * @var array<string, string>
	 */
	private array $type_cache = array();

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
		 * Gap 5 — Register our custom types in WooCommerce's attribute
		 * type selector.
		 *
		 * Without this, the Products → Attributes screen only shows
		 * "Select" and "Text" in the Type dropdown. This adds:
		 *   Color | Image | Label | Button
		 *
		 * @see woocommerce/includes/admin/meta-boxes/class-wc-meta-box-product-data.php
		 */
		add_filter(
			'product_attributes_type_selector',
			array( $this, 'register_types' )
		);

		/**
		 * v1.4.12 — Render WC's standard term-selection UI for our custom
		 * swatch types on the product editor's Attributes panel.
		 *
		 * WC's html-product-attribute.php view only renders the term
		 * multi-select + Select all / Select none / Create value buttons
		 * for the built-in 'select' type. For any other type it falls
		 * through to do_action( 'woocommerce_product_option_terms', ... )
		 * expecting a third-party plugin to render its own UI.
		 *
		 * Pre-v1.4.12 this plugin registered Color/Image/Label/Button as
		 * custom types but never hooked into woocommerce_product_option_
		 * terms — so on every product that used one of those attribute
		 * types the Value(s) section was rendered EMPTY: no select2
		 * dropdown, no "Select all"/"Select none"/"Create value" buttons,
		 * and the merchant could not pick existing terms or add new ones
		 * to the product. The only workaround was to deactivate the
		 * plugin, set the attribute type back to "Select", edit the
		 * product, then re-activate.
		 *
		 * @see render_term_selector_for_custom_types() for the rendering.
		 */
		add_action(
			'woocommerce_product_option_terms',
			array( $this, 'render_term_selector_for_custom_types' ),
			10,
			3
		);

		/**
		 * Clear the in-memory type cache when a WC attribute taxonomy is
		 * saved — so the next request reads fresh data from the DB.
		 * This covers both "Add Attribute" and "Edit Attribute" in admin.
		 */
		add_action( 'woocommerce_attribute_added',   array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_attribute_updated', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_attribute_deleted', array( $this, 'clear_cache' ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 5 — Register types
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Appends our swatch types to WooCommerce's built-in type list.
	 *
	 * @param array<string, string> $types Existing type key → label pairs.
	 * @return array<string, string>
	 */
	public function register_types( array $types ): array {
		$types['color']  = esc_html__( 'Color',  'woo-swatches-elementor' );
		$types['image']  = esc_html__( 'Image',  'woo-swatches-elementor' );
		$types['label']  = esc_html__( 'Label',  'woo-swatches-elementor' );
		$types['button'] = esc_html__( 'Button', 'woo-swatches-elementor' );
		return $types;
	}

	// ─────────────────────────────────────────────────────────────────────
	// v1.4.12 — Product-editor term selector for custom swatch types
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Renders the Value(s) UI inside WC's product-editor Attributes panel
	 * when the attribute's stored type is one of our custom swatch types
	 * (color/image/label/button).
	 *
	 * The output reproduces WC's standard `select` UI from
	 * woocommerce/includes/admin/meta-boxes/views/html-product-attribute.php
	 * byte-for-byte so WC's own admin JS continues to work unchanged:
	 *
	 *   - The <select class="multiselect attribute_values wc-taxonomy-term-search">
	 *     element is the exact selector that meta-boxes-product.js initializes
	 *     with Select2 on tab open, and that WC's AJAX term-search uses to
	 *     query candidate terms.
	 *   - data-taxonomy / data-return_id / data-placeholder / data-minimum_input_length
	 *     / data-limit are the exact attribute names WC's term-search handler reads.
	 *   - The form field name attribute_values[$i][] is the exact name WC's
	 *     product-save logic reads to persist selected term IDs.
	 *   - The .select_all_attributes / .select_no_attributes / .add_new_attribute
	 *     button classes are the exact selectors WC's click handlers bind to.
	 *
	 * Because every selector and form field name matches WC's defaults, all of
	 * the following continue to work without any additional plugin JS:
	 *   - Select2 term picker with AJAX search
	 *   - "Select all" / "Select none" buttons
	 *   - "Create value" inline-create button (WC's AJAX endpoint creates the
	 *     new term, returns the ID, prepends a selected <option> to this <select>)
	 *   - Saving the product, which writes the picked term IDs to the WC product
	 *     attribute store
	 *
	 * Only fires for global (taxonomy) attributes whose stored attribute_type
	 * is in SUPPORTED_TYPES. Local (per-product) attributes never hit this
	 * code path — they're handled by WC's textarea fallback inside the same
	 * html-product-attribute.php view.
	 *
	 * @param object|null            $attribute_taxonomy The taxonomy row from
	 *                                                   wp_woocommerce_attribute_taxonomies
	 *                                                   (carries attribute_name + attribute_type).
	 * @param int                    $i                  Loop index of this attribute in
	 *                                                   the product, used in field names.
	 * @param \WC_Product_Attribute  $attribute          The product attribute object.
	 * @return void
	 */
	public function render_term_selector_for_custom_types( $attribute_taxonomy, $i, $attribute ): void {

		// ── A4 (v1.5.0) — Local-attribute safety, verified + hardened ──────
		//
		// WooCommerce fires `woocommerce_product_option_terms` ONLY inside
		// the `$attribute->is_taxonomy()` branch of its admin view
		// (includes/admin/meta-boxes/views/html-product-attribute.php).
		// For LOCAL (per-product, non-taxonomy) attributes WC renders a
		// plain <textarea> for pipe-separated values and never fires this
		// action — so this method can never legitimately run for a local
		// attribute. The three guards below make that contract explicit and
		// defend against any third-party / future-WC code that might fire
		// the action with an unexpected payload:
		//
		//   1. is_taxonomy() — if the product attribute is local, bail. This
		//      is the primary hardening guard: even if the action were fired
		//      for a local attribute, we must NOT emit a taxonomy term-select
		//      (it would collide with WC's textarea and corrupt the save).
		//   2. empty( $attribute_taxonomy ) — local attrs have no taxonomy row.
		//   3. attribute_type in SUPPORTED_TYPES — only our swatch types.

		// Guard 1 — must be a global (taxonomy) attribute.
		if ( is_object( $attribute )
			&& method_exists( $attribute, 'is_taxonomy' )
			&& ! $attribute->is_taxonomy() ) {
			return;
		}

		// Guard 2 — only global (taxonomy) attributes have a taxonomy row.
		if ( empty( $attribute_taxonomy ) || ! isset( $attribute_taxonomy->attribute_type ) ) {
			return;
		}

		// Guard 3 — only for our custom swatch types. WC's built-in `select`
		// type renders its UI directly inside html-product-attribute.php
		// before this action fires, so we never run for it. `text` falls
		// through to a textarea inside WC's view too.
		if ( ! in_array( $attribute_taxonomy->attribute_type, self::SUPPORTED_TYPES, true ) ) {
			return;
		}

		// The taxonomy slug WC's JS expects, e.g. "pa_color".
		$taxonomy_name = is_object( $attribute ) && method_exists( $attribute, 'get_name' )
			? $attribute->get_name()
			: 'pa_' . $attribute_taxonomy->attribute_name;

		// Currently-selected terms on this product, if any.
		$selected_terms = array();
		if ( is_object( $attribute ) && method_exists( $attribute, 'get_terms' ) ) {
			$terms = $attribute->get_terms();
			if ( is_array( $terms ) ) {
				$selected_terms = $terms;
			}
		}

		// Output: byte-for-byte mirror of WC's html-product-attribute.php
		// select-type block. The `woocommerce` text domain is intentional
		// so WC's own translations apply.
		?>
		<select multiple="multiple"
			data-minimum_input_length="0"
			data-limit="50"
			data-return_id="id"
			data-placeholder="<?php esc_attr_e( 'Select terms', 'woocommerce' ); ?>"
			class="multiselect attribute_values wc-taxonomy-term-search"
			name="attribute_values[<?php echo esc_attr( (string) $i ); ?>][]"
			data-taxonomy="<?php echo esc_attr( $taxonomy_name ); ?>">
			<?php foreach ( $selected_terms as $term ) : ?>
				<?php if ( ! is_object( $term ) || ! isset( $term->term_id, $term->name ) ) { continue; } ?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" selected="selected">
					<?php echo esc_html(
						apply_filters( 'woocommerce_product_attribute_term_name', $term->name, $term )
					); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button class="button plus select_all_attributes"><?php esc_html_e( 'Select all', 'woocommerce' ); ?></button>
		<button class="button minus select_no_attributes"><?php esc_html_e( 'Select none', 'woocommerce' ); ?></button>
		<button class="button fr plus add_new_attribute"><?php esc_html_e( 'Create value', 'woocommerce' ); ?></button>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 7 + Gap 42 — Attribute type detection
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the swatch type for a given attribute.
	 *
	 * Lookup priority:
	 *   1. In-memory cache (request-scoped, no DB hit)
	 *   2. wp_woocommerce_attribute_taxonomies DB table (global attributes)
	 *   3. 'wse_attribute_type' developer filter (allows runtime override)
	 *   4. FALLBACK_TYPE ('select') if nothing is found
	 *
	 * For local (per-product) attributes the caller should pass the type
	 * directly — this method only handles global WC taxonomies.
	 *
	 * @param string $attribute_name Raw attribute name, e.g. 'pa_color' or 'color'.
	 * @return string One of: 'color' | 'image' | 'label' | 'button' | 'select' | 'text'
	 */
	public static function get_attribute_type( string $attribute_name ): string {
		$self = static::instance();

		// Normalise: strip 'pa_' prefix, sanitise
		$name = sanitize_key( str_replace( 'pa_', '', $attribute_name ) );

		if ( empty( $name ) ) {
			return self::FALLBACK_TYPE;
		}

		// ── 1. In-memory cache ────────────────────────────────────────────
		if ( isset( $self->type_cache[ $name ] ) ) {
			return $self->type_cache[ $name ];
		}

		// ── 2. DB lookup — Gap 7 + Gap 42 ────────────────────────────────
		global $wpdb;

		// Gap 42 — always use $wpdb->prepare() for raw queries
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$db_type = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT attribute_type
				 FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
				 WHERE attribute_name = %s
				 LIMIT 1",
				$name
			)
		);

		// Treat empty string and null as fallback
		$type = ! empty( $db_type ) ? $db_type : self::FALLBACK_TYPE;

		// ── 3. Developer filter — allows runtime override ─────────────────
		// Usage: add_filter('wse_attribute_type', function($type, $attr) {
		//            return ($attr === 'pa_finish') ? 'color' : $type;
		//        }, 10, 2);
		$type = (string) apply_filters( 'wse_attribute_type', $type, $attribute_name );

		// ── 4. Cache and return ───────────────────────────────────────────
		$self->type_cache[ $name ] = $type;

		return $type;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Helper methods
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns true if the attribute should render as a swatch
	 * (i.e. its type is one of our supported types, not 'select' or 'text').
	 *
	 * Used by WSE_Swatch_Renderer to decide whether to intercept the
	 * woocommerce_dropdown_variation_attribute_options_html filter output.
	 *
	 * @param string $attribute_name Raw attribute name, e.g. 'pa_color'.
	 * @return bool
	 */
	public static function is_swatch_type( string $attribute_name ): bool {
		return in_array(
			static::get_attribute_type( $attribute_name ),
			self::SUPPORTED_TYPES,
			true
		);
	}

	/**
	 * Returns true for a specific type match.
	 *
	 * @param string $attribute_name Raw attribute name.
	 * @param string $type           Type to test against: 'color'|'image'|'label'|'button'.
	 * @return bool
	 */
	public static function is_type( string $attribute_name, string $type ): bool {
		return static::get_attribute_type( $attribute_name ) === $type;
	}

	/**
	 * Returns all supported type keys.
	 *
	 * @return string[]
	 */
	public static function get_supported_types(): array {
		return self::SUPPORTED_TYPES;
	}

	/**
	 * Returns all supported types as a key → label array
	 * suitable for Elementor select controls or admin dropdowns.
	 *
	 * @return array<string, string>
	 */
	public static function get_types_for_select(): array {
		return array(
			'color'  => esc_html__( 'Color',    'woo-swatches-elementor' ),
			'image'  => esc_html__( 'Image',    'woo-swatches-elementor' ),
			'label'  => esc_html__( 'Label',    'woo-swatches-elementor' ),
			'button' => esc_html__( 'Button',   'woo-swatches-elementor' ),
			'select' => esc_html__( 'Dropdown', 'woo-swatches-elementor' ),
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Cache management
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Clears the in-memory type cache.
	 * Called when any WC attribute taxonomy is added, updated, or deleted.
	 * Also available as a public method for use by WSE_Cache::flush_all().
	 */
	public function clear_cache(): void {
		$this->type_cache = array();
	}

	/**
	 * Removes a single attribute from the in-memory cache by name.
	 *
	 * @param string $attribute_name Raw attribute name, e.g. 'pa_color'.
	 */
	public function clear_cache_for( string $attribute_name ): void {
		$name = sanitize_key( str_replace( 'pa_', '', $attribute_name ) );
		unset( $this->type_cache[ $name ] );
	}
}
