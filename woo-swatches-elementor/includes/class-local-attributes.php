<?php
/**
 * Per-product swatch configuration for local (non-taxonomy) attributes.
 *
 * WooCommerce has two attribute types:
 *   Global  — stored as taxonomies (pa_color, pa_size). Term meta handled in class-term-meta.php.
 *   Local   — created per-product, stored in _product_attributes post meta.
 *             These have NO taxonomy, so wp_termmeta cannot store their swatch data.
 *             This class provides a separate UI and storage for those.
 *
 * Storage key : _wse_local_swatches  (post meta on the product)
 * Storage format:
 *   [
 *     'attribute-slug' => [
 *       '_type'      => 'color',          // swatch type for this attribute
 *       'option-one' => '#ff0000',         // color hex value
 *       'option-two' => 42,               // attachment ID (image type)
 *     ],
 *   ]
 *
 * Gap 38 — Local/custom per-product attributes (non-taxonomy)
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Local_Attributes {

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected static ?WSE_Local_Attributes $instance = null;

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

		if ( ! is_admin() ) {
			return;
		}

		// Add metabox to WooCommerce product edit screen
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );

		// Gap 23 — Screen-gated asset enqueue (product edit page only)
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

		// Save swatch data when product is saved
		add_action(
			'woocommerce_process_product_meta',
			array( $this, 'save_local_swatches' ),
			10,
			1
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Metabox registration
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Registers the Local Attribute Swatches metabox on the product edit page.
	 */
	public function add_metabox(): void {
		add_meta_box(
			'wse-local-swatches',
			esc_html__( 'WooSwatches — Local Attribute Swatches', 'woo-swatches-elementor' ),
			array( $this, 'render_metabox' ),
			'product',
			'normal',
			'default'
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 23 — Screen-gated admin assets
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Enqueues admin assets only on the WooCommerce product edit screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function admin_enqueue( string $hook ): void {

		// Only on product add/edit pages
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		global $post;
		if ( ! $post || get_post_type( $post->ID ) !== 'product' ) {
			return;
		}

		// wp-color-picker and media uploader
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();

		// Gap 49 — WSE_SUFFIX: .min in production, '' in debug
		wp_enqueue_script(
			'wse-admin-local-attributes',
			WSE_URL . 'assets/js/admin-local-attributes' . WSE_SUFFIX . '.js',
			array( 'jquery', 'wp-color-picker' ),
			WSE_VERSION,
			true
		);

		wp_localize_script(
			'wse-admin-local-attributes',
			'WSELocalAttr',
			array(
				'choose_image' => esc_html__( 'Choose Swatch Image',  'woo-swatches-elementor' ),
				'use_image'    => esc_html__( 'Use this image',        'woo-swatches-elementor' ),
				'remove'       => esc_html__( 'Remove',                'woo-swatches-elementor' ),
				'placeholder'  => esc_url( wc_placeholder_img_src( 'wse_swatch' ) ),
			)
		);

		// Shared admin CSS already covers the field styles
		wp_enqueue_style(
			'wse-admin',
			WSE_URL . 'assets/css/admin.css',
			array( 'wp-color-picker' ),
			WSE_VERSION
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Metabox render
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Renders the local attribute swatches metabox on the product edit screen.
	 *
	 * @param \WP_Post $post The current post object.
	 */
	public function render_metabox( \WP_Post $post ): void {

		$product = wc_get_product( $post->ID );

		if ( ! $product ) {
			return;
		}

		// Gap 13 — Nonce field for save verification
		wp_nonce_field( 'wse_local_swatches_nonce', 'wse_local_swatches_nonce' );

		$attributes    = $product->get_attributes();
		$saved_swatches = $this->get_all_local_swatches( $post->ID );

		// Collect only non-taxonomy (local) attributes
		$local_attrs = array_filter(
			$attributes,
			static fn( $attr ) => ! $attr->is_taxonomy()
		);

		if ( empty( $local_attrs ) ) {
			echo wp_kses_post(
				'<p class="wse-local-no-attrs description">' .
				esc_html__(
					'No custom attributes found on this product. Add custom (non-taxonomy) attributes in the Attributes tab, save the product, then return here to configure their swatches.',
					'woo-swatches-elementor'
				) .
				'</p>'
			);
			return;
		}

		echo '<div class="wse-local-swatches-wrap">';
		echo '<p class="description">' .
			esc_html__(
				'Configure swatch display for each custom attribute below. Global attributes (like pa_color) are configured via Products → Attributes → Configure Terms.',
				'woo-swatches-elementor'
			) .
			'</p>';

		foreach ( $local_attrs as $attr_slug => $attr ) {
			$this->render_attribute_section(
				$attr_slug,
				$attr,
				$saved_swatches[ $attr_slug ] ?? array()
			);
		}

		echo '</div>'; // .wse-local-swatches-wrap
	}

	/**
	 * Renders the swatch configuration section for a single local attribute.
	 *
	 * @param string                 $attr_slug    Attribute slug key.
	 * @param \WC_Product_Attribute  $attr         WooCommerce attribute object.
	 * @param array<string, mixed>   $saved         Saved swatch data for this attribute.
	 */
	private function render_attribute_section(
		string $attr_slug,
		\WC_Product_Attribute $attr,
		array $saved
	): void {

		// Display name: "fabric-color" → "Fabric Color"
		$display_name = ucwords( str_replace( array( '-', '_' ), ' ', $attr_slug ) );

		// Saved type, defaulting to 'select' (leave as dropdown)
		$saved_type = sanitize_key( $saved['_type'] ?? 'select' );

		// Option values
		$options = $attr->get_options();

		if ( empty( $options ) ) {
			return;
		}

		$field_name_base = 'wse_local_swatches[' . esc_attr( $attr_slug ) . ']';

		// Available swatch types for the dropdown
		$types = WSE_Attribute_Types::get_types_for_select();

		echo '<div class="wse-local-attr-section" data-attr-slug="' . esc_attr( $attr_slug ) . '">';

		// Section header
		echo '<h4 class="wse-local-attr-title">' . esc_html( $display_name ) . '</h4>';

		// ── Type selector ─────────────────────────────────────────────────
		echo '<div class="wse-local-attr-type-row">';
		echo '<label for="wse_local_type_' . esc_attr( $attr_slug ) . '">';
		echo esc_html__( 'Swatch Type:', 'woo-swatches-elementor' );
		echo '</label>';
		echo '<select id="wse_local_type_' . esc_attr( $attr_slug ) . '"
		              name="' . esc_attr( $field_name_base ) . '[_type]"
		              class="wse-local-type-select">';

		foreach ( $types as $type_key => $type_label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $type_key ),
				selected( $saved_type, $type_key, false ),
				esc_html( $type_label )
			);
		}

		echo '</select>';
		echo '</div>'; // .wse-local-attr-type-row

		// ── Color fields ──────────────────────────────────────────────────
		$color_hidden = ( 'color' !== $saved_type ) ? ' wse-hidden' : '';
		echo '<div class="wse-local-color-fields' . esc_attr( $color_hidden ) . '" data-for-type="color">';
		echo '<p class="wse-local-fields-label description">' .
			esc_html__( 'Pick a color for each option:', 'woo-swatches-elementor' ) .
			'</p>';
		echo '<div class="wse-local-terms-grid">';

		foreach ( $options as $option ) {
			$option_slug  = sanitize_title( $option );
			$saved_color  = sanitize_hex_color( $saved[ $option_slug ] ?? '' );
			$field_name   = $field_name_base . '[' . esc_attr( $option_slug ) . '][color]';

			echo '<div class="wse-local-term-row">';
			echo '<label>' . esc_html( $option ) . '</label>';
			echo '<input type="text"
			             name="' . esc_attr( $field_name ) . '"
			             value="' . esc_attr( $saved_color ) . '"
			             class="wse-color-picker wse-local-color-picker"
			             data-default-color="#ffffff"
			             maxlength="7"/>';
			echo '</div>';
		}

		echo '</div>'; // .wse-local-terms-grid
		echo '</div>'; // .wse-local-color-fields

		// ── Image fields ──────────────────────────────────────────────────
		$image_hidden = ( 'image' !== $saved_type ) ? ' wse-hidden' : '';
		echo '<div class="wse-local-image-fields' . esc_attr( $image_hidden ) . '" data-for-type="image">';
		echo '<p class="wse-local-fields-label description">' .
			esc_html__( 'Assign an image for each option:', 'woo-swatches-elementor' ) .
			'</p>';
		echo '<div class="wse-local-terms-grid">';

		foreach ( $options as $option ) {
			$option_slug    = sanitize_title( $option );
			$saved_image_id = absint( $saved[ $option_slug . '_img' ] ?? 0 );
			$image_url      = $saved_image_id
				? wp_get_attachment_image_url( $saved_image_id, 'wse_swatch' )
				: '';
			$has_image      = ! empty( $image_url );
			$field_name_img = $field_name_base . '[' . esc_attr( $option_slug ) . '][image]';
			$field_id       = 'wse_local_img_' . esc_attr( $attr_slug ) . '_' . esc_attr( $option_slug );

			echo '<div class="wse-local-term-row wse-local-image-row" data-field-id="' . esc_attr( $field_id ) . '">';
			echo '<label>' . esc_html( $option ) . '</label>';
			echo '<div class="wse-image-upload-wrap">';
			echo '<img id="' . esc_attr( $field_id ) . '_preview"
			          src="' . ( $has_image ? esc_url( $image_url ) : esc_url( wc_placeholder_img_src( 'wse_swatch' ) ) ) . '"
			          class="wse-image-preview' . ( $has_image ? '' : ' hidden' ) . '"
			          alt="' . esc_attr( $option ) . '"/>';
			echo '<input type="hidden"
			             id="' . esc_attr( $field_id ) . '"
			             name="' . esc_attr( $field_name_img ) . '"
			             value="' . esc_attr( (string) ( $saved_image_id ?: '' ) ) . '"/>';
			echo '<button type="button"
			              class="button wse-upload-image-btn wse-local-upload-btn"
			              data-field-id="' . esc_attr( $field_id ) . '">' .
				esc_html__( 'Upload', 'woo-swatches-elementor' ) .
				'</button>';
			echo '<button type="button"
			              class="button-link wse-remove-image-btn wse-local-remove-btn' . ( $has_image ? '' : ' hidden' ) . '"
			              data-field-id="' . esc_attr( $field_id ) . '">' .
				esc_html__( 'Remove', 'woo-swatches-elementor' ) .
				'</button>';
			echo '</div>'; // .wse-image-upload-wrap
			echo '</div>'; // .wse-local-term-row
		}

		echo '</div>'; // .wse-local-terms-grid
		echo '</div>'; // .wse-local-image-fields

		echo '</div>'; // .wse-local-attr-section
	}

	// ─────────────────────────────────────────────────────────────────────
	// Save
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Saves local attribute swatch data when the product is saved.
	 *
	 * @param int $product_id Post ID of the product being saved.
	 */
	public function save_local_swatches( int $product_id ): void {

		// Gap 13 — Nonce verification
		if ( ! isset( $_POST['wse_local_swatches_nonce'] ) ||
			 ! wp_verify_nonce(
				 sanitize_key( wp_unslash( $_POST['wse_local_swatches_nonce'] ) ),
				 'wse_local_swatches_nonce'
			 )
		) {
			return;
		}

		// Gap 13 — Capability check
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		// If no swatch data posted, remove saved meta and return
		if ( ! isset( $_POST['wse_local_swatches'] ) ) {
			delete_post_meta( $product_id, '_wse_local_swatches' );
			return;
		}

		// Gap 13 — Sanitise every value before saving
		$raw     = (array) wp_unslash( $_POST['wse_local_swatches'] );
		$cleaned = array();

		foreach ( $raw as $attr_slug => $attr_data ) {

			$attr_slug = sanitize_key( $attr_slug );
			if ( empty( $attr_slug ) || ! is_array( $attr_data ) ) {
				continue;
			}

			// Sanitise attribute-level type
			$type = sanitize_key( $attr_data['_type'] ?? 'select' );
			if ( ! in_array( $type, array_merge( WSE_Attribute_Types::SUPPORTED_TYPES, array( 'select' ) ), true ) ) {
				$type = 'select';
			}

			$cleaned[ $attr_slug ] = array( '_type' => $type );

			// Process per-option swatch values
			foreach ( $attr_data as $option_slug => $option_data ) {

				if ( '_type' === $option_slug ) {
					continue;
				}

				$option_slug = sanitize_key( $option_slug );
				if ( ! is_array( $option_data ) ) {
					continue;
				}

				// Color value
				if ( 'color' === $type && isset( $option_data['color'] ) ) {
					$color = sanitize_hex_color( $option_data['color'] );
					if ( $color ) {
						$cleaned[ $attr_slug ][ $option_slug ] = $color;
					}
				}

				// Image value
				if ( 'image' === $type && isset( $option_data['image'] ) ) {
					$image_id = absint( $option_data['image'] );
					if ( $image_id > 0 ) {
						$cleaned[ $attr_slug ][ $option_slug . '_img' ] = $image_id;
					}
				}
			}
		}

		if ( ! empty( $cleaned ) ) {
			update_post_meta( $product_id, '_wse_local_swatches', $cleaned );
		} else {
			delete_post_meta( $product_id, '_wse_local_swatches' );
		}

		// Gap 25 — Invalidate swatch cache (available from Phase 5)
		if ( class_exists( 'WSE_Cache' ) ) {
			WSE_Cache::clear( $product_id );
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// Public data accessors — used by WSE_Swatch_Renderer (Phase 6)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns all saved local swatch data for a product.
	 *
	 * @param int $product_id Product post ID.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_all_local_swatches( int $product_id ): array {
		$data = get_post_meta( $product_id, '_wse_local_swatches', true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Returns swatch data for a specific local attribute on a product.
	 *
	 * @param int    $product_id  Product post ID.
	 * @param string $attr_slug   Attribute slug e.g. 'fabric-color'.
	 * @return array<string, mixed> Swatch data or empty array.
	 */
	public static function get_attribute_swatches( int $product_id, string $attr_slug ): array {
		$all = self::get_all_local_swatches( $product_id );
		return $all[ sanitize_key( $attr_slug ) ] ?? array();
	}

	/**
	 * Returns the swatch type for a local attribute on a product.
	 *
	 * @param int    $product_id Product post ID.
	 * @param string $attr_slug  Attribute slug.
	 * @return string Type string: 'color'|'image'|'label'|'button'|'select'.
	 */
	public static function get_attribute_type( int $product_id, string $attr_slug ): string {
		$data = self::get_attribute_swatches( $product_id, $attr_slug );
		return sanitize_key( $data['_type'] ?? 'select' );
	}

	/**
	 * Returns the color hex for a specific option in a local attribute.
	 *
	 * @param int    $product_id  Product post ID.
	 * @param string $attr_slug   Attribute slug.
	 * @param string $option_slug Option slug (sanitize_title of option value).
	 * @return string Hex color e.g. '#ff0000' or ''.
	 */
	public static function get_color(
		int $product_id,
		string $attr_slug,
		string $option_slug
	): string {
		$data = self::get_attribute_swatches( $product_id, $attr_slug );
		return (string) ( $data[ sanitize_key( $option_slug ) ] ?? '' );
	}

	/**
	 * Returns the image attachment ID for a specific option in a local attribute.
	 *
	 * @param int    $product_id  Product post ID.
	 * @param string $attr_slug   Attribute slug.
	 * @param string $option_slug Option slug (sanitize_title of option value).
	 * @return int Attachment ID or 0.
	 */
	public static function get_image_id(
		int $product_id,
		string $attr_slug,
		string $option_slug
	): int {
		$data = self::get_attribute_swatches( $product_id, $attr_slug );
		return absint( $data[ sanitize_key( $option_slug ) . '_img' ] ?? 0 );
	}
}
