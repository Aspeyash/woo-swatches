<?php
/**
 * WooCommerce Settings Tab — WooSwatches
 *
 * Registers a "WooSwatches" tab under WooCommerce → Settings.
 * Extends WC_Settings_Page so all standard WC field types (select,
 * checkbox, number, text) render and save for free.
 *
 * Tab ID      : woo_swatches
 * URL         : ?page=wc-settings&tab=woo_swatches
 *
 * All option keys follow the existing wse_* convention set in
 * WSE_Activator so no migration is needed on existing installs.
 *
 * Cache flush button delegates to WSE_Cache::ajax_flush_all() via the
 * existing wp_ajax_wse_flush_cache action — no new AJAX handler required.
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

// Guard: only available after WooCommerce loads WC_Settings_Page.
// The instantiation hook (woocommerce_get_settings_pages) guarantees this.
if ( ! class_exists( 'WC_Settings_Page' ) ) {
	return;
}

class WSE_Settings extends WC_Settings_Page {

	// ─────────────────────────────────────────────────────────────────────
	// Init
	// ─────────────────────────────────────────────────────────────────────

	public function __construct() {
		$this->id    = 'woo_swatches';
		$this->label = esc_html__( 'WooSwatches', 'woo-swatches-elementor' );

		parent::__construct();

		// Register the custom field renderer for the cache info row.
		add_action(
			'woocommerce_admin_field_wse_cache_info',
			array( $this, 'render_cache_info_field' )
		);

		// Print the tiny flush-button script only on our settings tab.
		add_action( 'admin_footer', array( $this, 'print_flush_script' ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Settings definition
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the settings array for the default (only) section.
	 *
	 * WC_Settings_Page::get_settings() calls this automatically when
	 * no section is selected (i.e. the default section).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings_for_default_section(): array {
		return array(

			// ── Display ───────────────────────────────────────────────────
			array(
				'title' => esc_html__( 'Display', 'woo-swatches-elementor' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Global defaults. Each Elementor widget can override these per instance.', 'woo-swatches-elementor' ),
				'id'    => 'wse_display_section',
			),
			array(
				'title'    => esc_html__( 'Swatch Shape', 'woo-swatches-elementor' ),
				'id'       => 'wse_shape',
				'type'     => 'select',
				'default'  => 'rounded',
				'options'  => array(
					'rounded' => esc_html__( 'Rounded (6 px)', 'woo-swatches-elementor' ),
					'circle'  => esc_html__( 'Circle (50%)',   'woo-swatches-elementor' ),
					'square'  => esc_html__( 'Square',         'woo-swatches-elementor' ),
				),
				'desc_tip' => esc_html__( 'Border-radius applied to all swatches.', 'woo-swatches-elementor' ),
			),
			array(
				'title'   => esc_html__( 'Tooltip', 'woo-swatches-elementor' ),
				'id'      => 'wse_tooltip',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => esc_html__( 'Show term name on swatch hover', 'woo-swatches-elementor' ),
			),
			array(
				'title'    => esc_html__( 'Out-of-Stock Swatches', 'woo-swatches-elementor' ),
				'id'       => 'wse_oos_behavior',
				'type'     => 'select',
				'default'  => 'blur',
				'options'  => array(
					'blur'  => esc_html__( 'Blur',              'woo-swatches-elementor' ),
					'cross' => esc_html__( 'Blur + Cross-out',  'woo-swatches-elementor' ),
					'hide'  => esc_html__( 'Hide swatch',       'woo-swatches-elementor' ),
				),
				'desc_tip' => esc_html__( 'How to display swatches for out-of-stock variations.', 'woo-swatches-elementor' ),
			),
			array(
				'title'    => esc_html__( 'Plugin Stylesheet', 'woo-swatches-elementor' ),
				'id'       => 'wse_stylesheet',
				'type'     => 'checkbox',
				'default'  => 'yes',
				'desc'     => esc_html__( 'Load plugin CSS. Disable if your theme provides its own swatch styles.', 'woo-swatches-elementor' ),
				'desc_tip' => esc_html__( 'When disabled, the wse-stylesheet-disabled body class applies and no visual CSS loads.', 'woo-swatches-elementor' ),
			),
			array(
				'title'    => esc_html__( 'Show "View Cart" Link After Add to Cart', 'woo-swatches-elementor' ),
				'id'       => 'wse_show_view_cart_link',
				'type'     => 'checkbox',
				'default'  => 'yes',
				'desc'     => esc_html__( 'Append a View Cart link to add-to-cart success notices.', 'woo-swatches-elementor' ),
				'desc_tip' => esc_html__( 'Disable to hide the "View Cart" link in success messages, the snackbar, and the mini-cart fragment so shoppers stay on the product page.', 'woo-swatches-elementor' ),
			),
			array(
				'title'    => esc_html__( 'Show "Added to Cart" Toast', 'woo-swatches-elementor' ),
				'id'       => 'wse_show_added_toast',
				'type'     => 'checkbox',
				'default'  => 'yes',
				'desc'     => esc_html__( 'Show a small toast notification at the bottom of the screen when a product is successfully added to the cart.', 'woo-swatches-elementor' ),
			),
			// v1.2.3 (Issue 3) — Sale dot indicator on swatches.
			// Independent from the tooltip setting. Defaults ON for back-
			// compat with v1.2.1+ behaviour. Turn OFF to suppress the
			// small ZYMARG-purple dot in the corner of swatches that have
			// at least one on-sale variation.
			array(
				'title'    => esc_html__( 'Show Sale Dot on Swatches', 'woo-swatches-elementor' ),
				'id'       => 'wse_show_sale_dot',
				'type'     => 'checkbox',
				'default'  => 'yes',
				'desc'     => esc_html__( 'When ON, a small purple dot is rendered in the corner of any swatch whose variation is on sale. Independent of the Tooltip setting above.', 'woo-swatches-elementor' ),
			),
			array(
				'title'    => esc_html__( 'Multi-vendor Compatibility Mode', 'woo-swatches-elementor' ),
				'id'       => 'wse_multivendor_compat',
				'type'     => 'select',
				'default'  => 'auto',
				'options'  => array(
					'auto' => esc_html__( 'Auto (detect Dokan / WCFM / WC Vendors)', 'woo-swatches-elementor' ),
					'on'   => esc_html__( 'Always on',  'woo-swatches-elementor' ),
					'off'  => esc_html__( 'Always off', 'woo-swatches-elementor' ),
				),
				'desc_tip' => esc_html__( 'When on, the AJAX add-to-cart re-checks the cart hash whenever WooCommerce returns an error response. If the cart actually changed, the request is treated as a success — handles Dokan / WCFM vendor-validation pipelines that mark AJAX requests as failed even though the item was added. Auto enables this only when a multi-vendor plugin is detected.', 'woo-swatches-elementor' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wse_display_section',
			),

			// ── Archive / Shop Loop ───────────────────────────────────────
			array(
				'title' => esc_html__( 'Archive / Shop Loop', 'woo-swatches-elementor' ),
				'type'  => 'title',
				'id'    => 'wse_archive_section',
			),
			array(
				'title'   => esc_html__( 'Enable on Archive Pages', 'woo-swatches-elementor' ),
				'id'      => 'wse_archive_swatches',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => esc_html__( 'Render swatches below product titles on shop / category pages', 'woo-swatches-elementor' ),
			),
			array(
				'title'             => esc_html__( 'Max Swatches Per Product', 'woo-swatches-elementor' ),
				'id'                => 'wse_archive_max',
				'type'              => 'number',
				'default'           => 5,
				'desc_tip'          => esc_html__( 'Maximum swatches shown in the shop loop before a "+N more" indicator.', 'woo-swatches-elementor' ),
				'custom_attributes' => array( 'min' => 1, 'max' => 20, 'step' => 1 ),
			),
			array(
				'title'    => esc_html__( 'Archive Click Behaviour', 'woo-swatches-elementor' ),
				'id'       => 'wse_archive_click',
				'type'     => 'select',
				'default'  => 'link',
				'options'  => array(
					'link'             => esc_html__( 'Go to product page',     'woo-swatches-elementor' ),
					'ajax_add_to_cart' => esc_html__( 'AJAX Add to Cart',       'woo-swatches-elementor' ),
				),
				'desc_tip' => esc_html__( 'What happens when a swatch is clicked in the shop/archive loop.', 'woo-swatches-elementor' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wse_archive_section',
			),

			// ── v1.2.1 (F2) — Sticky Add to Cart (moved from Widget 2) ────
			array(
				'title' => esc_html__( 'Sticky Add to Cart', 'woo-swatches-elementor' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Pin Widget 2 (Add to Cart) to the bottom of the viewport on the chosen breakpoints. Applies site-wide to every product page so customers always have a clear path to add to cart on mobile.', 'woo-swatches-elementor' ),
				'id'    => 'wse_sticky_section',
			),
			array(
				'title'   => esc_html__( 'Sticky on Desktop (≥ 1025 px)', 'woo-swatches-elementor' ),
				'id'      => 'wse_sticky_desktop',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => esc_html__( 'Sticky on Tablet (768–1024 px)', 'woo-swatches-elementor' ),
				'id'      => 'wse_sticky_tablet',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => esc_html__( 'Sticky on Mobile (≤ 767 px)', 'woo-swatches-elementor' ),
				'id'      => 'wse_sticky_mobile',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => esc_html__( 'Recommended ON. Mobile shoppers benefit most from a persistently visible Add to Cart.', 'woo-swatches-elementor' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wse_sticky_section',
			),

			// ── v1.2.1 (F5) — Swatch Sizes (responsive) ───────────────────
			array(
				'title' => esc_html__( 'Swatch Sizes', 'woo-swatches-elementor' ),
				'type'  => 'title',
				'desc'  => esc_html__( 'Per-type swatch widths at each breakpoint. Each Elementor Widget 1 instance can override these globally via its Style tab. Leave a value at 0 to inherit the default.', 'woo-swatches-elementor' ),
				'id'    => 'wse_sizes_section',
			),

			// Color swatches (square — width = height)
			array(
				'title'             => esc_html__( 'Color Swatch — Desktop', 'woo-swatches-elementor' ),
				'id'                => 'wse_color_w_d',
				'type'              => 'number',
				'default'           => 32,
				'desc_tip'          => esc_html__( 'Width AND height of color swatches on screens > 1024 px (px).', 'woo-swatches-elementor' ),
				'custom_attributes' => array( 'min' => 16, 'max' => 96, 'step' => 1 ),
			),
			array(
				'title'             => esc_html__( 'Color Swatch — Tablet', 'woo-swatches-elementor' ),
				'id'                => 'wse_color_w_t',
				'type'              => 'number',
				'default'           => 32,
				'custom_attributes' => array( 'min' => 16, 'max' => 96, 'step' => 1 ),
			),
			array(
				'title'             => esc_html__( 'Color Swatch — Mobile', 'woo-swatches-elementor' ),
				'id'                => 'wse_color_w_m',
				'type'              => 'number',
				'default'           => 28,
				'custom_attributes' => array( 'min' => 16, 'max' => 96, 'step' => 1 ),
			),

			// Image swatches
			array(
				'title'             => esc_html__( 'Image Swatch — Desktop', 'woo-swatches-elementor' ),
				'id'                => 'wse_image_w_d',
				'type'              => 'number',
				'default'           => 56,
				'desc_tip'          => esc_html__( 'Width AND height of image swatches on screens > 1024 px (px).', 'woo-swatches-elementor' ),
				'custom_attributes' => array( 'min' => 24, 'max' => 160, 'step' => 1 ),
			),
			array(
				'title'             => esc_html__( 'Image Swatch — Tablet', 'woo-swatches-elementor' ),
				'id'                => 'wse_image_w_t',
				'type'              => 'number',
				'default'           => 48,
				'custom_attributes' => array( 'min' => 24, 'max' => 160, 'step' => 1 ),
			),
			array(
				'title'             => esc_html__( 'Image Swatch — Mobile', 'woo-swatches-elementor' ),
				'id'                => 'wse_image_w_m',
				'type'              => 'number',
				'default'           => 44,
				'custom_attributes' => array( 'min' => 24, 'max' => 160, 'step' => 1 ),
			),

			// Label swatches (auto-width text — control min-width)
			array(
				'title'             => esc_html__( 'Label Swatch — Desktop (min-width)', 'woo-swatches-elementor' ),
				'id'                => 'wse_label_w_d',
				'type'              => 'number',
				'default'           => 32,
				'desc_tip'          => esc_html__( 'Minimum width of label swatches on screens > 1024 px (px). Text content can grow beyond this.', 'woo-swatches-elementor' ),
				'custom_attributes' => array( 'min' => 16, 'max' => 200, 'step' => 1 ),
			),
			array(
				'title'             => esc_html__( 'Label Swatch — Tablet (min-width)', 'woo-swatches-elementor' ),
				'id'                => 'wse_label_w_t',
				'type'              => 'number',
				'default'           => 32,
				'custom_attributes' => array( 'min' => 16, 'max' => 200, 'step' => 1 ),
			),
			array(
				'title'             => esc_html__( 'Label Swatch — Mobile (min-width)', 'woo-swatches-elementor' ),
				'id'                => 'wse_label_w_m',
				'type'              => 'number',
				'default'           => 28,
				'custom_attributes' => array( 'min' => 16, 'max' => 200, 'step' => 1 ),
			),

			// Button swatches (similar — control min-width)
			array(
				'title'             => esc_html__( 'Button Swatch — Desktop (min-width)', 'woo-swatches-elementor' ),
				'id'                => 'wse_button_w_d',
				'type'              => 'number',
				'default'           => 48,
				'desc_tip'          => esc_html__( 'Minimum width of button swatches on screens > 1024 px (px). Text content can grow beyond this.', 'woo-swatches-elementor' ),
				'custom_attributes' => array( 'min' => 24, 'max' => 240, 'step' => 1 ),
			),
			array(
				'title'             => esc_html__( 'Button Swatch — Tablet (min-width)', 'woo-swatches-elementor' ),
				'id'                => 'wse_button_w_t',
				'type'              => 'number',
				'default'           => 44,
				'custom_attributes' => array( 'min' => 24, 'max' => 240, 'step' => 1 ),
			),
			array(
				'title'             => esc_html__( 'Button Swatch — Mobile (min-width)', 'woo-swatches-elementor' ),
				'id'                => 'wse_button_w_m',
				'type'              => 'number',
				'default'           => 40,
				'custom_attributes' => array( 'min' => 24, 'max' => 240, 'step' => 1 ),
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wse_sizes_section',
			),

			// ── Performance ───────────────────────────────────────────────
			array(
				'title' => esc_html__( 'Performance', 'woo-swatches-elementor' ),
				'type'  => 'title',
				'id'    => 'wse_performance_section',
			),
			array(
				'title'             => esc_html__( 'Cache Duration (seconds)', 'woo-swatches-elementor' ),
				'id'                => 'wse_cache_ttl',
				'type'              => 'number',
				'default'           => DAY_IN_SECONDS,
				'desc_tip'          => esc_html__( 'How long swatch data is cached per product. Default: 86400 (24 hours). Minimum: 60.', 'woo-swatches-elementor' ),
				'custom_attributes' => array( 'min' => 60, 'step' => 60 ),
			),
			// Custom field: cache entry count + flush button.
			array(
				'type' => 'wse_cache_info',
				'id'   => 'wse_cache_info',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wse_performance_section',
			),

			// ── Advanced ──────────────────────────────────────────────────
			array(
				'title' => esc_html__( 'Advanced', 'woo-swatches-elementor' ),
				'type'  => 'title',
				'id'    => 'wse_advanced_section',
			),
			array(
				'title'   => esc_html__( 'Delete Data on Uninstall', 'woo-swatches-elementor' ),
				'id'      => 'wse_delete_on_uninstall',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => esc_html__( 'Remove all plugin options, term meta, and transients when the plugin is deleted from WP admin.', 'woo-swatches-elementor' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wse_advanced_section',
			),
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Custom field: cache info row
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Renders the cache entry count and flush button table row.
	 *
	 * The flush button POSTs to the existing wp_ajax_wse_flush_cache action
	 * defined in WSE_Cache — no new PHP handler is needed.
	 *
	 * @param array<string, mixed> $value Field config (unused but required by hook signature).
	 */
	public function render_cache_info_field( array $value ): void {

		$count = class_exists( 'WSE_Cache' ) ? WSE_Cache::get_cache_count() : 0;
		$nonce = wp_create_nonce( 'wse_flush_cache' );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php esc_html_e( 'Swatch Cache', 'woo-swatches-elementor' ); ?></label>
			</th>
			<td class="forminp">
				<span id="wse-cache-count">
					<?php
					printf(
						/* translators: %d: number of cached product entries */
						esc_html( _n(
							'%d cached entry',
							'%d cached entries',
							$count,
							'woo-swatches-elementor'
						) ),
						(int) $count
					);
					?>
				</span>
				&nbsp;&nbsp;
				<button
					type="button"
					id="wse-flush-cache-btn"
					class="button button-secondary"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
				>
					<?php esc_html_e( 'Flush Cache', 'woo-swatches-elementor' ); ?>
				</button>
				<span id="wse-flush-msg" style="margin-left:10px;display:none;"></span>
				<p class="description">
					<?php esc_html_e( 'Clears all swatch transients. Data will be rebuilt on the next product page load.', 'woo-swatches-elementor' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────
	// Cache flush inline script
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Prints a small inline script for the flush button.
	 * Only runs on our settings tab to avoid admin overhead.
	 */
	public function print_flush_script(): void {
		$screen = get_current_screen();

		// Only fire on the WC settings screen.
		if ( ! $screen || 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['tab'] ) || 'woo_swatches' !== $_GET['tab'] ) {
			return;
		}
		?>
		<script id="wse-settings-flush">
		( function ( $ ) {
			$( '#wse-flush-cache-btn' ).on( 'click', function () {
				var $btn = $( this );
				var $msg = $( '#wse-flush-msg' );
				var $cnt = $( '#wse-cache-count' );

				$btn.prop( 'disabled', true )
				    .text( '<?php echo esc_js( __( 'Flushing…', 'woo-swatches-elementor' ) ); ?>' );

				$.post( ajaxurl, {
					action   : 'wse_flush_cache',
					security : $btn.data( 'nonce' ),
				} )
				.done( function ( res ) {
					if ( res.success ) {
						$msg.css( 'color', '#46b450' ).text( res.data.message );
						$cnt.text( '<?php echo esc_js( __( '0 cached entries', 'woo-swatches-elementor' ) ); ?>' );
					} else {
						$msg.css( 'color', '#dc3232' )
						    .text( res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Error. Try again.', 'woo-swatches-elementor' ) ); ?>' );
					}
					$msg.show();
					setTimeout( function () { $msg.fadeOut(); }, 4000 );
				} )
				.fail( function () {
					$msg.css( 'color', '#dc3232' )
					    .text( '<?php echo esc_js( __( 'Request failed.', 'woo-swatches-elementor' ) ); ?>' )
					    .show();
				} )
				.always( function () {
					$btn.prop( 'disabled', false )
					    .text( '<?php echo esc_js( __( 'Flush Cache', 'woo-swatches-elementor' ) ); ?>' );
				} );
			} );
		} )( jQuery );
		</script>
		<?php
	}
}
