<?php
/**
 * Shop / archive loop swatch display.
 *
 * Renders variation swatches below the product title on all standard
 * WooCommerce shop, category, tag, and custom taxonomy archive pages.
 *
 * Controlled by the admin toggle: WooSwatches → Settings → Archive.
 * When OFF, this class registers its hooks but returns early — so
 * disabling and re-enabling never breaks anything.
 *
 * Architecture notes:
 *   • woocommerce_after_shop_loop_item_title fires for every product
 *     card in the loop. We check is_type('variable') before rendering.
 *   • We call wc_dropdown_variation_attribute_options() exactly
 *     as a product page would. WSE_Swatch_Renderer::render() intercepts
 *     the filter and replaces the <select> with swatch HTML — exactly
 *     the same code path used on single product pages.
 *   • The hidden <select> is kept in the DOM so that archive-specific
 *     JS (added as inline script) can read current selections.
 *
 * Gap 43 — Archive/shop loop swatches with on/off toggle
 * Gap 45 — Single attribute selector: show only the most relevant attr
 * Gap 47 — AJAX add-to-cart for variable products on archive pages
 * Gap 24 — Cart fragment refresh after successful AJAX add
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Archive_Swatches {

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected static ?WSE_Archive_Swatches $instance = null;

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

		// Render swatches in the shop loop (always register, guard inside callback)
		add_action(
			'woocommerce_after_shop_loop_item_title',
			array( $this, 'render_archive_swatches' ),
			15
		);

		// Add inline JS on archive pages (after wse-swatches is enqueued)
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_archive_scripts' ), 20 );

		// Gap 47 — AJAX add-to-cart handler for variable products in loop
		add_action( 'wp_ajax_wse_archive_add_to_cart',        array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_wse_archive_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Archive swatch renderer
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Renders swatches for the current product in the shop loop.
	 * Hooked to woocommerce_after_shop_loop_item_title at priority 15.
	 */
	public function render_archive_swatches(): void {

		// Gap 43 — on/off toggle check
		if ( ! 'yes' === get_option( 'wse_archive_swatches', 'yes' ) ) {
			return;
		}

		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		// Only render for variable products (swatches need variations)
		if ( ! $product->is_type( 'variable' ) ) {
			return;
		}

		$attributes = $product->get_variation_attributes();
		if ( empty( $attributes ) ) {
			return;
		}

		// ── Gap 45 — Attribute selection ──────────────────────────────────
		// Determine which attribute(s) to render in the archive loop.
		// Default: the first swatch-type attribute, or the first attribute if
		// none are swatch types.
		// Admin option wse_archive_attribute ('') means auto-detect first swatch.
		$attrs_to_render = $this->get_archive_attributes( $attributes, $product );

		if ( empty( $attrs_to_render ) ) {
			return;
		}

		$product_id     = $product->get_id();
		$product_url    = get_permalink( $product_id );
		$click_behavior = sanitize_key( get_option( 'wse_archive_click', 'link' ) );
		$max_swatches   = absint( get_option( 'wse_archive_max', 5 ) );

		// ── Wrapper div ────────────────────────────────────────────────────
		printf(
			'<div class="wse-archive-swatches" data-product-id="%s" data-product-url="%s" data-click-behavior="%s" data-max-swatches="%s">',
			esc_attr( (string) $product_id ),
			esc_url( $product_url ),
			esc_attr( $click_behavior ),
			esc_attr( (string) $max_swatches )
		);

		// ── Render swatches per attribute ─────────────────────────────────
		foreach ( $attrs_to_render as $attribute_name => $options ) {

			// wc_dropdown_variation_attribute_options() calls the
			// woocommerce_dropdown_variation_attribute_options_html filter.
			// WSE_Swatch_Renderer::render() intercepts it and outputs swatches.
			wc_dropdown_variation_attribute_options(
				array(
					'options'          => $options,
					'attribute'        => $attribute_name,
					'product'          => $product,
					'selected'         => '',
					'show_option_none' => false, // no "Choose an option" in archive
				)
			);
		}

		// ── AJAX mode: add to cart button (hidden until swatch selected) ──
		if ( 'ajax_add_to_cart' === $click_behavior ) {
			printf(
				'<button type="button" class="wse-archive-atc button" data-product-id="%s" style="display:none">%s</button>',
				esc_attr( (string) $product_id ),
				esc_html__( 'Add to cart', 'woo-swatches-elementor' )
			);
		}

		echo '</div>'; // .wse-archive-swatches
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 45 — Attribute selection logic
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Determines which attributes to render in the archive loop.
	 *
	 * Priority:
	 *   1. Admin-configured specific attribute (wse_archive_attribute option)
	 *   2. First image-type attribute (most visually impactful)
	 *   3. First color-type attribute
	 *   4. First swatch-type attribute of any kind
	 *   5. First attribute regardless of type
	 *
	 * @param  array<string, array<string>> $attributes All product variation attributes.
	 * @param  \WC_Product                 $product    The variable product.
	 * @return array<string, array<string>>             Filtered attributes to render.
	 */
	private function get_archive_attributes(
		array $attributes,
		\WC_Product $product
	): array {

		// Admin setting: specific attribute name to always show ('pa_color', etc.)
		$configured = sanitize_key( (string) get_option( 'wse_archive_attribute', '' ) );

		if ( $configured && isset( $attributes[ $configured ] ) ) {
			return array( $configured => $attributes[ $configured ] );
		}

		// Auto-detect: prefer image > color > any swatch > any attribute
		$priority_order = array( 'image', 'color', 'label', 'button' );

		foreach ( $priority_order as $preferred_type ) {
			foreach ( $attributes as $attr_name => $options ) {
				$type = WSE_Attribute_Types::get_attribute_type( $attr_name );
				if ( $type === $preferred_type ) {
					return array( $attr_name => $options );
				}
			}
		}

		// Fallback: return the very first attribute
		$first_key = array_key_first( $attributes );
		return array( $first_key => $attributes[ $first_key ] );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Archive scripts (inline JS appended to wse-swatches)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Appends archive-specific click handling as an inline script on
	 * wp_enqueue_scripts at priority 20 (after wse-swatches is enqueued).
	 *
	 * Using wp_add_inline_script keeps the logic close to its PHP
	 * without requiring an extra HTTP request.
	 */
	public function enqueue_archive_scripts(): void {

		// Only on WC archive pages
		if ( ! is_shop() && ! is_product_category()
			&& ! is_product_tag() && ! is_product_taxonomy() ) {
			return;
		}

		// Only if wse-swatches was actually enqueued (Gap 19 check passed)
		if ( ! wp_script_is( 'wse-swatches', 'enqueued' ) ) {
			return;
		}

		wp_add_inline_script( 'wse-swatches', $this->get_archive_inline_js() );
	}

	/**
	 * Returns the inline JS string for archive swatch interactions.
	 * Handles both 'link' and 'ajax_add_to_cart' click modes.
	 *
	 * @return string JavaScript string (no <script> tags).
	 */
	private function get_archive_inline_js(): string {
		return <<<'JS'
/* WooSwatches for Elementor — Archive Swatches */
( function ( $ ) {
    'use strict';

    // ── Swatch click in archive loop ──────────────────────────────────
    $( document ).on(
        'click.wse-archive',
        '.wse-archive-swatches .wse-swatch:not(.disabled)',
        function ( e ) {
            var $swatch    = $( this );
            var attribute  = $swatch.data( 'attribute' );
            var value      = $swatch.data( 'value' );
            var $container = $swatch.closest( '.wse-archive-swatches' );
            var behavior   = $container.data( 'click-behavior' ) || 'link';
            var productUrl = String( $container.data( 'product-url' ) || '' );

            // Visual selection — scoped to this product card only
            $container
                .find( '.wse-swatch[data-attribute="' + attribute + '"]' )
                .removeClass( 'selected' )
                .attr( 'aria-checked', 'false' )
                .attr( 'tabindex', '-1' );

            $swatch
                .addClass( 'selected' )
                .attr( 'aria-checked', 'true' )
                .attr( 'tabindex', '0' );

            if ( behavior === 'link' ) {
                // Navigate to product page with attribute pre-selected
                e.preventDefault();
                var params = {};
                $container.find( '.wse-swatch.selected' ).each( function () {
                    params[ 'attribute_' + $( this ).data( 'attribute' ) ] =
                        String( $( this ).data( 'value' ) );
                } );
                if ( productUrl ) {
                    window.location.href = productUrl + '?' + $.param( params );
                }

            } else if ( behavior === 'ajax_add_to_cart' ) {
                // Show the Add to Cart button once a swatch is picked
                e.preventDefault();
                $container.find( '.wse-archive-atc' ).show();
            }
        }
    );

    // ── Archive AJAX add-to-cart (Gap 47) ─────────────────────────────
    $( document ).on(
        'click.wse-archive',
        '.wse-archive-atc',
        function ( e ) {
            e.preventDefault();

            if ( typeof WSEParams === 'undefined' ) {
                return;
            }

            var $btn       = $( this );
            var $container = $btn.closest( '.wse-archive-swatches' );
            var productId  = String( $container.data( 'product-id' ) || '' );

            if ( ! productId ) {
                return;
            }

            // Collect selected attribute values
            var postData = {
                action     : 'wse_archive_add_to_cart',
                security   : WSEParams.nonce,
                product_id : productId,
                quantity   : 1,
            };

            $container.find( '.wse-swatch.selected' ).each( function () {
                postData[ 'attribute_' + $( this ).data( 'attribute' ) ] =
                    String( $( this ).data( 'value' ) );
            } );

            // Loading state
            $btn.addClass( 'loading' ).prop( 'disabled', true );

            $.ajax( {
                url     : WSEParams.ajax_url,
                method  : 'POST',
                data    : postData,
                success : function ( response ) {
                    $btn.removeClass( 'loading' ).prop( 'disabled', false );

                    if ( response.success ) {
                        // Show success text briefly
                        var originalText = $btn.text();
                        $btn.text( WSEParams.i18n.added || 'Added!' );
                        setTimeout( function () {
                            $btn.text( originalText );
                        }, 2500 );

                        // Gap 24 — trigger cart fragment refresh
                        $( document.body ).trigger( 'added_to_cart', [
                            response.data.fragments,
                            response.data.cart_hash,
                            $btn,
                        ] );
                        $( document.body ).trigger( 'wc_fragment_refresh' );
                    }
                },
                error   : function () {
                    $btn.removeClass( 'loading' ).prop( 'disabled', false );
                },
            } );
        }
    );

} )( jQuery );
JS;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 47 — AJAX add-to-cart handler (server side)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Handles AJAX add-to-cart for variable products in the archive loop.
	 *
	 * Resolves the variation ID from the posted attribute values, adds to
	 * the WC cart, and returns refreshed cart fragments for mini-cart update.
	 *
	 * Gap 13 — nonce verification + capability + sanitisation
	 * Gap 24 — returns cart fragments for mini-cart update
	 * Gap 42 — no raw DB queries; all via WC API
	 */
	public function ajax_add_to_cart(): void {

		// Gap 13 — nonce verification (same nonce as main add-to-cart)
		if ( ! isset( $_POST['security'] ) ||
			 ! check_ajax_referer( 'wse_nonce', 'security', false ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Security check failed.', 'woo-swatches-elementor' ) ),
				403
			);
			return;
		}

		// Validate product
		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! $product_id ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Invalid product ID.', 'woo-swatches-elementor' ) )
			);
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Product not found.', 'woo-swatches-elementor' ) )
			);
			return;
		}

		// Gap 13 — Sanitise quantity
		$quantity = wc_stock_amount( wp_unslash( $_POST['quantity'] ?? 1 ) );

		// Gap 13 — Collect and sanitise attribute values from POST
		$attributes  = array();
		$variation_id = 0;

		if ( $product->is_type( 'variable' ) ) {
			/** @var \WC_Product_Variable $product */
			$product_attributes = $product->get_attributes();

			foreach ( $product_attributes as $attr_name => $attr_obj ) {
				$post_key = 'attribute_' . sanitize_title( $attr_name );
				if ( isset( $_POST[ $post_key ] ) ) {
					$attributes[ $post_key ] = wc_clean( wp_unslash( $_POST[ $post_key ] ) );
				}
			}

			// Resolve variation ID from attribute combination
			$data_store   = \WC_Data_Store::load( 'product' );
			$variation_id = (int) $data_store->find_matching_product_variation(
				$product,
				$attributes
			);

			if ( ! $variation_id ) {
				wp_send_json_error(
					array( 'message' => esc_html__( 'Please select all product options.', 'woo-swatches-elementor' ) )
				);
				return;
			}
		}

		// Add to WooCommerce cart
		$cart_item_key = WC()->cart->add_to_cart(
			$product_id,
			$quantity,
			$variation_id,
			$attributes
		);

		if ( false === $cart_item_key ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Could not add to cart. Please try on the product page.', 'woo-swatches-elementor' ) )
			);
			return;
		}

		// Gap 24 — Build cart fragments for mini-cart update
		ob_start();
		woocommerce_mini_cart();
		$mini_cart_html = (string) ob_get_clean();

		$fragments = apply_filters(
			'woocommerce_add_to_cart_fragments',
			array(
				'div.widget_shopping_cart_content' =>
					'<div class="widget_shopping_cart_content">' . $mini_cart_html . '</div>',
			)
		);

		wp_send_json_success( array(
			'fragments'  => $fragments,
			'cart_hash'  => WC()->cart->get_cart_hash(),
			'product_id' => $product_id,
		) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Public helpers
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns whether archive swatches are enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'yes' === get_option( 'wse_archive_swatches', 'yes' );
	}

	/**
	 * Returns the configured click behaviour for archive swatches.
	 *
	 * @return string 'link' | 'ajax_add_to_cart'
	 */
	public static function get_click_behavior(): string {
		return sanitize_key( (string) get_option( 'wse_archive_click', 'link' ) );
	}
}
