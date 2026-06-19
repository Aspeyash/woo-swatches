/**
 * WooSwatches for Elementor — ZYMARG Variation Image Gallery JS (v1.3.0)
 *
 * Subscribes each .zymarg-vig[data-form-id] widget on the page to the
 * canonical form's `found_variation` and `reset_data` events fired by
 * wc-add-to-cart-variation.js, and cross-fades the thumbnails + main
 * image when the customer picks a variation via Widget 1.
 *
 * Same architectural pattern as price.js (v1.2.0+) — a pure consumer of
 * events that other parts of the plugin or WC core already fire. Multiple
 * gallery instances on the same page (e.g. main column + sticky-floating
 * preview) all sync to the same canonical form.
 *
 * Variation→images mapping is embedded as data-variation-images JSON on
 * the widget root, computed once server-side. No AJAX needed for the
 * variation swap — the JS just reads the JSON and swaps the DOM.
 *
 * Phase 1 ships an IIFE skeleton with documented Phase-2 / Phase-4 hook
 * points. Phase 2 implements the basic variation swap. Phase 4 adds
 * zoom, lightbox, keyboard nav, and pinch-to-zoom on mobile.
 *
 * @package WooSwatchesElementor
 * @since   1.3.0
 */
( function ( $, WSEParams ) {
	'use strict';

	$( function () {

		// Phase 1 scaffold: bind nothing yet. Phases 2 / 4 will implement:
		//
		//   $( '.zymarg-vig[data-form-id]' ).each( function () {
		//       var $widget = $( this );
		//       var images  = parseJSONAttr( $widget, 'variation-images' );
		//       var $form   = $( '#' + $widget.data( 'form-id' ) );
		//       if ( ! $form.length ) { return; }
		//
		//       $form.on( 'found_variation.wseGallery', function ( e, variation ) {
		//           swapToVariation( $widget, images, variation.variation_id );
		//       } );
		//
		//       $form.on( 'reset_data.wseGallery', function () {
		//           restoreInitial( $widget, images );
		//       } );
		//
		//       initThumbClicks( $widget );
		//       initZoomLens( $widget );
		//       initLightbox( $widget );
		//       initMobileCarousel( $widget );
		//   } );

	} );

} )( jQuery, window.WSEParams || {} );
