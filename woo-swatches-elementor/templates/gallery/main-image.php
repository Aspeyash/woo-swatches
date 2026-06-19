<?php
/**
 * Gallery main image (v1.3.0).
 *
 * Renders the active main image (the big hero in the gallery). Used by
 * every layout (vertical-thumbs / horizontal-thumbs / stacked / grid /
 * mobile-carousel) since the structural <figure> for the main image is
 * the same across layouts.
 *
 * Available variables:
 *   @var array  $image  {
 *       'id'        => int,    Attachment ID (0 for placeholder)
 *       'src'       => string, Main-size URL
 *       'srcset'    => string, Comma-separated srcset
 *       'sizes'     => string, sizes attribute
 *       'alt'       => string, Alt text from WP attachment alt meta
 *       'width'     => int,    Image width (CLS prevention)
 *       'height'    => int,    Image height (CLS prevention)
 *       'thumb'     => string, Thumb-size URL
 *   }
 *   @var bool   $show_zoom         Hover-zoom lens enabled?
 *   @var bool   $show_lightbox     Click-to-lightbox enabled?
 *   @var bool   $show_sale_badge   Render the Sale badge overlay?
 *   @var string $sale_badge_text   Sale badge label text.
 *   @var bool   $is_on_sale        Is the current variation/product on sale?
 *
 * @package WooSwatchesElementor
 * @since   1.3.0
 */

defined( 'ABSPATH' ) || exit;

$_id     = (int)    ( $image['id']     ?? 0 );
$_src    = (string) ( $image['src']    ?? '' );
$_srcset = (string) ( $image['srcset'] ?? '' );
$_sizes  = (string) ( $image['sizes']  ?? '' );
$_alt    = (string) ( $image['alt']    ?? '' );
$_w      = (int)    ( $image['width']  ?? 0 );
$_h      = (int)    ( $image['height'] ?? 0 );

$_classes = array( 'zymarg-vig-main' );
if ( $show_zoom ) {
	$_classes[] = 'zymarg-vig-main--zoomable';
}
if ( $show_lightbox ) {
	$_classes[] = 'zymarg-vig-main--lightbox';
}
?>
<figure class="<?php echo esc_attr( implode( ' ', $_classes ) ); ?>"
	data-image-id="<?php echo esc_attr( (string) $_id ); ?>">

	<?php if ( $is_on_sale && $show_sale_badge && '' !== trim( (string) $sale_badge_text ) ) : ?>
		<span class="zymarg-vig-sale-badge"><?php echo esc_html( $sale_badge_text ); ?></span>
	<?php endif; ?>

	<img class="zymarg-vig-main-img"
		src="<?php echo esc_url( $_src ); ?>"
		<?php if ( '' !== $_srcset ) : ?>srcset="<?php echo esc_attr( $_srcset ); ?>"<?php endif; ?>
		<?php if ( '' !== $_sizes  ) : ?>sizes="<?php echo esc_attr( $_sizes ); ?>"<?php endif; ?>
		alt="<?php echo esc_attr( $_alt ); ?>"
		<?php if ( $_w > 0 ) : ?>width="<?php echo (int) $_w; ?>"<?php endif; ?>
		<?php if ( $_h > 0 ) : ?>height="<?php echo (int) $_h; ?>"<?php endif; ?>
		loading="eager"
		decoding="async"/>

	<?php if ( $show_zoom ) : ?>
		<span class="zymarg-vig-zoom-lens" aria-hidden="true"></span>
	<?php endif; ?>

</figure>
