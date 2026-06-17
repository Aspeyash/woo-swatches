<?php
/**
 * Shop / archive loop swatch display (v1.1.0).
 *
 * Renders variation swatches below the product title on all standard
 * WooCommerce shop, category, tag, and custom taxonomy archive pages.
 *
 * v1.1.0 changes:
 *   • B1  — Fixed the "Enable on Archive Pages" precedence bug. The check
 *           `! 'yes' === get_option(...)` always evaluated to false; now
 *           uses `'yes' !== get_option(...)` so the toggle actually works.
 *   • B2  — Added render_for_product() public method so WC Blocks
 *           integration (class-blocks-compat.php) actually renders.
 *   • B6  — Multi-attribute variable products on archive pages now
 *           gracefully fall back from "AJAX Add to Cart" to "Go to product
 *           page" mode so shoppers aren't left clicking a button that
 *           returns "Please select all options".
 *   • B7  — wse_archive_max is enforced. Swatches over the limit collapse
 *           into a "+N more" link that navigates to the product page.
 *   • B13 — Hook is registered conditionally so the action callback no
 *           longer fires on every shop loop item when archive swatches
 *           are disabled or the page isn't a WC archive.
 *   • B19 — Archive AJAX add-to-cart only binds when the click behaviour
 *           is set to ajax_add_to_cart, eliminating dead-code event
 *           listeners on link-mode shops.
 *   • B23 — AJAX endpoint moved off admin-ajax.php to wc-ajax (the wc-ajax
 *           endpoint is already excluded from page caching by every major
 *           cache plugin including LiteSpeed).
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Archive_Swatches {

	protected static ?WSE_Archive_Swatches $instance = null;

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

		// B13 — Conditional hook registration.
		// Only attach the loop callback when the toggle is on. Saves the
		// callback firing for every product card on every shop request
		// when the feature is disabled.
		if ( self::is_enabled() ) {
			add_action(
				'woocommerce_after_shop_loop_item_title',
				array( $this, 'render_archive_swatches' ),
				15
			);
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_archive_scripts' ), 20 );

		// B23 — AJAX add-to-cart via wc-ajax (cache-friendly).
		// wc-ajax registers the action on woocommerce_ajax_{action} (logged-in)
		// and woocommerce_ajax_{action}_nopriv (guests).
		add_action( 'wc_ajax_wse_archive_add_to_cart',        array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wc_ajax_nopriv_wse_archive_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Archive swatch renderer (loop hook callback)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Renders swatches for the current product in the shop loop.
	 * Hooked to woocommerce_after_shop_loop_item_title at priority 15.
	 */
	public function render_archive_swatches(): void {

		// B1 — Precedence-correct toggle check.
		if ( 'yes' !== get_option( 'wse_archive_swatches', 'yes' ) ) {
			return;
		}

		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$this->render_for_product( $product );
	}

	/**
	 * v1.1.0 (B2) — Public render method for programmatic invocation.
	 *
	 * Used by the WC Blocks integration (WSE_Blocks_Compat) to render
	 * swatches inside All Products / Product Collection blocks. Mirrors
	 * the loop callback's logic but takes the product as a parameter.
	 *
	 * @param \WC_Product $product Variable product to render swatches for.
	 */
	public function render_for_product( \WC_Product $product ): void {

		// Same toggle gate so blocks honour it too.
		if ( 'yes' !== get_option( 'wse_archive_swatches', 'yes' ) ) {
			return;
		}

		if ( ! $product->is_type( 'variable' ) ) {
			return;
		}

		$attributes = $product->get_variation_attributes();
		if ( empty( $attributes ) ) {
			return;
		}

		// ── Attribute selection ───────────────────────────────────────────
		$attrs_to_render = $this->get_archive_attributes( $attributes, $product );
		if ( empty( $attrs_to_render ) ) {
			return;
		}

		$product_id     = $product->get_id();
		$product_url    = get_permalink( $product_id );
		$click_behavior = sanitize_key( get_option( 'wse_archive_click', 'link' ) );
		$max_swatches   = absint( get_option( 'wse_archive_max', 5 ) );

		// B6 — Multi-attribute variable products cannot be added to cart
		// from the archive with a single attribute click; fall back to link
		// mode so shoppers aren't left clicking a button that returns
		// "Please select all options".
		if ( 'ajax_add_to_cart' === $click_behavior && count( $attributes ) > 1 ) {
			$click_behavior = 'link';
		}

		// ── Wrapper div ────────────────────────────────────────────────────
		printf(
			'<div class="wse-archive-swatches" data-product-id="%s" data-product-url="%s" data-click-behavior="%s" data-max-swatches="%s">',
			esc_attr( (string) $product_id ),
			esc_url( $product_url ),
			esc_attr( $click_behavior ),
			esc_attr( (string) $max_swatches )
		);

		// ── Render swatches per attribute (B7 — apply max + overflow) ────
		foreach ( $attrs_to_render as $attribute_name => $options ) {

			$total       = count( $options );
			$has_overflow = $max_swatches > 0 && $total > $max_swatches;
			$visible_opts = $has_overflow ? array_slice( $options, 0, $max_swatches ) : $options;

			wc_dropdown_variation_attribute_options(
				array(
					'options'          => $visible_opts,
					'attribute'        => $attribute_name,
					'product'          => $product,
					'selected'         => '',
					'show_option_none' => false,
				)
			);

			if ( $has_overflow ) {
				$overflow_count = $total - count( $visible_opts );
				printf(
					'<a href="%1$s" class="wse-archive-overflow" aria-label="%3$s" rel="nofollow">+%2$d</a>',
					esc_url( $product_url ),
					(int) $overflow_count,
					esc_attr( sprintf(
						/* translators: %d: number of additional swatches not shown */
						_n( '%d more option — view product', '%d more options — view product', $overflow_count, 'woo-swatches-elementor' ),
						$overflow_count
					) )
				);
			}
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
	// Attribute selection logic (Gap 45)
	// ─────────────────────────────────────────────────────────────────────

	private function get_archive_attributes(
		array $attributes,
		\WC_Product $product
	): array {

		$configured = sanitize_key( (string) get_option( 'wse_archive_attribute', '' ) );

		if ( $configured && isset( $attributes[ $configured ] ) ) {
			return array( $configured => $attributes[ $configured ] );
		}

		$priority_order = array( 'image', 'color', 'label', 'button' );

		foreach ( $priority_order as $preferred_type ) {
			foreach ( $attributes as $attr_name => $options ) {
				$type = WSE_Attribute_Types::get_attribute_type( $attr_name );
				if ( $type === $preferred_type ) {
					return array( $attr_name => $options );
				}
			}
		}

		$first_key = array_key_first( $attributes );
		return array( $first_key => $attributes[ $first_key ] );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Archive scripts (inline JS appended to wse-swatches)
	// ─────────────────────────────────────────────────────────────────────

	public function enqueue_archive_scripts(): void {

		if ( ! is_shop() && ! is_product_category()
			&& ! is_product_tag() && ! is_product_taxonomy() ) {
			return;
		}

		if ( ! wp_script_is( 'wse-swatches', 'enqueued' ) ) {
			return;
		}

		// B19 — pass click behaviour to JS so it can skip dead-code paths.
		wp_localize_script(
			'wse-swatches',
			'WSEArchive',
			array(
				'click_behavior' => sanitize_key( get_option( 'wse_archive_click', 'link' ) ),
				// B23 — wc-ajax endpoint URL for archive AJAX add-to-cart.
				'wc_ajax_url'    => esc_url( WC_AJAX::get_endpoint( 'wse_archive_add_to_cart' ) ),
			)
		);

		wp_add_inline_script( 'wse-swatches', $this->get_archive_inline_js() );
	}

	private function get_archive_inline_js(): string {
		return <<<'JS'
/* WooSwatches for Elementor — Archive Swatches (v1.1.0) */
( function ( $ ) {
    'use strict';

    var clickBehavior = ( window.WSEArchive && WSEArchive.click_behavior )
        ? WSEArchive.click_behavior
        : 'link';

    // ── Swatch click in archive loop ──────────────────────────────────
    $( document )
        .off( 'click.wse-archive', '.wse-archive-swatches .wse-swatch:not(.disabled)' )
        .on(  'click.wse-archive', '.wse-archive-swatches .wse-swatch:not(.disabled)',
        function ( e ) {
            var $swatch    = $( this );
            var attribute  = $swatch.data( 'attribute' );
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
                e.preventDefault();
                $container.find( '.wse-archive-atc' ).show();
            }
        }
    );

    // ── Archive AJAX add-to-cart — only bind when feature is in use (B19) ──
    if ( clickBehavior === 'ajax_add_to_cart' ) {

        $( document )
            .off( 'click.wse-archive', '.wse-archive-atc' )
            .on(  'click.wse-archive', '.wse-archive-atc',
            function ( e ) {
                e.preventDefault();

                if ( typeof WSEParams === 'undefined' || typeof WSEArchive === 'undefined' ) {
                    return;
                }

                var $btn       = $( this );
                var $container = $btn.closest( '.wse-archive-swatches' );
                var productId  = String( $container.data( 'product-id' ) || '' );

                if ( ! productId ) {
                    return;
                }

                var postData = {
                    security   : WSEParams.nonce,
                    product_id : productId,
                    quantity   : 1,
                };

                $container.find( '.wse-swatch.selected' ).each( function () {
                    postData[ 'attribute_' + $( this ).data( 'attribute' ) ] =
                        String( $( this ).data( 'value' ) );
                } );

                $btn.addClass( 'loading' ).prop( 'disabled', true );

                $.ajax( {
                    url     : WSEArchive.wc_ajax_url,
                    method  : 'POST',
                    data    : postData,
                    success : function ( response ) {
                        $btn.removeClass( 'loading' ).prop( 'disabled', false );

                        if ( response && response.success ) {
                            var originalText = $btn.text();
                            $btn.text( WSEParams.i18n.added || 'Added!' );
                            setTimeout( function () {
                                $btn.text( originalText );
                            }, 2500 );

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
    }

} )( jQuery );
JS;
	}

	// ─────────────────────────────────────────────────────────────────────
	// AJAX add-to-cart handler (server side)
	// ─────────────────────────────────────────────────────────────────────

	public function ajax_add_to_cart(): void {

		if ( ! isset( $_POST['security'] ) ||
			 ! check_ajax_referer( 'wse_nonce', 'security', false ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Security check failed.', 'woo-swatches-elementor' ) ),
				403
			);
			return;
		}

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

		$quantity = wc_stock_amount( wp_unslash( $_POST['quantity'] ?? 1 ) );

		$attributes   = array();
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

	public static function is_enabled(): bool {
		return 'yes' === get_option( 'wse_archive_swatches', 'yes' );
	}

	public static function get_click_behavior(): string {
		return sanitize_key( (string) get_option( 'wse_archive_click', 'link' ) );
	}
}
