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
	data-product-type="simple"
	data-heading-state="<?php echo esc_attr( $heading_state ?? 'regular' ); ?>"
	<?php if ( ! empty( $skeleton_show ) ) : ?>data-skeleton-enabled="1"<?php endif; ?>>

	<?php if ( ! empty( $heading_text ) ) : ?>
		<span class="zymarg-price-heading zymarg-price-heading--<?php echo esc_attr( $heading_state ?? 'regular' ); ?>"><?php echo esc_html( $heading_text ); ?></span>
	<?php endif; ?>

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

	<?php
	// v1.2.1 (P1) — "Save X (Y%)" indicator.
	if ( $is_on_sale && ! empty( $savings_show ) && ! empty( $savings_data['amount'] ) ) :
		$_savings_text = (string) ( $savings_format ?? 'amount_percent' );
		$_savings_html = '';
		switch ( $_savings_text ) {
			case 'amount_only':
				$_savings_html = sprintf( '%s %s', esc_html( $savings_prefix ), esc_html( $savings_data['amount_html'] ) );
				break;
			case 'percent_only':
				$_savings_html = sprintf( '%s %d%%', esc_html( $savings_prefix ), (int) $savings_data['percent'] );
				break;
			case 'amount_percent':
			default:
				$_savings_html = sprintf( '%s %s (%d%%)', esc_html( $savings_prefix ), esc_html( $savings_data['amount_html'] ), (int) $savings_data['percent'] );
				break;
		}
		?>
		<span class="zymarg-price-savings"><?php echo $_savings_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	<?php endif; ?>

	<?php
	// v1.2.1 (P5) — Sale badge with position/content variants.
	if ( $is_on_sale && $show_sale_badge ) :
		$_pos     = $badge_position ?? 'inline_after';
		$_content = $badge_content  ?? 'text_only';
		$_text    = $sale_badge_text;

		// Compute content based on the chosen variant.
		if ( 'percent' === $_content && ! empty( $savings_data['percent'] ) ) {
			$_text = sprintf( '-%d%%', (int) $savings_data['percent'] );
		} elseif ( 'amount' === $_content && ! empty( $savings_data['amount_html'] ) ) {
			$_text = sprintf( '%s %s', esc_html__( 'Save', 'woo-swatches-elementor' ), $savings_data['amount_html'] );
		} elseif ( 'percent_text' === $_content && ! empty( $savings_data['percent'] ) ) {
			$_text = sprintf( '%d%% off', (int) $savings_data['percent'] );
		}
		?>
		<span class="zymarg-sale-badge zymarg-sale-badge--<?php echo esc_attr( $_pos ); ?>"><?php echo esc_html( $_text ); ?></span>
	<?php endif; ?>

	<?php if ( ! empty( $shipping_html ) ) : ?>
		<span class="zymarg-price-shipping-hint"><?php echo esc_html( $shipping_html ); ?></span>
	<?php endif; ?>

</div>
