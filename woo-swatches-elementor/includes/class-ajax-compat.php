<?php
/**
 * AJAX Compatibility
 *
 * Hooks into WooCommerce's variation data pipeline via
 * woocommerce_available_variation to embed swatch meta inside each
 * variation's JSON object. This covers both cases:
 *
 *   a) Initial page load — WC embeds all variation JSON in the page when
 *      the variation count is below woocommerce_ajax_variation_threshold.
 *   b) Lazy AJAX load — WC fetches variations via AJAX when above the
 *      threshold; the same filter fires in both paths.
 *
 * Frontend JS (add-to-cart.js) reads `zymarg_swatch_data` from the
 * variation data object passed to the found_variation event to update
 * the swatch image without an extra round-trip.
 *
 * Also prints a small footer script to reinitialise swatches after
 * WooCommerce fragment refreshes on themes that use AJAX add-to-cart.
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Ajax_Compat {

	// ─────────────────────────────────────────────────────────────────────
	// Init
	// ─────────────────────────────────────────────────────────────────────

	public function __construct() {
		add_filter(
			'woocommerce_available_variation',
			array( $this, 'inject_swatch_data' ),
			10,
			3
		);

		add_action( 'wp_footer', array( $this, 'print_fragment_reinit_script' ), 99 );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Variation data injection
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Appends `zymarg_swatch_data` to WC's per-variation data array.
	 *
	 * The added key is an associative array keyed by taxonomy slug. Each
	 * value contains the type, colour, and image URL for that term so the
	 * frontend can update the swatch image display when a variation is found.
	 *
	 * @param  array                 $data      Existing variation data built by WC.
	 * @param  WC_Product_Variable   $product   Parent variable product.
	 * @param  WC_Product_Variation  $variation The specific variation.
	 * @return array                            Modified variation data.
	 */
	public function inject_swatch_data(
		array $data,
		WC_Product_Variable $product,
		WC_Product_Variation $variation
	): array {

		$swatch_data = array();

		foreach ( $variation->get_attributes() as $taxonomy => $slug ) {

			// Only act on WC product attribute taxonomies; skip "any" slots.
			if ( ! str_starts_with( $taxonomy, 'pa_' ) || '' === $slug ) {
				continue;
			}

			if ( ! WSE_Attribute_Types::is_swatch_type( $taxonomy ) ) {
				continue;
			}

			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$type = WSE_Attribute_Types::get_attribute_type( $taxonomy );

			// Priority image chain:
			// 1. Term swatch image (wse_image meta) → woocommerce_single size
			// 2. Variation image already embedded by WC in $data['image']['src']
			// 3. Empty string — JS will use the parent product image
			$image_url = '';
			$img_id    = WSE_Term_Meta::get_image_id( $term->term_id );
			if ( $img_id ) {
				$url = wp_get_attachment_image_url( $img_id, 'woocommerce_single' );
				if ( $url ) {
					$image_url = $url;
				}
			}
			if ( ! $image_url && ! empty( $data['image']['src'] ) ) {
				$image_url = $data['image']['src'];
			}

			$swatch_data[ $taxonomy ] = array(
				'type'      => $type,
				'term_id'   => $term->term_id,
				'slug'      => $term->slug,
				'name'      => $term->name,
				'color'     => WSE_Term_Meta::get_color( $term->term_id ),
				'image_url' => $image_url,
			);
		}

		if ( ! empty( $swatch_data ) ) {
			$data['zymarg_swatch_data'] = $swatch_data;
		}

		return $data;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Fragment reinit script
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Prints an inline footer script that reinitialises swatch instances
	 * after WooCommerce cart fragment refreshes.
	 *
	 * swatches.js already listens for wse:reinit on document.body and
	 * add-to-cart.js uses delegated handlers, so both survive fragment
	 * refreshes automatically. This script handles edge cases where a
	 * theme's AJAX cart flow re-inserts .variations_form nodes.
	 *
	 * Only emitted on pages where WC and WSE scripts are enqueued.
	 */
	public function print_fragment_reinit_script(): void {

		if (
			! wp_script_is( WSE_Assets::handle( 'swatches_js' ), 'done' )
			&& ! wp_script_is( WSE_Assets::handle( 'add_to_cart_js' ), 'done' )
		) {
			return;
		}
		?>
		<script id="wse-fragment-reinit">
		/* WooSwatches — fragment reinit */
		( function ( $ ) {

			/**
			 * Fix (v1.0.4): wc_fragments_loaded AND wc_fragments_refreshed
			 * both fire during a normal page load (WC's cart-fragments.js
			 * fetches mini-cart data asynchronously shortly after
			 * document.ready), and added_to_cart fires after every
			 * successful add-to-cart. Each of these previously triggered
			 * its own separate wse:reinit — 2-3 times on a single page load.
			 *
			 * add-to-cart.js's init() responded to each wse:reinit by
			 * re-binding ALL its document.body click/event handlers.
			 * jQuery's .on() never deduplicates by namespace, so this
			 * stacked 2-3 click handlers on the Add to Cart button. A
			 * single click then fired 2-3 simultaneous AJAX requests:
			 * the first succeeded (product genuinely added to cart), the
			 * extras failed (e.g. stock already reserved by request #1),
			 * and whichever response resolved last overwrote the button
			 * with "Something went wrong" even though the add succeeded.
			 *
			 * wse:reinit now fires AT MOST ONCE per page load. Combined
			 * with add-to-cart.js's own .off()/.on() idempotency (added in
			 * the same fix), this guarantees exactly one handler per event
			 * regardless of how many of these WC events fire afterward.
			 */
			var wseReinitFired = false;

			function wseReinitOnce() {
				if ( wseReinitFired ) {
					return;
				}
				wseReinitFired = true;
				$( document.body ).trigger( 'wse:reinit' );
			}

			// WC finishes refreshing cart fragments (fires on normal page load)
			$( document.body ).on( 'wc_fragments_refreshed wc_fragments_loaded', wseReinitOnce );

			// After AJAX add-to-cart the product HTML may be replaced in loops
			$( document.body ).on( 'added_to_cart', wseReinitOnce );

		} )( jQuery );
		</script>
		<?php
	}
}
