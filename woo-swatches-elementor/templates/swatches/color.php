<?php
/**
 * Color swatch template.
 *
 * Renders a single colored square swatch as a <li> element.
 * The background-color is the hex value stored in term meta (wse_color).
 *
 * Available variables:
 *   @var string              $value        Term slug / option value e.g. 'red'.
 *   @var array<string,mixed> $swatch       {
 *       'label'        => string   Display name e.g. 'Red',
 *       'color'        => string   Hex color  e.g. '#ff0000',
 *       'is_available' => bool     Whether any variation with this term is in stock,
 *       'is_selected'  => bool     Whether this is the currently selected value,
 *   }
 *   @var string              $attribute    Attribute name e.g. 'pa_color'.
 *   @var bool                $is_selected  Shortcut from $swatch['is_selected'].
 *
 * Gap 14 — WCAG 2.2: role=radio, aria-checked, aria-disabled,
 *           tabindex, min 44px touch target (enforced in CSS)
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

$is_available = (bool) ( $swatch['is_available'] ?? true );
$is_selected  = (bool) ( $swatch['is_selected']  ?? false );
$label        = (string) ( $swatch['label']       ?? $value );
$color        = (string) ( $swatch['color']       ?? '#e0e0e0' );

// CSS class string
$classes = array( 'wse-swatch', 'wse-swatch-color' );
if ( $is_selected )   { $classes[] = 'selected'; }
if ( ! $is_available ) { $classes[] = 'disabled'; }

// tabindex: 0 for focusable (selected or first available), -1 for others
// This is simplified — swatches.js manages full roving tabindex
$tabindex = $is_selected ? '0' : '-1';
?>
<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	role="radio"
	aria-label="<?php echo esc_attr(
		sprintf(
			/* translators: %s: color name */
			__( 'Color: %s', 'woo-swatches-elementor' ),
			$label
		)
	); ?>"
	aria-checked="<?php echo $is_selected ? 'true' : 'false'; ?>"
	aria-disabled="<?php echo $is_available ? 'false' : 'true'; ?>"
	tabindex="<?php echo esc_attr( $tabindex ); ?>"
	data-attribute="<?php echo esc_attr( $attribute ); ?>"
	data-value="<?php echo esc_attr( $value ); ?>"
	title="<?php echo esc_attr( $label ); ?>"
	style="background-color:<?php echo esc_attr( $color ); ?>;">

	<?php
	/**
	 * Checkmark overlay — visible when swatch is selected.
	 * Controlled by CSS:
	 *   .wse-swatch.selected .wse-checkmark { opacity: 1; }
	 *
	 * aria-hidden="true" since the parent li already has aria-checked.
	 */
	?>
	<span class="wse-checkmark" aria-hidden="true">
		<svg width="12" height="12" viewBox="0 0 12 12" fill="none"
		     xmlns="http://www.w3.org/2000/svg">
			<path d="M2 6l3 3 5-5"
			      stroke="currentColor"
			      stroke-width="1.8"
			      stroke-linecap="round"
			      stroke-linejoin="round"/>
		</svg>
	</span>

</li>
