<?php
/**
 * Image swatch template.
 *
 * Renders a single image swatch as a <li> element.
 * The image is the result of the 4-step fallback chain in
 * WSE_Swatch_Renderer::get_image_with_fallback():
 *   1. Term meta image (wse_image)
 *   2. Matching variation thumbnail
 *   3. Parent product featured image
 *   4. WooCommerce placeholder
 *
 * The image is rendered as a slightly rounded square (6px border-radius)
 * using object-fit:cover — controlled by the body class wse-shape-rounded
 * and the CSS in swatches.css. The store owner can switch to circle or
 * square from the Elementor widget style controls.
 *
 * Available variables:
 *   @var string              $value        Term slug / option value.
 *   @var array<string,mixed> $swatch       {
 *       'label'        => string  Display name,
 *       'image_id'     => int     Attachment ID (may be 0 if using fallback),
 *       'image_url'    => string  Image URL from fallback chain,
 *       'is_available' => bool,
 *       'is_selected'  => bool,
 *   }
 *   @var string              $attribute    Attribute name.
 *   @var bool                $is_selected  Shortcut from $swatch['is_selected'].
 *
 * Gap 14 — WCAG 2.2 ARIA attributes
 * Gap 54 — Uses 'wse_swatch' image size (80×80 hard crop registered in Phase 1)
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

$is_available = (bool) ( $swatch['is_available'] ?? true );
$is_selected  = (bool) ( $swatch['is_selected']  ?? false );
$label        = (string) ( $swatch['label']       ?? $value );
$image_id     = (int)    ( $swatch['image_id']    ?? 0 );
$image_url    = (string) ( $swatch['image_url']   ?? '' );

$classes = array( 'wse-swatch', 'wse-swatch-image' );
if ( $is_selected )    { $classes[] = 'selected'; }
if ( ! $is_available ) { $classes[] = 'disabled'; }

$tabindex = $is_selected ? '0' : '-1';
?>
<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	role="radio"
	aria-label="<?php echo esc_attr( $label ); ?>"
	aria-checked="<?php echo $is_selected ? 'true' : 'false'; ?>"
	aria-disabled="<?php echo $is_available ? 'false' : 'true'; ?>"
	tabindex="<?php echo esc_attr( $tabindex ); ?>"
	data-attribute="<?php echo esc_attr( $attribute ); ?>"
	data-value="<?php echo esc_attr( $value ); ?>"
	title="<?php echo esc_attr( $label ); ?>">

	<?php if ( $image_id > 0 ) : ?>
		<?php
		/**
		 * Use wp_get_attachment_image() for the best possible output:
		 *   - Generates srcset automatically from registered sizes
		 *   - Handles WebP conversion (WP 5.8+ with WP core or plugins)
		 *   - Adds width/height to prevent layout shift (CLS)
		 *   - loading="lazy" for performance (Gap 19)
		 *   - decoding="async" to avoid render blocking
		 *
		 * 'wse_swatch' = 80×80 hard crop registered in Phase 1 (Gap 54)
		 */
		echo wp_get_attachment_image(
			$image_id,
			'wse_swatch',
			false,
			array(
				'class'    => 'wse-swatch-img',
				'alt'      => esc_attr( $label ),
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
		?>
	<?php else : ?>
		<?php
		/**
		 * Fallback to URL-only rendering when image_id is 0
		 * (e.g. fallback came from variation image or WC placeholder).
		 * We still output a proper <img> with explicit dimensions.
		 */
		?>
		<img class="wse-swatch-img"
		     src="<?php echo esc_url( $image_url ); ?>"
		     alt="<?php echo esc_attr( $label ); ?>"
		     width="80"
		     height="80"
		     loading="lazy"
		     decoding="async"/>
	<?php endif; ?>

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
