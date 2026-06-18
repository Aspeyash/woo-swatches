<?php
/**
 * Label / Button swatch template.
 *
 * Renders a single text-label swatch as a <li> element.
 * Used for both 'label' and 'button' swatch types.
 * The visual difference between label and button is handled purely
 * in CSS via the wse-swatch-label--button modifier class.
 *
 * This template is the correct one for attributes like Size (S/M/L/XL),
 * Material (Cotton/Polyester), or any attribute where the term name
 * IS the swatch content.
 *
 * Available variables:
 *   @var string              $value        Term slug / option value.
 *   @var array<string,mixed> $swatch       {
 *       'label'        => string   Display name e.g. 'X-Large',
 *       'type'         => string   'label' or 'button',
 *       'is_available' => bool,
 *       'is_selected'  => bool,
 *   }
 *   @var string              $attribute    Attribute name.
 *   @var bool                $is_selected  Shortcut from $swatch['is_selected'].
 *
 * Gap 14 — WCAG 2.2 ARIA attributes
 * Gap 30 — Supports add_inline_editing_attributes() via data-attr
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

$is_available = (bool)   ( $swatch['is_available'] ?? true );
$is_selected  = (bool)   ( $swatch['is_selected']  ?? false );
$label        = (string) ( $swatch['label']         ?? $value );
$type         = (string) ( $swatch['type']          ?? 'label' );

$classes = array( 'wse-swatch', 'wse-swatch-label' );

// CSS modifier for button style vs label style
if ( 'button' === $type ) {
	$classes[] = 'wse-swatch-label--button';
}

if ( $is_selected )    { $classes[] = 'selected'; }
if ( ! $is_available ) { $classes[] = 'disabled'; }

// B5 — tabindex 0 for selected OR first-focusable when no default selection.
$is_first_focusable = isset( $is_first_focusable ) ? (bool) $is_first_focusable : false;
$tabindex           = ( $is_selected || $is_first_focusable ) ? '0' : '-1';

/*
 * Aria label for screen readers:
 * Announces both the attribute context and the option name.
 * e.g. "Size: X-Large" or "Material: Cotton (unavailable)"
 */
$aria_label = $label;
if ( ! $is_available ) {
	$aria_label .= ' ' . __( '(unavailable)', 'woo-swatches-elementor' );
}
?>
<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	role="radio"
	aria-label="<?php echo esc_attr( $aria_label ); ?>"
	aria-checked="<?php echo $is_selected ? 'true' : 'false'; ?>"
	aria-disabled="<?php echo $is_available ? 'false' : 'true'; ?>"
	tabindex="<?php echo esc_attr( $tabindex ); ?>"
	data-attribute="<?php echo esc_attr( $attribute ); ?>"
	data-value="<?php echo esc_attr( $value ); ?>">

	<span class="wse-swatch-label-text">
		<?php echo esc_html( $label ); ?>
	</span>

</li>
