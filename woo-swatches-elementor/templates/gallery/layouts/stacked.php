<?php
/**
 * Gallery layout — Stacked vertical (v1.3.0).
 *
 * Allbirds / H&M / Patagonia minimal pattern. Renders ALL images at full
 * size in a single vertical column, no thumbnail strip. Best for fashion
 * brands that want users to scroll through every image at full quality.
 *
 * Available variables (extracted by WSE_Widget_Variation_Image_Gallery::render()):
 *   @var array<int,array> $images         Initial image list to render.
 *   @var bool             $show_zoom
 *   @var bool             $show_lightbox
 *   @var bool             $show_sale_badge
 *   @var string           $sale_badge_text
 *   @var bool             $is_on_sale
 *
 * @package WooSwatchesElementor
 * @since   1.3.0
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $images ) ) {
	return;
}
?>
<div class="zymarg-vig-layout zymarg-vig-layout--stacked">

	<?php foreach ( $images as $i => $img ) : ?>
		<?php
		// Render every image as a "main" so each is full-size. Sale badge
		// shows only on the first one — repeating would be visual noise.
		WSE_Widget_Variation_Image_Gallery::include_template(
			'main-image.php',
			array(
				'image'           => $img,
				'show_zoom'       => ! empty( $show_zoom ),
				'show_lightbox'   => ! empty( $show_lightbox ),
				'show_sale_badge' => ! empty( $show_sale_badge ) && 0 === $i,
				'sale_badge_text' => (string) ( $sale_badge_text ?? '' ),
				'is_on_sale'      => ! empty( $is_on_sale ) && 0 === $i,
			)
		);
		?>
	<?php endforeach; ?>

</div><!-- .zymarg-vig-layout--stacked -->
