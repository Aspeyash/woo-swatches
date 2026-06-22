<?php
/**
 * Buy Now — AJAX handler + isolated checkout session management.
 *
 * Strategy (Buy Now -> Checkout -> Order / Abandonment):
 *
 *  - On Buy Now click (AJAX): save the customer's existing cart to the WC
 *    session under `wse_bnw_saved_cart`, swap the live cart with ONLY the
 *    Buy Now product, set a `wse_bnw_active` session flag.
 *
 *  - On every front-end page load (`template_redirect`, priority 5):
 *      * If on the checkout page -> ensure the cart still contains exactly
 *        the Buy Now product (re-syncs after WC checkout AJAX, reloads).
 *      * If on the order-received endpoint -> do nothing here;
 *        `woocommerce_thankyou` handles cart restoration.
 *      * If on ANY OTHER front-end page -> treat as abandonment: rebuild
 *        the cart as `existing items + Buy Now product` and clear flags.
 *
 *  - On `woocommerce_thankyou` (after order is placed): restore the
 *    customer's original cart and clear the Buy Now session.
 *
 * @package WooSwatchesElementor
 * @since   1.4.5
 */

defined( 'ABSPATH' ) || exit;

class WSE_Buy_Now {

	protected static ?WSE_Buy_Now $instance = null;

