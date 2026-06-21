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
			echo WSE_Widget_Variation_Image_Gallery::include_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		echo WSE_Widget_Variation_Image_Gallery::include_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'main-image.php',
			array(
				'image'                => $images[ $_active ] ?? $images[0],
				'show_zoom'            => ! empty( $show_zoom ),
				'show_lightbox'        => ! empty( $show_lightbox ),
				'show_sale_badge'      => ! empty( $show_sale_badge ),
				'sale_badge_text'      => (string) ( $sale_badge_text ?? '' ),
				'is_on_sale'           => ! empty( $is_on_sale ),
				// v1.3.3 (F4) — Counter inside the figure so it's always
				// positioned relative to the visible main image bounds.
				'show_image_counter'   => ! empty( $show_image_counter ),
				'image_counter_format' => (string) ( $image_counter_format ?? '{current} / {total}' ),
				'active_index'         => $_active,
				'total_count'          => count( $images ),
			)
		);
		?>

		<?php /* v1.3.2 (F4) — Mobile carousel: real swipeable strip of
		         all variation images. Hidden on desktop/tablet by CSS
		         (only shown when --layout-m-mobile_carousel + mobile bp).
		         The single hero <figure> above is hidden in the same
		         media query. */ ?>
		<?php if ( ! empty( $mobile_carousel_enabled ) ) : ?>
			<div class="zymarg-vig-carousel"
				role="region"
				aria-label="<?php esc_attr_e( 'Product image carousel', 'woo-swatches-elementor' ); ?>"
				aria-roledescription="carousel">
				<?php foreach ( $images as $i => $img ) : ?>
					<?php
					$_csrc = (string) ( $img['src']    ?? '' );
					$_calt = (string) ( $img['alt']    ?? '' );
					$_cid  = (int)    ( $img['id']     ?? 0 );
					$_cw   = (int)    ( $img['width']  ?? 0 );
					$_ch   = (int)    ( $img['height'] ?? 0 );
					// v1.4.0 — Carousel slide carries variation association
					// for reverse-sync. JS reads data-variation-id +
					// data-variation-attrs the same way it reads them off
					// .zymarg-vig-thumb / .zymarg-vig-main.
					$_cvid    = (int)   ( $img['variation_id'] ?? 0 );
					$_cvattrs = (array) ( $img['attributes']   ?? array() );
					$_cvjson  = ! empty( $_cvattrs ) ? wp_json_encode( $_cvattrs ) : '';
					$_cclass  = 'zymarg-vig-carousel-slide' . ( $i === $_active ? ' is-active' : '' );
					if ( $_cvid > 0 ) {
						$_cclass .= ' zymarg-vig-carousel-slide--variation';
					}
					?>
					<figure class="<?php echo esc_attr( $_cclass ); ?>"
						data-image-id="<?php echo esc_attr( (string) $_cid ); ?>"
						data-image-index="<?php echo absint( $i ); ?>"
						<?php if ( $_cvid > 0 ) : ?>
							data-variation-id="<?php echo absint( $_cvid ); ?>"
							data-variation-attrs="<?php echo esc_attr( (string) $_cvjson ); ?>"
						<?php endif; ?>
						aria-roledescription="slide"
						aria-label="<?php
							echo esc_attr( sprintf(
								/* translators: 1: current slide index, 2: total */
								__( '%1$d of %2$d', 'woo-swatches-elementor' ),
								$i + 1,
								count( $images )
							) );
						?>">
						<img class="zymarg-vig-carousel-img"
							src="<?php echo esc_url( $_csrc ); ?>"
							alt="<?php echo esc_attr( $_calt ); ?>"
							<?php if ( $_cw > 0 ) : ?>width="<?php echo (int) $_cw; ?>"<?php endif; ?>
							<?php if ( $_ch > 0 ) : ?>height="<?php echo (int) $_ch; ?>"<?php endif; ?>
							loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>"
							decoding="async"/>
					</figure>
				<?php endforeach; ?>

				<?php /* v1.3.3 (F4) — Counter for the mobile_carousel
				         layout: lives inside the carousel container so
				         its position:absolute coords are relative to
				         the carousel viewport, matching the currently
				         centered slide. The figure's counter (rendered
				         by main-image.php) stays hidden on this layout
				         since the figure itself is hidden. */ ?>
				<?php if ( ! empty( $show_image_counter ) && count( $images ) > 1 ) : ?>
					<span class="zymarg-vig-counter zymarg-vig-counter--carousel"
						data-format="<?php echo esc_attr( $image_counter_format ?? '{current} / {total}' ); ?>"
						data-total="<?php echo absint( count( $images ) ); ?>"
						aria-live="polite">
						<?php
						echo esc_html( str_replace(
							array( '{current}', '{total}' ),
							array( (string) ( $_active + 1 ), (string) count( $images ) ),
							(string) ( $image_counter_format ?? '{current} / {total}' )
						) );
						?>
					</span>
				<?php endif; ?>
			</div><!-- .zymarg-vig-carousel -->
		<?php endif; ?>

	</div><!-- .zymarg-vig-main-wrap -->

</div><!-- .zymarg-vig-layout -->
