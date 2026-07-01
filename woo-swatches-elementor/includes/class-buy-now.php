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
 *      * If on ANY OTHER front-end page -> treat as abandonment: restore
 *        the original cart WITHOUT the Buy Now product and clear flags.
 *        (v1.7.3: changed from merge-back to discard — on a multivendor
 *        marketplace, merging the Buy Now product into the cart on abandon
 *        risks accidental purchases during subsequent normal checkout.)
 *
 *  - On `woocommerce_thankyou` (after order is placed): restore the
 *    customer's original cart and clear the Buy Now session.
 *
 *  - v1.7.5: 15-minute session TTL. Every Buy Now click stamps
 *    `wse_bnw_expires_at` = time() + 900. If the customer navigates back
 *    after 15+ minutes, the session is auto-expired: original cart is
 *    restored and Buy Now state is cleared. Matches WC Product Grid's
 *    Buy Now TTL for consistent behaviour across the ZYMARG stack.
 *
 * @package WooSwatchesElementor
 * @since   1.4.5
 */

defined( 'ABSPATH' ) || exit;

class WSE_Buy_Now {

	/**
	 * How long (in seconds) a Buy Now session stays valid before being
	 * treated as abandoned and auto-cleaned. Matches WC Product Grid's
	 * Buy Now TTL for consistent behaviour across the ZYMARG stack.
	 *
	 * @since 1.7.5
	 */
	const EXPIRY_SECONDS = 900;

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

		// v1.7.4: Cross-plugin Buy Now lock — refuse to start if the WC Product
		// Grid Buy Now flow is already mid-flight for this customer. Two
		// simultaneous Buy Now state machines operating on the same shared WC
		// cart would corrupt each other's snapshots.
		if ( WC()->session->get( 'zymarg_wcpg_buy_now_backup' ) || WC()->session->get( 'zymarg_buy_now_token' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Another Buy Now checkout is already in progress. Please complete or cancel it first.', 'woo-swatches-elementor' ),
			) );
		}

		// v1.7.5: Check if an existing Buy Now session has already expired.
		// If so, clean it up and start fresh instead of piggybacking on stale state.
		$this->maybe_expire_stale_session();

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

		// v1.7.5: Stamp the session with an expiry timestamp so an abandoned
		// Buy Now flow can be cleaned up automatically after 15 minutes.
		// Matches WC Product Grid's Buy Now TTL for consistency across the
		// ZYMARG stack.
		WC()->session->set( 'wse_bnw_expires_at', time() + self::EXPIRY_SECONDS );

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

		// v1.7.5: Check for expired session BEFORE anything else. If the
		// customer opened the checkout tab 20 minutes ago and forgot about it,
		// we want to restore their cart and let them start fresh — not force
		// them to complete a Buy Now they've clearly abandoned.
		if ( $this->is_session_expired() ) {
			$this->restore_original_cart();
			$this->clear_session();
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

		// Anything else = abandonment. Discard the Buy Now product and restore
		// the original cart exactly as it was before Buy Now was clicked.
		// v1.7.3: Previously this called restore_combined_cart() which merged
		// the Buy Now product back into the cart. On a multivendor marketplace
		// this caused accidental purchases — customer forgets the item is in
		// cart and later checks out with everything bundled together.
		$this->restore_original_cart();
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
			$this->restore_original_cart();
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

	/**
	 * Restore original cart + add the Buy Now product back into it.
	 *
	 * @deprecated 1.7.3 No longer called internally. Use restore_original_cart()
	 *             instead. On a multivendor marketplace, merging the Buy Now product
	 *             on abandon risked accidental purchases during subsequent checkouts.
	 * @return void
	 */
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
		WC()->session->set( 'wse_bnw_expires_at', null );
	}

	/* =========================================================================
	 *  SESSION EXPIRY (v1.7.5)
	 * ====================================================================== */

	/**
	 * Whether the current Buy Now session has passed its expiry timestamp.
	 *
	 * Returns false if no expiry is set (backwards-compatible with sessions
	 * created before v1.7.5 — those simply behave as if they never expire,
	 * which matches the pre-v1.7.5 behaviour so no existing customer sessions
	 * are disrupted at upgrade time).
	 *
	 * @since 1.7.5
	 * @return bool
	 */
	private function is_session_expired(): bool {
		if ( ! WC()->session ) {
			return false;
		}
		$expires_at = absint( WC()->session->get( 'wse_bnw_expires_at' ) );
		if ( $expires_at <= 0 ) {
			// No expiry stamp = legacy session from pre-v1.7.5. Treat as
			// still valid so we don't yank the rug out from under a customer
			// mid-checkout right after upgrading the plugin.
			return false;
		}
		return time() >= $expires_at;
	}

	/**
	 * Called at the start of a fresh Buy Now click. If a stale expired
	 * session is hanging around, wipe it before we snapshot the current cart
	 * — otherwise the "already_active" idempotency check would piggyback on
	 * stale state and never re-snapshot the cart.
	 *
	 * @since 1.7.5
	 * @return void
	 */
	private function maybe_expire_stale_session(): void {
		if ( ! WC()->session ) {
			return;
		}
		if ( ! WC()->session->get( 'wse_bnw_active' ) ) {
			return;
		}
		if ( ! $this->is_session_expired() ) {
			return;
		}
		// Session was left open past its TTL. Restore original cart to WC
		// state (so subsequent snapshot captures the real cart, not the
		// swapped one) and clear all Buy Now session keys.
		$this->restore_original_cart();
		$this->clear_session();
	}
}