	public static function instance(): static {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {
		$this->hooks();
	}

	private function hooks(): void {
		// AJAX handlers (logged-in + guest).
		add_action( 'wp_ajax_wse_buy_now', array( $this, 'ajax_buy_now' ) );
		add_action( 'wp_ajax_nopriv_wse_buy_now', array( $this, 'ajax_buy_now' ) );

		// Front-end navigation dispatcher during Buy Now flow.
		add_action( 'template_redirect', array( $this, 'handle_buy_now_navigation' ), 5 );

		// After successful order, restore the customer's original cart.
		add_action( 'woocommerce_thankyou', array( $this, 'restore_cart_after_order' ), 5 );

		// Inject Buy Now nonce + i18n into WSEParams payload.
		add_filter( 'wse_frontend_params', array( $this, 'inject_frontend_params' ), 10, 1 );
	}

	/**
	 * Adds buy_now sub-key to WSEParams so add-to-cart.js can fire the AJAX.
	 */
	public function inject_frontend_params( array $params ): array {
		$params['buy_now'] = array(
			'nonce' => wp_create_nonce( 'wse_buy_now' ),
			'i18n'  => array(
				'processing'     => esc_html__( 'Processing...', 'woo-swatches-elementor' ),
				'select_options' => esc_html__( 'Please select product options', 'woo-swatches-elementor' ),
				'error'          => esc_html__( 'Something went wrong. Please try again.', 'woo-swatches-elementor' ),
			),
		);
		return $params;
	}

	/* =========================================================================
	 *  AJAX HANDLER
	 * ====================================================================== */

	/**
	 * AJAX: Save existing cart, swap with Buy Now product, return checkout URL.
	 */
	public function ajax_buy_now(): void {
		check_ajax_referer( 'wse_buy_now', 'nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$quantity     = isset( $_POST['quantity'] ) ? min( 1000, max( 1, absint( $_POST['quantity'] ) ) ) : 1;
		$variation    = isset( $_POST['variation'] ) && is_array( $_POST['variation'] )
			? wc_clean( wp_unslash( $_POST['variation'] ) )
			: array();

		// Validate product.
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'woo-swatches-elementor' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'woo-swatches-elementor' ) ) );
		}

		if ( $product->is_type( 'variable' ) && ! $variation_id ) {
			wp_send_json_error( array(
				'message'       => __( 'Please select product options before buying.', 'woo-swatches-elementor' ),
				'needs_options' => true,
			) );
		}

		$target_product = $variation_id ? wc_get_product( $variation_id ) : $product;
		if ( ! $target_product || ! $target_product->is_purchasable() ) {
			wp_send_json_error( array( 'message' => __( 'This product cannot be purchased.', 'woo-swatches-elementor' ) ) );
		}
		if ( ! $target_product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'This product is out of stock.', 'woo-swatches-elementor' ) ) );
		}
		if ( $target_product->managing_stock() ) {
			$stock_qty = $target_product->get_stock_quantity();
			if ( $stock_qty !== null && $quantity > $stock_qty ) {
				wp_send_json_error( array(
					'message' => sprintf(
						/* translators: %d: available stock quantity */
						__( 'Only %d available in stock.', 'woo-swatches-elementor' ),
						$stock_qty
					),
				) );
			}
		}

		// Initialize WC session for guests.
		if ( ! WC()->session ) {
			WC()->initialize_session_handler();
		}
		if ( is_callable( array( WC()->session, 'set_customer_session_cookie' ) ) ) {
			WC()->session->set_customer_session_cookie( true );
		}

		// Save the original cart on the FIRST Buy Now click only.
		$already_active = (bool) WC()->session->get( 'wse_bnw_active' );
		if ( ! $already_active ) {
			$saved_cart = WC()->cart->get_cart_for_session();
			WC()->session->set( 'wse_bnw_saved_cart', $saved_cart );
		}

		// Store Buy Now product details (always overwritten so user can
		// switch products mid-flow).
		WC()->session->set( 'wse_bnw_product', array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'quantity'     => $quantity,
			'variation'    => $variation,
		) );
		WC()->session->set( 'wse_bnw_active', true );

		// Replace cart contents with the Buy Now product.
		WC()->cart->empty_cart( false );
		$added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );

		if ( ! $added ) {
			$this->restore_original_cart();
			$this->clear_session();

			$notices = wc_get_notices( 'error' );
			$msg     = ! empty( $notices )
				? wp_strip_all_tags( $notices[0]['notice'] )
				: __( 'Could not add product. Please try again.', 'woo-swatches-elementor' );
			wc_clear_notices();

			wp_send_json_error( array( 'message' => $msg ) );
		}

		WC()->cart->calculate_totals();

		wp_send_json_success( array(
			'redirect' => wc_get_checkout_url(),
		) );
	}

	/* =========================================================================
	 *  NAVIGATION DISPATCHER
	 * ====================================================================== */

	public function handle_buy_now_navigation(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}

		if ( ! WC()->session->get( 'wse_bnw_active' ) ) {
			return;
		}

		// Order-received: handled by woocommerce_thankyou.
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		// Order-pay: different flow, leave alone.
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		// On checkout page: keep cart synced to Buy Now product.
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$this->ensure_buy_now_cart();
			return;
		}

		// Anything else = abandonment. Restore combined cart.
		$this->restore_combined_cart();
		$this->clear_session();
	}

	/* =========================================================================
	 *  CHECKOUT-PAGE CART SYNC
	 * ====================================================================== */

	private function ensure_buy_now_cart(): void {
		$bnw_product = WC()->session->get( 'wse_bnw_product' );
		if ( empty( $bnw_product ) || empty( $bnw_product['product_id'] ) ) {
			$this->clear_session();
			return;
		}

		if ( $this->cart_matches( $bnw_product ) ) {
			return;
		}

		WC()->cart->empty_cart( false );

		$added = WC()->cart->add_to_cart(
			$bnw_product['product_id'],
			$bnw_product['quantity'],
			$bnw_product['variation_id'],
			$bnw_product['variation']
		);

		if ( ! $added ) {
			$this->restore_combined_cart();
			$this->clear_session();
			wc_add_notice( __( 'The Buy Now product is no longer available. Your original cart has been restored.', 'woo-swatches-elementor' ), 'error' );
			return;
		}

		WC()->cart->calculate_totals();
	}

	private function cart_matches( array $bnw_product ): bool {
		$cart_contents = WC()->cart->get_cart();
		if ( count( $cart_contents ) !== 1 ) {
			return false;
		}
		$item = reset( $cart_contents );
		return (
			(int) $item['product_id'] === (int) $bnw_product['product_id']
			&& (int) ( $item['variation_id'] ?? 0 ) === (int) $bnw_product['variation_id']
			&& (int) $item['quantity'] === (int) $bnw_product['quantity']
		);
	}

	/* =========================================================================
	 *  RESTORE AFTER ORDER
	 * ====================================================================== */

	public function restore_cart_after_order( $order_id ): void {
		if ( ! WC()->session ) {
			return;
		}
		if ( ! WC()->session->get( 'wse_bnw_active' ) ) {
			return;
		}

		$this->restore_original_cart();
		$this->clear_session();
	}

	/* =========================================================================
	 *  CART RESTORE HELPERS
	 * ====================================================================== */

	private function restore_original_cart(): void {
		if ( ! WC()->session || ! WC()->cart ) {
			return;
		}

		$saved_cart = WC()->session->get( 'wse_bnw_saved_cart' );
		WC()->cart->empty_cart( false );

		if ( ! empty( $saved_cart ) && is_array( $saved_cart ) ) {
			WC()->session->set( 'cart', $saved_cart );
			WC()->cart->get_cart_from_session();
			WC()->cart->calculate_totals();
		}
	}

	private function restore_combined_cart(): void {
		if ( ! WC()->session || ! WC()->cart ) {
			return;
		}

		$saved_cart  = WC()->session->get( 'wse_bnw_saved_cart' );
		$bnw_product = WC()->session->get( 'wse_bnw_product' );

		WC()->cart->empty_cart( false );

		if ( ! empty( $saved_cart ) && is_array( $saved_cart ) ) {
			WC()->session->set( 'cart', $saved_cart );
			WC()->cart->get_cart_from_session();
		}

		if ( ! empty( $bnw_product ) && ! empty( $bnw_product['product_id'] ) ) {
			$target = ! empty( $bnw_product['variation_id'] )
				? wc_get_product( $bnw_product['variation_id'] )
				: wc_get_product( $bnw_product['product_id'] );

			if ( $target && $target->is_purchasable() && $target->is_in_stock() ) {
				WC()->cart->add_to_cart(
					(int) $bnw_product['product_id'],
					(int) $bnw_product['quantity'],
					(int) $bnw_product['variation_id'],
					is_array( $bnw_product['variation'] ) ? $bnw_product['variation'] : array()
				);
			}
		}

		WC()->cart->calculate_totals();
	}

	private function clear_session(): void {
		if ( ! WC()->session ) {
			return;
		}
		WC()->session->set( 'wse_bnw_active', null );
		WC()->session->set( 'wse_bnw_product', null );
		WC()->session->set( 'wse_bnw_saved_cart', null );
	}
}
