<?php
/**
 * Add to Cart template — External / Affiliate product.
 *
 * Renders a styled link button pointing to the external product URL.
 * No quantity field, no cart submission — the user is taken directly
 * to the external site. This matches WooCommerce's own behaviour for
 * external product types.
 *
 * Available variables:
 *   @var \WC_Product_External  $product   The external product object.
 *   @var array                 $settings  Elementor widget settings from Widget 2.
 *
 * Gap 27 — All standard WC action hooks present.
 * Gap 37 — External product type routing from Widget 2.
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

$external_url = $product->get_product_url();
$button_text  = ! empty( $settings['button_text'] )
	? esc_html( $settings['button_text'] )
	: esc_html( $product->single_add_to_cart_text() ?: __( 'Buy product', 'woo-swatches-elementor' ) );

// Bail gracefully if no URL is set
if ( empty( $external_url ) ) {
	return;
}
?>

<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<div class="wse-external-product">

	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<a href="<?php echo esc_url( $external_url ); ?>"
	   class="single_add_to_cart_button button alt wse-atc-button wse-external-btn"
	   target="_blank"
	   rel="noopener noreferrer"
	   aria-label="<?php
		echo esc_attr(
			sprintf(
				/* translators: %s: product name */
				__( 'Buy %s — opens in new tab', 'woo-swatches-elementor' ),
				$product->get_name()
			)
		);
		?>">
		<?php echo $button_text; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</a>

	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

</div><!-- .wse-external-product -->

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
