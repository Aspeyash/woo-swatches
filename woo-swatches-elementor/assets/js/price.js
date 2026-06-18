/**
 * WooSwatches for Elementor — ZYMARG Price Widget JS (v1.2.0)
 *
 * Subscribes each .zymarg-price[data-form-id] widget on the page to the
 * canonical form's `found_variation` and `reset_data` events fired by
 * wc-add-to-cart-variation.js, and re-renders the price in place.
 *
 * Phase 1 (scaffold): handlers are no-ops; the file exists only so the
 * `wse-price` script handle resolves. Phase 2 fills in the full variation
 * sync, sale-formatting, and currency-rendering logic.
 *
 * Multiple Widget 3 instances on the same page (e.g. main + sticky cart)
 * are all bound to the same canonical form by data-form-id, so a single
 * variation event updates every visible price widget for that product.
 *
 * @package WooSwatchesElementor
 * @since   1.2.0
 */
( function ( $, WSEParams ) {
	'use strict';

	$( function () {

		// Phase 1: scaffold only. Phase 2 implements:
		//
		//   $( '.zymarg-price[data-form-id]' ).each( function () {
		//       var $widget = $( this );
		//       var $form   = $( '#' + $widget.data( 'form-id' ) );
		//       if ( ! $form.length ) { return; }
		//
		//       $form.on( 'found_variation', function ( e, variation ) {
		//           renderVariation( $widget, variation );
		//       } );
		//
		//       $form.on( 'reset_data', function () {
		//           restoreInitial( $widget );
		//       } );
		//   } );

	} );

} )( jQuery, window.WSEParams || {} );
