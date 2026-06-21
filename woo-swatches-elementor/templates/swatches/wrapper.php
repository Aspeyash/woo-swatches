<?php
/**
 * Swatch wrapper template (v1.1.0).
 *
 * Outputs the outer container for a single attribute's swatches.
 * The original WooCommerce <select> is preserved hidden in the DOM only
 * when $emit_select is true — Widget 1 sets it to false in v1.1.0 so
 * the canonical form (Widget 2) is the sole owner of form-field state.
 *
 * Available variables (extracted by WSE_Swatch_Renderer::include_template):
 *
 *   @var string       $html         Original WC <select> HTML — hidden but intact.
 *   @var string       $attribute    Attribute name e.g. 'pa_color'.
 *   @var \WC_Product  $product      The product object.
 *   @var string       $type         Swatch type: color|image|label|button.
 *   @var string       $items_html   Pre-built <li> HTML for all swatch items.
 *   @var bool         $emit_select  v1.1.0 — emit the hidden native <select>?
 *   @var bool         $emit_swatches v1.1.0 — emit the visible swatch <ul>?
 *
 * Gap 2  — Hidden <select> preserved (Emran Ahmed's confirmed pattern)
 * Gap 14 — Accessibility: fieldset + legend wrap (no duplicate aria-label, B15)
 * Gap 40 — Clear/reset link wired to WC reset_data event in swatches.js
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

// Back-compat defaults so child-theme overrides from v1.0.5 keep rendering.
$emit_select   = isset( $emit_select )   ? (bool) $emit_select   : true;
$emit_swatches = isset( $emit_swatches ) ? (bool) $emit_swatches : true;

$attr_label = wc_attribute_label( $attribute, $product );
$form_id    = WSE_Form_Registry::instance()->get_form_id( $product->get_id() );
?>
<div class="wse-swatch-wrap"
	data-attribute="<?php echo esc_attr( $attribute ); ?>"
	data-type="<?php echo esc_attr( $type ); ?>"
	data-product-id="<?php echo absint( $product->get_id() ); ?>"
	data-form-id="<?php echo esc_attr( $form_id ); ?>">

	<?php if ( $emit_select ) : ?>
	<?php
	/**
	 * Gap 2 — The hidden WC <select> stays in the DOM for canonical-form
	 * emitters. wc-add-to-cart-variation.js reads/writes it via:
	 *   $form.find( 'select[name="attribute_pa_color"]' )
	 *
	 * aria-hidden="true" removes it from the accessibility tree since
	 * the ARIA radio group below replaces it semantically.
	 */
	?>
	<div class="wse-select-hidden variations" style="display:none" aria-hidden="true">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
		?>
	</div>
	<?php endif; ?>

	<?php if ( $emit_swatches ) : ?>
	<?php
	/**
	 * B15 — fieldset + legend provides the semantic grouping that screen
	 * readers announce before reading individual swatch options. The
	 * <ul role="radiogroup"> below carries no aria-label so the legend
	 * isn't announced twice on screen readers that handle <fieldset>
	 * natively.
	 */
	?>
	<fieldset class="wse-fieldset">
		<legend class="wse-legend screen-reader-text">
			<?php echo esc_html( $attr_label ); ?>
		</legend>

		<ul class="wse-swatches wse-swatches-<?php echo esc_attr( $type ); ?>"
			role="radiogroup"
			data-source-widget="wse-renderer">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $items_html;
			?>
		</ul>

		<?php
		/**
		 * Gap 40 — Clear/reset link.
		 * swatches.js shows it once any swatch is selected and wires it
		 * to the WC reset_data event flow.
		 */
		?>
	<?php
	/**
	 * "Clear selection" link — visible only when a swatch is currently
	 * selected. wc-add-to-cart-variation.js re-shows the link via inline
	 * style when a variation is found, and our swatches.js mirrors the
	 * same trigger when the customer clicks a swatch.
	 *
	 * v1.3.5 (B1) — Honor the per-Widget-1-instance show_clear toggle
	 * via the wse_show_clear_button filter. When the filter returns
	 * 'no', skip emitting the element entirely. Pre-1.3.5 the element
	 * was always rendered regardless of the toggle.
	 */
	$_show_clear_button = (string) apply_filters( 'wse_show_clear_button', 'yes' );
	if ( 'no' !== $_show_clear_button ) :
	?>
		<a href="#"
		   class="wse-reset-link"
		   style="display:none"
		   aria-label="<?php echo esc_attr( sprintf(
				/* translators: %s: attribute label */
				__( 'Clear %s selection', 'woo-swatches-elementor' ),
				$attr_label
		   ) ); ?>">
			<?php
			// v1.2.3 Tier 0 — Editable Clear-link text via Widget 1 setting.
			echo esc_html( apply_filters(
				'wse_clear_button_text',
				__( 'Clear', 'woo-swatches-elementor' )
			) );
			?>
		</a>
	<?php endif; ?>

	</fieldset>
	<?php endif; ?>
</div>
