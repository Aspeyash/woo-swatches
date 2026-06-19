<?php
/**
 * Gallery layout — Vertical thumbnails (v1.3.0).
 *
 * Used by both vertical_left (default desktop, Apple/Nike/Apple/Sephora
 * pattern) and vertical_right (mirrored variant). The CSS rule
 * .zymarg-vig--layout-vertical_right { flex-direction: row-reverse }
 * handles the mirroring without a separate template.
 *
 * Available variables (extracted by WSE_Widget_Variation_Image_Gallery::render()):
 *   @var array<int,array> $images        Initial image list to render.
 *   @var int              $active_index  Initially-active image (0).
 *   @var bool             $show_zoom
 *   @var bool             $show_lightbox
 *   @var bool             $show_sale_badge
 *   @var string           $sale_badge_text
 *   @var bool             $is_on_sale
 *   @var bool             $lazy_load_thumbs
 *
 * @package WooSwatchesElementor
 * @since   1.3.0
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $images ) ) {
	return;
}

$_active = absint( $active_index ?? 0 );
?>
<div class="zymarg-vig-layout zymarg-vig-layout--vertical">

	<?php /* Thumbnails strip — left or right via parent layout class. */ ?>
	<div class="zymarg-vig-thumbs zymarg-vig-thumbs--vertical"
		role="tablist"
		aria-label="<?php esc_attr_e( 'Product image thumbnails', 'woo-swatches-elementor' ); ?>">

		<?php foreach ( $images as $i => $img ) : ?>
			<?php
			WSE_Widget_Variation_Image_Gallery::include_template(
				'thumbnail.php',
				array(
					'image'     => $img,
					'is_active' => ( $i === $_active ),
					'index'     => $i,
					'lazy_load' => ! empty( $lazy_load_thumbs ),
				)
			);
			?>
		<?php endforeach; ?>

	</div><!-- .zymarg-vig-thumbs -->

	<?php /* Main image — picks up .is-active thumb's source. */ ?>
	<div class="zymarg-vig-main-wrap">
		<?php
		WSE_Widget_Variation_Image_Gallery::include_template(
			'main-image.php',
			array(
				'image'           => $images[ $_active ] ?? $images[0],
				'show_zoom'       => ! empty( $show_zoom ),
				'show_lightbox'   => ! empty( $show_lightbox ),
				'show_sale_badge' => ! empty( $show_sale_badge ),
				'sale_badge_text' => (string) ( $sale_badge_text ?? '' ),
				'is_on_sale'      => ! empty( $is_on_sale ),
			)
		);
		?>
	</div><!-- .zymarg-vig-main-wrap -->

</div><!-- .zymarg-vig-layout -->
