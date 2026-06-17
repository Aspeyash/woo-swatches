<?php
/**
 * Add to Cart template — Simple product.
 *
 * Renders a quantity field and add-to-cart button for a simple product.
 * All standard WooCommerce action hooks fire so bundle plugins,
 * subscription plugins, and gift-wrap plugins can inject their content.
 *
 * Available variables (extracted by WSE_Swatch_Renderer::include_template):
 *   @var \WC_Product  $product  The simple product object.
 *   @var array        $settings Elementor widget settings from Widget 2.
 *
 * Gap 27 — All standard WC action hooks present.
 * Gap 37 — Simple product type routing from Widget 2.
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

$product_id   = $product->get_id();
$show_qty     = ( $settings['show_quantity'] ?? 'yes' ) === 'yes';
$default_qty  = absint( $settings['default_quantity'] ?? 1 );
$button_text  = ! empty( $settings['button_text'] )
	? esc_html( $settings['button_text'] )
	: esc_html( $product->single_add_to_cart_text() );
?>

<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<form class="cart wse-cart-form wse-simple-cart"
	  action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
	  method="post"
	  enctype="multipart/form-data">

	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<div class="wse-qty-atc-row">

		<?php if ( $show_qty ) : ?>
		<div class="wse-qty-wrap">
			<?php do_action( 'woocommerce_before_add_to_cart_quantity' ); ?>

			<?php
			woocommerce_quantity_input(
				array(
					'min_value'   => apply_filters(
						'woocommerce_quantity_input_min',
						$product->get_min_purchase_quantity(),
						$product
					),
					'max_value'   => apply_filters(
						'woocommerce_quantity_input_max',
						$product->get_max_purchase_quantity(),
						$product
					),
					'input_value' => $default_qty,
					'step'        => apply_filters( 'woocommerce_quantity_input_step', 1, $product ),
				),
				$product
			);
			?>

			<?php do_action( 'woocommerce_after_add_to_cart_quantity' ); ?>
		</div>
		<?php endif; ?>

		<button type="submit"
		        name="add-to-cart"
		        value="<?php echo absint( $product_id ); ?>"
		        class="single_add_to_cart_button button alt wse-atc-button">
			<?php echo $button_text; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</button>

	</div><!-- .wse-qty-atc-row -->

	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

</form><!-- .wse-simple-cart -->

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
