<?php
/**
 * Add to Cart template — Variable product (PRESENTER mode — v1.1.0).
 *
 * Used by Widget 2 instances that operate as a presenter (sticky bar /
 * quick-view secondary instance). Renders ONLY the user-facing controls —
 * a quantity input and an Add to Cart button — both attached to the
 * primary canonical form via the HTML5 form= attribute.
 *
 *   <input type="number" class="wse-presenter-qty"
 *          form="wse-form-{P}" name="quantity">
 *   <button type="submit" form="wse-form-{P}" class="wse-atc-button">
 *
 * The button submits the canonical form. Quantity is synced bidirectionally
 * between this input and the canonical form's quantity by add-to-cart.js.
 *
 * No <form> wrapper is emitted here — the canonical form (rendered by
 * variable.php elsewhere on the page) is the single source of truth for
 * variations and form submission.
 *
 * Available variables:
 *   @var \WC_Product_Variable  $product   Variable product.
 *   @var array                 $settings  Elementor widget settings.
 *   @var string                $form_id   Canonical form ID to target.
 *
 * @package WooSwatchesElementor
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

$product_id   = $product->get_id();
$show_qty     = ( $settings['show_quantity'] ?? 'yes' ) === 'yes';
$default_qty  = absint( $settings['default_quantity'] ?? 1 );
$button_text  = ! empty( $settings['button_text'] )
	? esc_html( $settings['button_text'] )
	: esc_html( $product->single_add_to_cart_text() );
?>

<div class="wse-presenter-row wse-qty-atc-row" data-product-id="<?php echo absint( $product_id ); ?>">

	<?php if ( $show_qty ) : ?>
	<div class="wse-qty-wrap">
		<?php
		/**
		 * Presenter quantity input — name="quantity" but no form= attribute.
		 *
		 * If we set form="wse-form-{P}" both the canonical form's quantity
		 * input AND this input would submit name=quantity, with the last in
		 * DOM order winning. To avoid the conflict, this input is JS-synced
		 * to the canonical form's quantity by add-to-cart.js, and the
		 * canonical's value is what gets submitted.
		 */
		?>
		<input type="number"
		       class="input-text qty text wse-presenter-qty"
		       value="<?php echo esc_attr( (string) $default_qty ); ?>"
		       min="<?php echo esc_attr( (string) apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ) ); ?>"
		       step="<?php echo esc_attr( (string) apply_filters( 'woocommerce_quantity_input_step', 1, $product ) ); ?>"
		       inputmode="numeric"
		       aria-label="<?php esc_attr_e( 'Quantity', 'woo-swatches-elementor' ); ?>"
		       data-product-id="<?php echo absint( $product_id ); ?>"
		       data-target-form="<?php echo esc_attr( $form_id ); ?>" />
	</div>
	<?php endif; ?>

	<button type="submit"
	        form="<?php echo esc_attr( $form_id ); ?>"
	        class="single_add_to_cart_button button alt wse-atc-button wse-presenter-btn"
	        aria-label="<?php echo esc_attr( $button_text ); ?>"
	        data-product-id="<?php echo absint( $product_id ); ?>"
	        data-target-form="<?php echo esc_attr( $form_id ); ?>">
		<?php echo $button_text; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</button>

</div>

<?php
/**
 * v1.4.5 — Buy Now button (presenter mode).
 */
$_show_buy_now = ( $settings['show_buy_now'] ?? 'no' ) === 'yes';
if ( $_show_buy_now ) :
	$_buy_now_text = ! empty( $settings['buy_now_text'] )
		? esc_html( $settings['buy_now_text'] )
		: esc_html__( 'Buy Now', 'woo-swatches-elementor' );
	$_buy_now_fw   = ( $settings['buy_now_full_width'] ?? 'yes' ) === 'yes';
	$_buy_now_cls  = 'wse-buy-now-btn button';
	if ( $_buy_now_fw ) {
		$_buy_now_cls .= ' wse-buy-now-full-width';
	}
?>
<div class="wse-buy-now-wrap">
	<button type="button"
		class="<?php echo esc_attr( $_buy_now_cls ); ?>"
		data-product-id="<?php echo absint( $product_id ); ?>"
		data-product-type="variable"
		data-quantity="1"
		data-default-text="<?php echo esc_attr( $_buy_now_text ); ?>"
		disabled="disabled"
		aria-disabled="true"
		data-needs-options="1">
		<span class="wse-buy-now-text"><?php echo $_buy_now_text; // phpcs:ignore ?></span>
	</button>
	<span class="wse-buy-now-message" role="status" aria-live="polite"></span>
</div>
<?php endif; ?>
