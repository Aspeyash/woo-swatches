<?php
/**
 * Price part — regular ("was") price, struck through.
 *
 * Renders the regular price as a strikethrough subscript next to the
 * current price. Per ZYMARG product spec, this only appears when the
 * product (or its lowest-priced variation) is on sale.
 *
 * Layout is controlled by the regular_price_position setting on the
 * widget:
 *   subscript — inline next to current, smaller, vertical-align: sub
 *   beside    — inline next to current, same baseline
 *   below     — block-level on the line below
 *   hide      — not rendered (this template not called)
 *
 * Available variables:
 *   @var string $regular_html  Pre-formatted regular price (e.g. "1,500৳").
 *   @var string $position      One of: subscript | beside | below.
 *
 * @package WooSwatchesElementor
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

$position_class = 'zymarg-price-was--' . sanitize_html_class( $position );
?>
<del class="zymarg-price-was <?php echo esc_attr( $position_class ); ?>">
	<?php if ( 'subscript' === $position ) : ?>
		<sub><?php echo esc_html( $regular_html ); ?></sub>
	<?php else : ?>
		<?php echo esc_html( $regular_html ); ?>
	<?php endif; ?>
</del>
