<?php
/**
 * Add to Cart template — Grouped product.
 *
 * Renders a table of child products, each with its own quantity input.
 * Matches WooCommerce's standard grouped product form structure so
 * existing themes and plugins that style grouped products work correctly.
 *
 * Available variables:
 *   @var \WC_Product_Grouped  $product   The grouped product object.
 *   @var array                $settings  Elementor widget settings from Widget 2.
 *
 * Gap 27 — All standard WC action hooks present.
 * Gap 37 — Grouped product type routing from Widget 2.
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

// Fetch and filter grouped child products
$grouped_products = array_filter(
	array_map( 'wc_get_product', $product->get_children() ),
	'wc_products_array_filter_visible_grouped'
);

if ( empty( $grouped_products ) ) {
	return;
}

$button_text = ! empty( $settings['button_text'] )
	? esc_html( $settings['button_text'] )
	: esc_html( $product->single_add_to_cart_text() );
?>

<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<form class="cart wse-cart-form wse-grouped-cart"
	  action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
	  method="post"
	  enctype="multipart/form-data">

	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<table class="woocommerce-grouped-product-list group_table">
		<tbody>
			<?php foreach ( $grouped_products as $grouped_product ) :

				$grouped_id  = $grouped_product->get_id();
				$purchasable = $grouped_product->is_purchasable();
				$in_stock    = $grouped_product->is_in_stock();
				$row_class   = $in_stock ? 'wse-grouped-row--in-stock' : 'wse-grouped-row--out-of-stock';

				// Get posted quantity (preserves user input on validation error)
				$posted_qty = isset( $_POST['quantity'][ $grouped_id ] ) // phpcs:ignore WordPress.Security.NonceVerification
					? absint( wp_unslash( $_POST['quantity'][ $grouped_id ] ) ) // phpcs:ignore
					: '';
			?>
			<tr class="woocommerce-grouped-product-list-item wse-grouped-row <?php echo esc_attr( $row_class ); ?>"
			    id="wse-grouped-product-<?php echo absint( $grouped_id ); ?>">

				<td class="woocommerce-grouped-product-list-item__quantity">
					<?php if ( $purchasable && $in_stock ) : ?>
					<?php
					woocommerce_quantity_input(
						array(
							'input_name'  => 'quantity[' . $grouped_id . ']',
							'input_value' => $posted_qty,
							'min_value'   => '0',
							'max_value'   => $grouped_product->backorders_allowed()
								? ''
								: $grouped_product->get_stock_quantity(),
							'placeholder' => '0',
							'inputmode'   => 'numeric',
						),
						$grouped_product
					);
					?>
					<?php else : ?>
					<span class="wse-grouped-oos">
						<?php esc_html_e( 'Out of stock', 'woo-swatches-elementor' ); ?>
					</span>
					<?php endif; ?>
				</td>

				<td class="woocommerce-grouped-product-list-item__label">
					<label for="quantity_<?php echo absint( $grouped_id ); ?>">
						<a href="<?php echo esc_url( get_permalink( $grouped_id ) ); ?>">
							<?php echo esc_html( $grouped_product->get_name() ); ?>
						</a>
					</label>
				</td>

				<td class="woocommerce-grouped-product-list-item__price">
					<?php echo wp_kses_post( $grouped_product->get_price_html() ); ?>
				</td>

			</tr>
			<?php endforeach; ?>
		</tbody>
	</table><!-- .group_table -->

	<div class="wse-qty-atc-row wse-grouped-submit">
		<button type="submit"
		        name="add-to-cart"
		        value="<?php echo absint( $product->get_id() ); ?>"
		        class="single_add_to_cart_button button alt wse-atc-button">
			<?php echo $button_text; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</button>
	</div>

	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

</form><!-- .wse-grouped-cart -->

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
