<?php
/**
 * Gallery single thumbnail (v1.3.0).
 *
 * Renders one thumbnail in the thumb strip. Used by every layout that
 * shows thumbnails (vertical-thumbs / horizontal-thumbs / mobile-carousel
 * variants). The active state is driven by the .is-active class.
 *
 * Available variables:
 *   @var array  $image  {
 *       'id'    => int,
 *       'src'   => string,  Main-size URL (used when this thumb is clicked)
 *       'thumb' => string,  Thumb-size URL
 *       'alt'   => string,
 *   }
 *   @var bool   $is_active  Currently selected thumbnail?
 *   @var int    $index      Zero-based index for ARIA / keyboard nav.
 *   @var bool   $lazy_load  Use loading="lazy" on the thumb image?
 *
 * @package WooSwatchesElementor
 * @since   1.3.0
 */

defined( 'ABSPATH' ) || exit;

$_id    = (int)    ( $image['id']    ?? 0 );
$_thumb = (string) ( $image['thumb'] ?? '' );
$_alt   = (string) ( $image['alt']   ?? '' );

$_classes = array( 'zymarg-vig-thumb' );
if ( ! empty( $is_active ) ) {
	$_classes[] = 'is-active';
}
?>
<button type="button"
	class="<?php echo esc_attr( implode( ' ', $_classes ) ); ?>"
	data-image-id="<?php echo esc_attr( (string) $_id ); ?>"
	data-image-index="<?php echo absint( $index ?? 0 ); ?>"
	aria-label="<?php
		echo esc_attr( sprintf(
			/* translators: %d: thumbnail position (1-based) */
			__( 'View image %d', 'woo-swatches-elementor' ),
			absint( $index ?? 0 ) + 1
		) );
	?>"
	<?php if ( ! empty( $is_active ) ) : ?>aria-current="true"<?php endif; ?>
	tabindex="<?php echo ! empty( $is_active ) ? '0' : '-1'; ?>">

	<img class="zymarg-vig-thumb-img"
		src="<?php echo esc_url( $_thumb ); ?>"
		alt="<?php echo esc_attr( $_alt ); ?>"
		<?php if ( ! empty( $lazy_load ) ) : ?>loading="lazy"<?php endif; ?>
		decoding="async"/>

</button>
