<?php
/**
 * Swatch wrapper template.
 *
 * Outputs the outer container for a single attribute's swatches.
 * The original WooCommerce <select> is preserved hidden in the DOM —
 * this is non-negotiable: wc-add-to-cart-variation.js finds the select
 * by name="attribute_{attr}" and reads/writes its value to drive all
 * variation matching, price updates, and stock checks.
 *
 * Available variables (extracted by WSE_Swatch_Renderer::include_template):
 *   @var string       $html       Original WC <select> HTML — hidden but intact.
 *   @var string       $attribute  Attribute name e.g. 'pa_color'.
 *   @var \WC_Product  $product    The product object.
 *   @var string       $type       Swatch type: color|image|label|button.
 *   @var string       $items_html Pre-built <li> HTML for all swatch items.
 *
 * Gap 2  — Hidden <select> preserved (Emran Ahmed's confirmed pattern)
 * Gap 14 — Accessibility: fieldset + legend wrap
 * Gap 40 — Clear/reset link wired to WC reset_data event in swatches.js
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

$attr_label = wc_attribute_label( $attribute, $product );
?>
<div class="wse-swatch-wrap"
	data-attribute="<?php echo esc_attr( $attribute ); ?>"
	data-type="<?php echo esc_attr( $type ); ?>">

	<?php
	/**
	 * Gap 2 — The hidden WC <select> MUST stay in the DOM.
	 *
	 * wc-add-to-cart-variation.js selects it via:
	 *   $form.find( 'select[name="attribute_pa_color"]' )
	 *
	 * swatches.js uses the same selector to sync the hidden value when
	 * a swatch is clicked — matching Emran Ahmed's exact approach.
	 *
	 * aria-hidden="true" removes it from the accessibility tree since
	 * our ARIA radio group below replaces it semantically.
	 */
	?>
	<div class="wse-select-hidden variations" style="display:none" aria-hidden="true">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
		?>
	</div>

	<?php
	/**
	 * Gap 14 — Accessibility.
	 *
	 * <fieldset> + <legend> provides the semantic grouping that screen
	 * readers announce before reading individual swatch options.
	 *
	 * The legend is visually hidden with .screen-reader-text (WP core class)
	 * because the visible attribute label is rendered by the widget's
	 * own render() method above the swatch list.
	 */
	?>
	<fieldset class="wse-fieldset">
		<legend class="wse-legend screen-reader-text">
			<?php echo esc_html( $attr_label ); ?>
		</legend>

		<?php
		/**
		 * role="radiogroup" + aria-label reinforces the grouping for
		 * assistive technologies that don't handle <fieldset> natively
		 * (some mobile screen readers).
		 */
		?>
		<ul class="wse-swatches wse-swatches-<?php echo esc_attr( $type ); ?>"
			role="radiogroup"
			aria-label="<?php echo esc_attr( $attr_label ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $items_html;
			?>
		</ul>

		<?php
		/**
		 * Gap 40 — Clear/reset link.
		 *
		 * Initially hidden via CSS (display:none).
		 * swatches.js shows it as soon as any swatch is selected and
		 * wires it to trigger WC's own '.reset_variations' click —
		 * which fires the 'reset_data' event to deselect all swatches.
		 */
		?>
		<a href="#"
		   class="wse-reset-link"
		   style="display:none"
		   aria-label="<?php echo esc_attr( sprintf(
				/* translators: %s: attribute label */
				__( 'Clear %s selection', 'woo-swatches-elementor' ),
				$attr_label
		   ) ); ?>">
			<?php esc_html_e( 'Clear', 'woo-swatches-elementor' ); ?>
		</a>

	</fieldset>
</div>
