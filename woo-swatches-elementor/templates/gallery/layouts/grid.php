<?php
/**
 * Gallery layout — Grid (v1.3.0).
 *
 * Shopify Dawn pattern. Renders all images in a 2-column grid, all
 * visible at once on desktop. No thumbnail strip — every image is a
 * "main". On tablet collapses to 1 column.
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
<div class="zymarg-vig-layout zymarg-vig-layout--grid">

	<?php foreach ( $images as $i => $img ) : ?>
		<?php
		echo WSE_Widget_Variation_Image_Gallery::include_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'main-image.php',
			array(
				'image'                => $img,
				'show_zoom'            => ! empty( $show_zoom ),
				'show_lightbox'        => ! empty( $show_lightbox ),
				'show_sale_badge'      => ! empty( $show_sale_badge ) && 0 === $i,
				'sale_badge_text'      => (string) ( $sale_badge_text ?? '' ),
				'is_on_sale'           => ! empty( $is_on_sale ) && 0 === $i,
				// v1.3.3 (F4) — Counter only on first image (grid shows all
				// images at once; one counter is enough).
				'show_image_counter'   => ! empty( $show_image_counter ) && 0 === $i,
				'image_counter_format' => (string) ( $image_counter_format ?? '{current} / {total}' ),
				'active_index'         => $i,
				'total_count'          => count( $images ),
			)
		);
		?>
	<?php endforeach; ?>

</div><!-- .zymarg-vig-layout--grid -->
