<?php
/**
 * Admin term meta fields for global WooCommerce attribute terms.
 *
 * Adds a color picker or image uploader to each attribute term edit screen
 * (Products → Attributes → Configure Terms → Add / Edit Term).
 *
 * Storage:
 *   wse_color  → sanitized hex color string  e.g. '#ff0000'
 *   wse_image  → WordPress attachment ID      e.g. 42
 *
 * Gap 6  — Admin color picker + image uploader per global attribute term
 * Gap 23 — Admin assets screen-gated: wp-color-picker + wp_enqueue_media
 * Gap 25 — Cache invalidation when term meta is saved
 * Gap 42 — No raw DB queries; all via WP term meta API
 * Gap 53 — wp_kses() on all admin HTML output
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Term_Meta {

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected static ?WSE_Term_Meta $instance = null;

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

		// Only hook admin actions in the admin area
		if ( ! is_admin() ) {
			return;
		}

		// Gap 23 — Enqueue admin assets only on WC attribute term screens
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

		// Register form field hooks for every pa_* taxonomy
		// Uses admin_init so WC taxonomies are fully registered
		add_action( 'admin_init', array( $this, 'register_form_hooks' ) );

		// Save meta on both create and edit
		// created_term: ($term_id, $tt_id, $taxonomy)
		// edited_term:  ($term_id, $tt_id, $taxonomy)
		add_action( 'created_term', array( $this, 'save_term_meta' ), 10, 3 );
		add_action( 'edited_term',  array( $this, 'save_term_meta' ), 10, 3 );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 23 — Screen-gated admin asset enqueue
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Enqueues wp-color-picker, wp.media, and our admin JS/CSS
	 * ONLY on WC attribute term edit screens (edit-pa_*).
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function admin_enqueue( string $hook ): void {

		$screen = get_current_screen();

		// Only load on WC attribute term screens: edit-pa_color, edit-pa_size etc.
		if ( ! $screen || strpos( $screen->id, 'edit-pa_' ) === false ) {
			return;
		}

		// Native WordPress color picker (bundled with WP — no download needed)
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'wp-color-picker' );

		// WordPress media uploader (needed for image swatch uploads)
		wp_enqueue_media();

		// v1.1.6 (Bug #6): admin JS lives in admin/, not assets/js/, and is
		// shipped unminified only — using WSE_SUFFIX here produced a 404 in
		// production (.min.js never existed), so the global-attribute term
		// editor's color picker + image uploader were broken in the same way
		// the local-attribute metabox was. Path corrected.
		wp_enqueue_script(
			'wse-admin-term-meta',
			WSE_URL . 'admin/admin-term-meta.js',
			array( 'jquery', 'wp-color-picker' ),
			WSE_VERSION,
			true // load in footer
		);

		// Pass data to JS
		wp_localize_script(
			'wse-admin-term-meta',
			'WSETermMeta',
			array(
				'choose_image'  => esc_html__( 'Choose Swatch Image', 'woo-swatches-elementor' ),
				'use_image'     => esc_html__( 'Use this image',       'woo-swatches-elementor' ),
				'remove'        => esc_html__( 'Remove image',         'woo-swatches-elementor' ),
				'placeholder'   => esc_url( wc_placeholder_img_src( 'wse_swatch' ) ),
			)
		);

		// v1.1.6 (Bug #6): admin.css lives in admin/, not assets/css/.
		// Wrong path 404'd, leaving the term-edit screen unstyled. Corrected.
		wp_enqueue_style(
			'wse-admin',
			WSE_URL . 'admin/admin.css',
			array( 'wp-color-picker' ),
			WSE_VERSION
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Register form field hooks per taxonomy
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Registers add/edit form field actions for every WC attribute taxonomy.
	 * Called on admin_init so all pa_* taxonomies are fully registered.
	 */
	public function register_form_hooks(): void {

		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( empty( $attribute_taxonomies ) ) {
			return;
		}

		foreach ( $attribute_taxonomies as $taxonomy_obj ) {
			$tax = wc_attribute_taxonomy_name( $taxonomy_obj->attribute_name );

			// "Add Term" screen — uses <div class="form-field"> structure
			add_action(
				"{$tax}_add_form_fields",
				array( $this, 'render_add_form_field' )
			);

			// "Edit Term" screen — uses <tr class="form-field"> structure
			add_action(
				"{$tax}_edit_form_fields",
				array( $this, 'render_edit_form_field' ),
				10,
				2
			);
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// Render: Add Term screen
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Outputs the swatch field on the "Add Attribute Term" screen.
	 * Structure: <div class="form-field"> (WC standard for add screens).
	 *
	 * @param string $taxonomy Current attribute taxonomy slug e.g. 'pa_color'.
	 */
	public function render_add_form_field( string $taxonomy ): void {

		$type = WSE_Attribute_Types::get_attribute_type( $taxonomy );

		// Only render for swatch types — leave non-swatch attrs untouched
		if ( ! WSE_Attribute_Types::is_swatch_type( $taxonomy ) ) {
			return;
		}

		// Nonce field — verified on save
		wp_nonce_field( 'wse_term_meta_nonce', 'wse_term_meta_nonce' );

		if ( 'color' === $type ) {
			$this->render_color_field_add();
		} elseif ( 'image' === $type ) {
			$this->render_image_field_add();
		}
		// label / button types need no extra field — term name IS the label
	}

	/**
	 * Outputs the color picker field inside the Add Term form.
	 */
	private function render_color_field_add(): void {
		echo wp_kses(
			sprintf(
				'<div class="form-field term-wse-color-wrap">
					<label for="wse_color">%s</label>
					<input type="text"
					       id="wse_color"
					       name="wse_color"
					       value=""
					       class="wse-color-picker"
					       data-default-color="#ffffff"
					       maxlength="7"/>
					<p class="description">%s</p>
				</div>',
				esc_html__( 'Swatch Color', 'woo-swatches-elementor' ),
				esc_html__( 'Choose the color for this attribute term swatch.', 'woo-swatches-elementor' )
			),
			self::allowed_html()
		);
	}

	/**
	 * Outputs the image uploader field inside the Add Term form.
	 */
	private function render_image_field_add(): void {
		echo wp_kses(
			sprintf(
				'<div class="form-field term-wse-image-wrap">
					<label for="wse_image">%s</label>
					<div class="wse-image-upload-wrap">
						<img id="wse_image_preview"
						     src="%s"
						     alt="%s"
						     class="wse-image-preview hidden"/>
						<input type="hidden" id="wse_image" name="wse_image" value=""/>
						<button type="button" class="button wse-upload-image-btn">%s</button>
						<button type="button" class="button-link wse-remove-image-btn hidden">%s</button>
					</div>
					<p class="description">%s</p>
				</div>',
				esc_html__( 'Swatch Image', 'woo-swatches-elementor' ),
				esc_url( wc_placeholder_img_src( 'wse_swatch' ) ),
				esc_attr__( 'Swatch image preview', 'woo-swatches-elementor' ),
				esc_html__( 'Upload Image', 'woo-swatches-elementor' ),
				esc_html__( 'Remove', 'woo-swatches-elementor' ),
				esc_html__( 'Upload or choose an image for this attribute term swatch.', 'woo-swatches-elementor' )
			),
			self::allowed_html()
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Render: Edit Term screen
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Outputs the swatch field on the "Edit Attribute Term" screen.
	 * Structure: <tr class="form-field"> (WC standard for edit screens).
	 *
	 * @param \WP_Term $term     The term being edited.
	 * @param string   $taxonomy Current attribute taxonomy slug e.g. 'pa_color'.
	 */
	public function render_edit_form_field( \WP_Term $term, string $taxonomy ): void {

		$type = WSE_Attribute_Types::get_attribute_type( $taxonomy );

		if ( ! WSE_Attribute_Types::is_swatch_type( $taxonomy ) ) {
			return;
		}

		// Nonce field — verified on save
		wp_nonce_field( 'wse_term_meta_nonce', 'wse_term_meta_nonce' );

		if ( 'color' === $type ) {
			$this->render_color_field_edit( $term );
		} elseif ( 'image' === $type ) {
			$this->render_image_field_edit( $term );
		}
	}

	/**
	 * Outputs the color picker field inside the Edit Term form.
	 *
	 * @param \WP_Term $term The term being edited.
	 */
	private function render_color_field_edit( \WP_Term $term ): void {

		$saved_color = (string) get_term_meta( $term->term_id, 'wse_color', true );
		$color_value = ! empty( $saved_color ) ? $saved_color : '';

		echo wp_kses(
			sprintf(
				'<tr class="form-field term-wse-color-wrap">
					<th scope="row">
						<label for="wse_color">%s</label>
					</th>
					<td>
						<input type="text"
						       id="wse_color"
						       name="wse_color"
						       value="%s"
						       class="wse-color-picker"
						       data-default-color="#ffffff"
						       maxlength="7"/>
						<p class="description">%s</p>
					</td>
				</tr>',
				esc_html__( 'Swatch Color', 'woo-swatches-elementor' ),
				esc_attr( $color_value ),
				esc_html__( 'Choose the color for this attribute term swatch.', 'woo-swatches-elementor' )
			),
			self::allowed_html()
		);
	}

	/**
	 * Outputs the image uploader field inside the Edit Term form.
	 *
	 * @param \WP_Term $term The term being edited.
	 */
	private function render_image_field_edit( \WP_Term $term ): void {

		$image_id  = (int) get_term_meta( $term->term_id, 'wse_image', true );
		$image_src = $image_id
			? wp_get_attachment_image_url( $image_id, 'wse_swatch' )
			: '';
		$has_image = ! empty( $image_src );

		echo wp_kses(
			sprintf(
				'<tr class="form-field term-wse-image-wrap">
					<th scope="row">
						<label for="wse_image">%s</label>
					</th>
					<td>
						<div class="wse-image-upload-wrap">
							<img id="wse_image_preview"
							     src="%s"
							     alt="%s"
							     class="wse-image-preview%s"/>
							<input type="hidden" id="wse_image" name="wse_image" value="%s"/>
							<button type="button" class="button wse-upload-image-btn">%s</button>
							<button type="button" class="button-link wse-remove-image-btn%s">%s</button>
						</div>
						<p class="description">%s</p>
					</td>
				</tr>',
				esc_html__( 'Swatch Image', 'woo-swatches-elementor' ),
				$has_image ? esc_url( $image_src ) : esc_url( wc_placeholder_img_src( 'wse_swatch' ) ),
				esc_attr__( 'Swatch image preview', 'woo-swatches-elementor' ),
				$has_image ? '' : ' hidden',
				$image_id > 0 ? esc_attr( (string) $image_id ) : '',
				esc_html__( 'Upload Image', 'woo-swatches-elementor' ),
				$has_image ? '' : ' hidden',
				esc_html__( 'Remove', 'woo-swatches-elementor' ),
				esc_html__( 'Upload or choose an image for this attribute term swatch.', 'woo-swatches-elementor' )
			),
			self::allowed_html()
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Save term meta
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Saves color/image term meta on both term create and term edit.
	 *
	 * Hooked into:
	 *   created_term ($term_id, $tt_id, $taxonomy)
	 *   edited_term  ($term_id, $tt_id, $taxonomy)
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID (unused but required by hook).
	 * @param string $taxonomy Taxonomy slug e.g. 'pa_color'.
	 */
	public function save_term_meta( int $term_id, int $tt_id, string $taxonomy ): void {

		// Only handle WC attribute taxonomies (pa_*)
		if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
			return;
		}

		// Only handle our swatch types
		if ( ! WSE_Attribute_Types::is_swatch_type( $taxonomy ) ) {
			return;
		}

		// Gap 13 — Nonce verification
		if ( ! isset( $_POST['wse_term_meta_nonce'] ) ||
			 ! wp_verify_nonce(
				 sanitize_key( wp_unslash( $_POST['wse_term_meta_nonce'] ) ),
				 'wse_term_meta_nonce'
			 )
		) {
			return;
		}

		// Gap 13 — Capability check
		if ( ! current_user_can( 'manage_product_terms' ) &&
			 ! current_user_can( 'edit_terms', $taxonomy ) ) {
			return;
		}

		$type = WSE_Attribute_Types::get_attribute_type( $taxonomy );

		// ── Save color ────────────────────────────────────────────────────
		if ( 'color' === $type && isset( $_POST['wse_color'] ) ) {
			// Gap 13 — sanitize_hex_color() validates and normalises the hex value
			$color = sanitize_hex_color( wp_unslash( $_POST['wse_color'] ) );
			if ( $color ) {
				update_term_meta( $term_id, 'wse_color', $color );
			} else {
				delete_term_meta( $term_id, 'wse_color' );
			}
		}

		// ── Save image ────────────────────────────────────────────────────
		if ( 'image' === $type && isset( $_POST['wse_image'] ) ) {
			// Gap 13 — absint() ensures we only store a positive integer
			$image_id = absint( $_POST['wse_image'] );
			if ( $image_id > 0 ) {
				update_term_meta( $term_id, 'wse_image', $image_id );
			} else {
				delete_term_meta( $term_id, 'wse_image' );
			}
		}

		// Gap 25 — Invalidate swatch transient cache for all products
		// using this term. WSE_Cache is available from Phase 5 onwards.
		if ( class_exists( 'WSE_Cache' ) ) {
			WSE_Cache::clear_by_term( $term_id );
		}

		// Invalidate WSE_Attribute_Types in-memory cache for this taxonomy
		WSE_Attribute_Types::instance()->clear_cache_for( $taxonomy );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Public data accessors
	// Used by WSE_Swatch_Renderer (Phase 6) to retrieve stored swatch data
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the saved hex color for a term, or empty string if none.
	 *
	 * @param int $term_id Term ID.
	 * @return string Hex color e.g. '#ff0000' or ''.
	 */
	public static function get_color( int $term_id ): string {
		return (string) get_term_meta( $term_id, 'wse_color', true );
	}

	/**
	 * Returns the saved image attachment ID for a term, or 0 if none.
	 *
	 * @param int $term_id Term ID.
	 * @return int Attachment ID or 0.
	 */
	public static function get_image_id( int $term_id ): int {
		return (int) get_term_meta( $term_id, 'wse_image', true );
	}

	/**
	 * Returns the image URL for a term at a given size.
	 *
	 * @param int    $term_id    Term ID.
	 * @param string $image_size WordPress image size name. Defaults to 'wse_swatch'.
	 * @return string Image URL or empty string.
	 */
	public static function get_image_url( int $term_id, string $image_size = 'wse_swatch' ): string {
		$image_id = self::get_image_id( $term_id );
		if ( ! $image_id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $image_id, $image_size );
		return $url ?: '';
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 53 — Allowed HTML for wp_kses() on admin output
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the allowed HTML array for wp_kses() used in this class.
	 * Centralised so every render method uses a consistent allowlist.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function allowed_html(): array {
		return array(
			'div'   => array( 'class' => true, 'id' => true ),
			'tr'    => array( 'class' => true ),
			'th'    => array( 'scope' => true ),
			'td'    => array(),
			'label' => array( 'for' => true ),
			'input' => array(
				'type'              => true,
				'id'                => true,
				'name'              => true,
				'value'             => true,
				'class'             => true,
				'data-default-color'=> true,
				'maxlength'         => true,
			),
			'img'   => array(
				'id'    => true,
				'src'   => true,
				'alt'   => true,
				'class' => true,
			),
			'button' => array(
				'type'  => true,
				'class' => true,
			),
			'p'     => array( 'class' => true ),
		);
	}
}
