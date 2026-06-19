<?php
/**
 * Price template — Variable product (v1.2.0).
 *
 * Renders the price block for a variable WooCommerce product. By default
 * shows ONLY the lowest active price (per ZYMARG spec). When the lowest
 * regular price exceeds the lowest active price, the lowest regular is
 * rendered next to it as a strikethrough subscript (option (ii) — see
 * the v1.2.0 changelog).
 *
 * The `data-form-id` attribute attaches this widget to the canonical form
 * Widget 2 creates. price.js subscribes to that form's `found_variation`
 * and `reset_data` events; when the customer picks a variation via Widget
 * 1, this widget re-renders client-side with that specific variation's
 * price + regular.
 *
 * Display style options:
 *   lowest          — Just the lowest price (default).
 *   lowest_with_from — "From {lowest}" prefix.
 *   range            — "{low} – {high}".
 *
 * The Sale badge appears when ANY variation on the product is on sale
 * (per WC_Product_Variable::is_on_sale()), even on the initial baseline
 * view where the lowest-priced variation may not itself be on sale.
 *
 * Initial server-side data is also embedded as data-* attributes so
 * price.js can call reset_data without re-fetching from the server.
 *
 * Available variables (extracted by WSE_Widget_Price::render()):
 *   @var \WC_Product_Variable $product           The variable product.
 *   @var array<string,mixed>  $price_data        Pre-computed price data.
 *   @var bool                 $show_sale_badge   Whether to render the sale badge.
 *   @var string               $sale_badge_text   Sale badge label text.
 *   @var string               $regular_position  Regular-price position.
 *   @var string               $default_style     Display style (lowest|lowest_with_from|range).
 *   @var string               $from_prefix       "From" prefix text.
 *   @var string               $form_id           Canonical form ID (wse-form-{P}).
 *
 * @package WooSwatchesElementor
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

$is_on_sale     = (bool) ( $price_data['has_sale']      ?? false );
$current_html   = (string) ( $price_data['current_html'] ?? '' );
$regular_html   = (string) ( $price_data['regular_html'] ?? '' );
$range_low_html = (string) ( $price_data['range_low_html']  ?? '' );
$range_high_html = (string) ( $price_data['range_high_html'] ?? '' );

$wrapper_classes = array( 'zymarg-price', 'zymarg-price--variable' );
if ( $is_on_sale ) {
	$wrapper_classes[] = 'zymarg-price--on-sale';
	$wrapper_classes[] = 'zymarg-price--regular-' . sanitize_html_class( $regular_position );
}
$wrapper_classes[] = 'zymarg-price--style-' . sanitize_html_class( $default_style );
?>
<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"
	data-product-id="<?php echo absint( $product->get_id() ); ?>"
	data-product-type="variable"
	data-form-id="<?php echo esc_attr( $form_id ); ?>"
	data-initial-current="<?php echo esc_attr( $current_html ); ?>"
	data-initial-regular="<?php echo esc_attr( $regular_html ); ?>"
	data-initial-on-sale="<?php echo $is_on_sale ? '1' : '0'; ?>"
	data-regular-position="<?php echo esc_attr( $regular_position ); ?>"
	data-show-sale-badge="<?php echo $show_sale_badge ? '1' : '0'; ?>"
	data-sale-badge-text="<?php echo esc_attr( $sale_badge_text ); ?>"
	data-heading-state="<?php echo esc_attr( $heading_state ?? 'regular' ); ?>"
	data-savings-show="<?php echo ( ! empty( $savings_show ) ) ? '1' : '0'; ?>"
	data-savings-format="<?php echo esc_attr( $savings_format ?? 'amount_percent' ); ?>"
	data-savings-prefix="<?php echo esc_attr( $savings_prefix ?? __( 'Save', 'woo-swatches-elementor' ) ); ?>"
	data-badge-position="<?php echo esc_attr( $badge_position ?? 'inline_after' ); ?>"
	data-badge-content="<?php echo esc_attr( $badge_content ?? 'text_only' ); ?>"
	<?php if ( ! empty( $skeleton_show ) ) : ?>data-skeleton-enabled="1"<?php endif; ?>>

	<?php if ( ! empty( $heading_text ) ) : ?>
		<span class="zymarg-price-heading zymarg-price-heading--<?php echo esc_attr( $heading_state ?? 'regular' ); ?>"><?php echo esc_html( $heading_text ); ?></span>
	<?php endif; ?>

	<?php if ( 'range' === $default_style ) : ?>
		<span class="zymarg-price-range">
			<?php
			WSE_Widget_Price::include_part(
				'price-current.php',
				array( 'current_html' => $range_low_html )
			);
			?>
			<span class="zymarg-price-sep" aria-hidden="true">&ndash;</span>
			<?php
			WSE_Widget_Price::include_part(
				'price-current.php',
				array( 'current_html' => $range_high_html )
			);
			?>
		</span>

	<?php elseif ( 'lowest_with_from' === $default_style ) : ?>
		<span class="zymarg-price-from"><?php echo esc_html( $from_prefix ); ?></span>
		<?php
		WSE_Widget_Price::include_part(
			'price-current.php',
			array( 'current_html' => $current_html )
		);
		?>

	<?php else : // lowest (default) ?>
		<?php
		WSE_Widget_Price::include_part(
			'price-current.php',
			array( 'current_html' => $current_html )
		);
		?>
	<?php endif; ?>

	<?php
	// Lowest-regular subscript only on the lowest / lowest_with_from styles.
	// On the range style, both ends are already rendered above so a separate
	// "was" makes no semantic sense — the strikethrough range would render
	// instead via the woocommerce_variable_price_html filter chain (handled
	// per-variation when one is selected).
	if (
		$is_on_sale
		&& 'hide' !== $regular_position
		&& in_array( $default_style, array( 'lowest', 'lowest_with_from' ), true )
		&& '' !== $regular_html
	) :
		?>
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

	<?php
	// v1.2.1 (P1) — Lowest savings indicator (only shown on sale).
	if ( $is_on_sale && ! empty( $savings_show ) && (float) $price_data['regular'] > (float) $price_data['current'] ) :
		$_save_amt = (float) ( $price_data['regular'] - $price_data['current'] );
		$_save_pct = (float) $price_data['regular'] > 0 ? (int) round( ( $_save_amt / (float) $price_data['regular'] ) * 100 ) : 0;
		$_save_amt_html = WSE_Widget_Price::format_price( $_save_amt );
		$_savings_format = $savings_format ?? 'amount_percent';
		$_savings_html = '';
		switch ( $_savings_format ) {
			case 'amount_only':
				$_savings_html = sprintf( '%s %s', esc_html( $savings_prefix ?? '' ), esc_html( $_save_amt_html ) );
				break;
			case 'percent_only':
				$_savings_html = sprintf( '%s %d%%', esc_html( $savings_prefix ?? '' ), (int) $_save_pct );
				break;
			case 'amount_percent':
			default:
				$_savings_html = sprintf( '%s %s (%d%%)', esc_html( $savings_prefix ?? '' ), esc_html( $_save_amt_html ), (int) $_save_pct );
				break;
		}
		?>
		<span class="zymarg-price-savings"><?php echo $_savings_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	<?php endif; ?>

	<?php if ( ! empty( $shipping_html ) ) : ?>
		<span class="zymarg-price-shipping-hint"><?php echo esc_html( $shipping_html ); ?></span>
	<?php endif; ?>

</div>
