<?php
/**
 * Price template — Simple product (v1.2.0).
 *
 * Renders the price block for a simple WooCommerce product. Sale-aware:
 * if the product is on sale, the sale price is the prominent number and
 * the regular price renders next to it as a strikethrough subscript
 * (or whichever position the user picked in the widget settings).
 *
 * No JS variation sync needed for simple products — the price is stable
 * for the lifetime of the page render.
 *
 * Available variables (extracted by WSE_Widget_Price::render()):
 *   @var \WC_Product       $product           The simple product.
 *   @var array<string,mixed> $price_data      Pre-computed price data.
 *   @var bool              $show_sale_badge   Whether to render the sale badge.
 *   @var string            $sale_badge_text   Sale badge label text.
 *   @var string            $regular_position  Regular-price position (subscript|beside|below|hide).
 *
 * @package WooSwatchesElementor
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

$is_on_sale   = (bool) ( $price_data['has_sale']      ?? false );
$current_html = (string) ( $price_data['current_html'] ?? '' );
$regular_html = (string) ( $price_data['regular_html'] ?? '' );

$wrapper_classes = array( 'zymarg-price', 'zymarg-price--simple' );
if ( $is_on_sale ) {
	$wrapper_classes[] = 'zymarg-price--on-sale';
	$wrapper_classes[] = 'zymarg-price--regular-' . sanitize_html_class( $regular_position );
}
?>
<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"
	data-product-id="<?php echo absint( $product->get_id() ); ?>"
	data-product-type="simple">

	<?php
	WSE_Widget_Price::include_part(
		'price-current.php',
		array( 'current_html' => $current_html )
	);
	?>

	<?php if ( $is_on_sale && 'hide' !== $regular_position ) : ?>
		<?php
		WSE_Widget_Price::include_part(
			'price-was.php',
			array(
				'regular_html' => $regular_html,
				'position'     => $regular_position,
			)
		);
		?>
	<?php endif; ?>

	<?php if ( $is_on_sale && $show_sale_badge ) : ?>
		<span class="zymarg-sale-badge"><?php echo esc_html( $sale_badge_text ); ?></span>
	<?php endif; ?>

</div>
