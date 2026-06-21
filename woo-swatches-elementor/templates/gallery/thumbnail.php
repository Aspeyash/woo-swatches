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

// v1.4.0 — variation association for reverse-sync. Empty/0 = parent-only image.
$_variation_id    = (int)   ( $image['variation_id'] ?? 0 );
$_variation_attrs = (array) ( $image['attributes']   ?? array() );
$_variation_json  = ! empty( $_variation_attrs ) ? wp_json_encode( $_variation_attrs ) : '';

$_classes = array( 'zymarg-vig-thumb' );
if ( ! empty( $is_active ) ) {
	$_classes[] = 'is-active';
}
if ( $_variation_id > 0 ) {
	$_classes[] = 'zymarg-vig-thumb--variation';
}
?>
<button type="button"
	class="<?php echo esc_attr( implode( ' ', $_classes ) ); ?>"
	data-image-id="<?php echo esc_attr( (string) $_id ); ?>"
	data-image-index="<?php echo absint( $index ?? 0 ); ?>"
	<?php if ( $_variation_id > 0 ) : ?>
		data-variation-id="<?php echo absint( $_variation_id ); ?>"
		data-variation-attrs="<?php echo esc_attr( (string) $_variation_json ); ?>"
	<?php endif; ?>
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
		<?php /* v1.4.0 (S5) — Lazy-load all variation thumbs except the
		         first 5. Parent gallery thumbs honor the lazy_load arg
		         from the layout template. Variation thumbs after index
		         5 get a hard `loading="lazy"` regardless of lazy_load
		         to keep mobile data usage low. */ ?>
		<?php
		$_force_lazy = ( $_variation_id > 0 && (int) ( $index ?? 0 ) >= 5 );
		if ( ! empty( $lazy_load ) || $_force_lazy ) :
		?>loading="lazy"<?php endif; ?>
		decoding="async"/>

</button>
