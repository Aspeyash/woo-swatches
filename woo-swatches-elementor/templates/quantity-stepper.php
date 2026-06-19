<?php
/**
 * Shared quantity stepper template (v1.2.0).
 *
 * Replaces woocommerce_quantity_input() in Widget 2's add-to-cart
 * templates with a [-] [qty] [+] stepper for a more touch-friendly UX
 * and full Elementor styling control. The middle <input> stays exactly
 * where WooCommerce expects it (same name, same .qty class, same
 * .input-text class) so wc-add-to-cart-variation.js continues to read
 * and write the value without modification.
 *
 * The wrapping <div> carries BOTH a `.wse-qty-stepper` class (for our
 * own styling and JS bindings) and a `.quantity` class (for theme and
 * plugin compatibility — many themes target `.quantity` for spacing).
 *
 * The Elementor icon picker output flows through Icons_Manager so users
 * can pick any icon from the Elementor library or upload SVG. If the
 * widget is rendered outside an Elementor context (defensive fallback)
 * we ship a minimal hand-drawn SVG so the buttons always have visible
 * glyphs.
 *
 * Available variables (provided by the parent template's local scope):
 *   @var \WC_Product       $product   The product the stepper is for.
 *   @var array<string,mixed> $settings Elementor widget settings.
 *   @var array<string,mixed> $args     {
 *       'min'           => string,  Minimum allowed value (numeric).
 *       'max'           => string,  Maximum (empty = unlimited).
 *       'step'          => string,  Increment step.
 *       'value'         => string,  Initial value.
 *       'input_name'    => string,  HTML name attribute.
 *       'input_id'      => string,  HTML id attribute (optional).
 *       'input_classes' => string[],
 *   }
 *
 * @package WooSwatchesElementor
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

$min           = (string) ( $args['min']           ?? 0 );
$max           = (string) ( $args['max']           ?? '' );
$step          = (string) ( $args['step']          ?? 1 );
$value         = (string) ( $args['value']         ?? '1' );
$input_name    = (string) ( $args['input_name']    ?? 'quantity' );
$input_id      = (string) ( $args['input_id']      ?? '' );
$input_classes = (array)  ( $args['input_classes'] ?? array( 'input-text', 'qty', 'text', 'wse-canonical-qty' ) );

$show_buttons   = ( $settings['show_qty_stepper_buttons'] ?? 'yes' ) === 'yes';
$decrease_icon  = $settings['decrease_icon'] ?? array( 'value' => 'eicon-minus', 'library' => 'eicons' );
$increase_icon  = $settings['increase_icon'] ?? array( 'value' => 'eicon-plus',  'library' => 'eicons' );

$wrap_classes = array( 'wse-qty-stepper', 'quantity' );
if ( ! $show_buttons ) {
	$wrap_classes[] = 'wse-qty-stepper--no-buttons';
}
?>
<div class="<?php echo esc_attr( implode( ' ', $wrap_classes ) ); ?>"
	data-min="<?php echo esc_attr( $min ); ?>"
	data-max="<?php echo esc_attr( $max ); ?>"
	data-step="<?php echo esc_attr( $step ); ?>">

	<?php if ( $show_buttons ) : ?>
		<button type="button"
			class="wse-qty-btn wse-qty-btn--minus"
			aria-label="<?php esc_attr_e( 'Decrease quantity', 'woo-swatches-elementor' ); ?>"
			tabindex="-1">
			<?php
			/**
			 * v1.2.2 (Issues 1+2+3) — Bulletproof icon rendering.
			 *
			 * Live ZYMARG site testing of v1.2.1 surfaced a real-world failure:
			 * Elementor's font-icon-svg/e-icons.php data manager was missing
			 * the "minus" / "plus" keys on the user's installed Elementor
			 * version. When v1.2.1 captured Icons_Manager::render_icon() output
			 * via ob_start(), the captured buffer contained PHP warning text
			 * like "Warning: Undefined array key 'minus' in …e-icons.php on
			 * line 37" — which our trim() check treated as valid icon HTML
			 * and echoed inside the <button>, producing the broken render
			 * shown in the v1.2.2 issue report.
			 *
			 * Three layers of defence:
			 *   1. Default value is '' — so on most installs Icons_Manager
			 *      isn't called at all and the inline-SVG fallback wins.
			 *   2. @-suppress the render_icon() call so warnings don't
			 *      escape into the output stream even when display_errors=on.
			 *   3. Detect warning patterns ("Warning:", "Notice:", "Undefined
			 *      array key") in the captured buffer and fall through to the
			 *      fallback — defensive belt-and-braces.
			 */
			$_minus_html = '';
			if ( class_exists( '\Elementor\Icons_Manager' ) && ! empty( $decrease_icon['value'] ) ) {
				ob_start();
				@\Elementor\Icons_Manager::render_icon( $decrease_icon, array( 'aria-hidden' => 'true' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$_minus_html = trim( (string) ob_get_clean() );

				// Detect Elementor / PHP warning text in the captured buffer.
				if (
					'' !== $_minus_html
					&& (
						false !== stripos( $_minus_html, 'Warning:' )
						|| false !== stripos( $_minus_html, 'Notice:' )
						|| false !== stripos( $_minus_html, 'Undefined array key' )
						|| false !== stripos( $_minus_html, 'Trying to access array offset' )
					)
				) {
					$_minus_html = '';
				}
			}
			if ( '' !== $_minus_html ) {
				echo $_minus_html; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				// Hand-drawn fallback — guaranteed visible.
				echo '<svg class="wse-qty-icon wse-qty-icon-fallback" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 7h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
			}
			?>
		</button>
	<?php endif; ?>

	<input type="number"
		name="<?php echo esc_attr( $input_name ); ?>"
		<?php if ( $input_id ) : ?>id="<?php echo esc_attr( $input_id ); ?>"<?php endif; ?>
		class="<?php echo esc_attr( implode( ' ', $input_classes ) ); ?>"
		value="<?php echo esc_attr( $value ); ?>"
		min="<?php echo esc_attr( $min ); ?>"
		<?php if ( '' !== $max ) : ?>max="<?php echo esc_attr( $max ); ?>"<?php endif; ?>
		step="<?php echo esc_attr( $step ); ?>"
		inputmode="numeric"
		pattern="[0-9]*"
		autocomplete="off"
		aria-label="<?php esc_attr_e( 'Quantity', 'woo-swatches-elementor' ); ?>"/>

	<?php if ( $show_buttons ) : ?>
		<button type="button"
			class="wse-qty-btn wse-qty-btn--plus"
			aria-label="<?php esc_attr_e( 'Increase quantity', 'woo-swatches-elementor' ); ?>"
			tabindex="-1">
			<?php
			// v1.2.2 — see decrease-button comment above for the three-layer
			// defence rationale (empty default + @-suppress + warning detection).
			$_plus_html = '';
			if ( class_exists( '\Elementor\Icons_Manager' ) && ! empty( $increase_icon['value'] ) ) {
				ob_start();
				@\Elementor\Icons_Manager::render_icon( $increase_icon, array( 'aria-hidden' => 'true' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$_plus_html = trim( (string) ob_get_clean() );

				if (
					'' !== $_plus_html
					&& (
						false !== stripos( $_plus_html, 'Warning:' )
						|| false !== stripos( $_plus_html, 'Notice:' )
						|| false !== stripos( $_plus_html, 'Undefined array key' )
						|| false !== stripos( $_plus_html, 'Trying to access array offset' )
					)
				) {
					$_plus_html = '';
				}
			}
			if ( '' !== $_plus_html ) {
				echo $_plus_html; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo '<svg class="wse-qty-icon wse-qty-icon-fallback" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 7h8M7 3v8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
			}
			?>
		</button>
	<?php endif; ?>

</div>
