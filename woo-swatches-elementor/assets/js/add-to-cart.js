/**
 * WooSwatches for Elementor — Add to Cart Engine
 *
 * Responsibilities:
 *   1. Cross-widget sync  — mirrors Widget 1 swatch selections into Widget 2's
 *                           hidden <select> elements so wc-add-to-cart-
 *                           variation.js can find the variation + enable button.
 *   2. AJAX add-to-cart   — intercepts .wse-atc-form submissions and POSTs to
 *                           WooCommerce's ?wc-ajax=add_to_cart endpoint.
 *   3. Loading / success  — button state machine: default → loading → added.
 *   4. Fragment refresh   — triggers WC's built-in fragment refresh so the
 *                           mini-cart count updates without a page reload.
 *   5. Reset sync         — when Widget 1 resets (wse:swatchReset), clears
 *                           Widget 2's hidden selects and disables the button.
 *
 * Cross-widget event protocol (wse:swatchSelected):
 *   Fired by swatches.js on document.body whenever a swatch is clicked in a
 *   .wse-variations-form (Widget 1).  Payload:
 *     { attribute, value, $swatch, $form }
 *
 *   add-to-cart.js only acts when the originating $form carries the class
 *   .wse-variations-form (Widget 1), then finds the matching .wse-atc-form
 *   by [data-product_id] and mirrors the selection.
 *
 * @package WooSwatchesElementor
 */
( function ( $, window ) {
	'use strict';

	// ─────────────────────────────────────────────────────────────────────
	// State constants
	// ─────────────────────────────────────────────────────────────────────
	var BTN_DEFAULT  = 'default';
	var BTN_LOADING  = 'loading';
	var BTN_ADDED    = 'added';
	var BTN_ERROR    = 'error';

	// ─────────────────────────────────────────────────────────────────────
	// 1.  Cross-widget sync — Widget 1 → Widget 2
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Listens for wse:swatchSelected (fired by swatches.js on document.body)
	 * and mirrors the selection into the matching .wse-atc-form.
	 */
	function initCrossWidgetSync() {

		$( document.body )
			.off( 'wse:swatchSelected.wse-atc' )
			.on( 'wse:swatchSelected.wse-atc', function ( e, data ) {

			// Only act when the originating form is Widget 1 (.wse-variations-form).
			if ( ! data.$form || ! data.$form.hasClass( 'wse-variations-form' ) ) {
				return;
			}

			var productId = data.$form.data( 'product_id' );
			var $atcForm  = getAtcForm( productId );

			if ( ! $atcForm.length ) {
				return;
			}

			// Mirror to Widget 2's hidden select + fire .change() so
			// wc-add-to-cart-variation.js picks up the new selection.
			$atcForm
				.find( 'select[name="attribute_' + data.attribute + '"]' )
				.val( data.value )
				.trigger( 'change' );
		} );

		/**
		 * When Widget 1 resets all swatches, clear Widget 2's selects too.
		 * wc-add-to-cart-variation.js will disable the button automatically
		 * once the selects are emptied.
		 */
		$( document.body )
			.off( 'wse:swatchReset.wse-atc' )
			.on( 'wse:swatchReset.wse-atc', function ( e, data ) {

			if ( ! data.$form || ! data.$form.hasClass( 'wse-variations-form' ) ) {
				return;
			}

			var productId = data.$form.data( 'product_id' );
			var $atcForm  = getAtcForm( productId );

			if ( ! $atcForm.length ) {
				return;
			}

			$atcForm
				.find( '.wse-hidden-selects select' )
				.val( '' )
				.trigger( 'change' );
		} );
	}

	/**
	 * Finds Widget 2's .wse-atc-form for a given product_id.
	 *
	 * @param  {number|string} productId
	 * @return {jQuery}
	 */
	function getAtcForm( productId ) {
		return $( 'form.wse-atc-form[data-product_id="' + productId + '"]' );
	}

	// ─────────────────────────────────────────────────────────────────────
	// 2.  AJAX add-to-cart — intercept .wse-atc-form submission
	// ─────────────────────────────────────────────────────────────────────

	function initAjaxAddToCart() {

		/**
		 * Intercept the add-to-cart button click inside Widget 2's forms.
		 * External product forms are skipped — they're plain anchor links.
		 *
		 * Fix (v1.0.4): .off() before .on() so repeated init() calls
		 * (see wse:reinit) never stack a second click handler — the
		 * direct cause of duplicate AJAX submissions per click.
		 */
		$( document.body )
			.off( 'click.wse-atc' )
			.on( 'click.wse-atc', '.wse-atc-form .wse-atc-button', function ( e ) {

			var $btn  = $( this );
			var $form = $btn.closest( 'form.wse-atc-form' );

			// Skip external products (button is a link, no AJAX needed).
			if ( $form.hasClass( 'wse-external-cart' ) ) {
				return;
			}

			// Skip if WC has already disabled the button (no valid variation).
			if ( $btn.hasClass( 'disabled' ) || $btn.prop( 'disabled' ) ) {
				return;
			}

			e.preventDefault();

			submitAtcForm( $btn, $form );
		} );
	}

	/**
	 * Serialises the form and POSTs to WooCommerce's add-to-cart endpoint.
	 *
	 * Fix (v1.0.5): some server-side plugins (e.g. multi-vendor plugins)
	 * filter woocommerce_add_to_cart_validation to false for AJAX
	 * submissions while allowing native form POST — returning {error:true}
	 * even though another mechanism (WC's own variation-form submission)
	 * already added the item. We verify the actual cart state via a
	 * follow-up get_refreshed_fragments call before deciding whether to
	 * show success or failure.
	 *
	 * @param {jQuery} $btn  The button element (.wse-atc-button).
	 * @param {jQuery} $form The add-to-cart form (.wse-atc-form).
	 */
	function submitAtcForm( $btn, $form ) {

		setBtnState( $btn, BTN_LOADING );

		// Capture the cart hash before the add attempt so we can detect
		// whether the cart actually changed if WC returns an error.
		var hashBefore = window.localStorage
			? ( localStorage.getItem( 'wc_cart_hash' ) || '' )
			: '';

		var formData = $form.serializeArray();
		formData.push( { name: 'action', value: 'woocommerce_add_to_cart' } );

		$.ajax( {
			type : 'POST',
			url  : WSEParams.wc_ajax_url.replace( '%%endpoint%%', 'add_to_cart' ),
			data : formData,

			success : function ( response ) {

				if ( ! response || response.error ) {
					// WC returned an error. Verify whether the cart hash
					// actually changed before showing "Something went wrong".
					verifyCartChange( $btn, $form, hashBefore );
					return;
				}

				onAddToCartSuccess( $btn, $form, response );
			},

			error : function () {
				// Network / parse error — still verify cart state because
				// the operation may have completed server-side before the
				// response was corrupted (rare, but handles CDN edge cases).
				verifyCartChange( $btn, $form, hashBefore );
			},
		} );
	}

	/**
	 * Fetch fresh cart fragments and compare cart hash with the value
	 * captured before the add-to-cart attempt.
	 *
	 * If the hash changed → cart was genuinely modified (treat as success).
	 * If the hash is the same → nothing changed (genuine error).
	 *
	 * @param {jQuery} $btn
	 * @param {jQuery} $form
	 * @param {string} hashBefore  localStorage wc_cart_hash before add.
	 */
	function verifyCartChange( $btn, $form, hashBefore ) {

		$.get(
			WSEParams.wc_ajax_url.replace( '%%endpoint%%', 'get_refreshed_fragments' ),
			function ( fragsResponse ) {

				var hashAfter = ( fragsResponse && fragsResponse.cart_hash )
					? fragsResponse.cart_hash
					: '';

				if ( hashAfter && hashAfter !== hashBefore ) {
					// Cart changed — the item WAS added despite the error
					// response. Treat as success with the fresh fragments.
					onAddToCartSuccess( $btn, $form, fragsResponse );
				} else {
					// Hash unchanged — genuine add-to-cart failure.
					setBtnState( $btn, BTN_ERROR );
					setTimeout( function () {
						setBtnState( $btn, BTN_DEFAULT );
					}, 3000 );
				}
			}
		).fail( function () {
			// Fragments fetch failed — show error conservatively.
			setBtnState( $btn, BTN_ERROR );
			setTimeout( function () {
				setBtnState( $btn, BTN_DEFAULT );
			}, 3000 );
		} );
	}

	/**
	 * Called only when add-to-cart is confirmed successful — either because
	 * WC returned fragments directly, or because verifyCartChange() detected
	 * a cart hash change after an initial error response.
	 *
	 * @param {jQuery} $btn
	 * @param {jQuery} $form
	 * @param {Object} response  WC AJAX response (may come from
	 *                           add_to_cart or get_refreshed_fragments).
	 */
	function onAddToCartSuccess( $btn, $form, response ) {

		setBtnState( $btn, BTN_ADDED );

		// Apply updated cart fragments (mini-cart count, etc.).
		if ( response && response.fragments ) {
			$.each( response.fragments, function ( key, value ) {
				$( key ).replaceWith( value );
			} );
		}

		// ── WC fragment refresh ───────────────────────────────────────────
		$( document.body ).trigger( 'wc_fragment_refresh' );

		/**
		 * Standard WC added_to_cart event so themes (e.g. Astra's bottom-
		 * right "Added to cart" snackbar) react identically to how they
		 * react to shop-loop AJAX add-to-cart buttons.
		 */
		$( document.body ).trigger( 'added_to_cart', [
			response && response.fragments  ? response.fragments  : {},
			response && response.cart_hash  ? response.cart_hash  : '',
			$btn,
		] );

		// ── Developer event ───────────────────────────────────────────────
		$( document.body ).trigger( 'wse:addedToCart', [ {
			$btn    : $btn,
			$form   : $form,
			response: response,
		} ] );

		// Reset button after a short delay.
		setTimeout( function () {
			setBtnState( $btn, BTN_DEFAULT );
		}, 3000 );
	}

	// ─────────────────────────────────────────────────────────────────────
	// 3.  Button state machine
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Transitions the button between visual states.
	 *
	 * States and their classes / text:
	 *   default : no extra class  — original button text
	 *   loading : wse-atc-loading — "Adding…"
	 *   added   : wse-atc-added   — "Added ✓"
	 *   error   : wse-atc-error   — "Error — try again"
	 *
	 * The original button text is stored in data-original-text on first call.
	 *
	 * @param {jQuery} $btn
	 * @param {string} state  One of BTN_DEFAULT | BTN_LOADING | BTN_ADDED | BTN_ERROR.
	 */
	function setBtnState( $btn, state ) {

		// Persist the original label once.
		if ( ! $btn.data( 'original-text' ) ) {
			$btn.data( 'original-text', $btn.text() );
		}

		var original = $btn.data( 'original-text' );

		$btn
			.removeClass( 'wse-atc-loading wse-atc-added wse-atc-error' )
			.prop( 'disabled', false );

		var i18n = ( window.WSEParams && WSEParams.i18n ) ? WSEParams.i18n : {};

		switch ( state ) {

			case BTN_LOADING:
				$btn.addClass( 'wse-atc-loading' )
				    .prop( 'disabled', true )
				    .text( i18n.adding || 'Adding\u2026' );
				break;

			case BTN_ADDED:
				$btn.addClass( 'wse-atc-added' )
				    .text( i18n.added || 'Added \u2713' );
				break;

			case BTN_ERROR:
				$btn.addClass( 'wse-atc-error' )
				    .text( i18n.error || 'Error \u2014 try again' );
				break;

			default: // BTN_DEFAULT
				$btn.text( original );
				break;
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// 4.  Variable product — found_variation sync
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * When wc-add-to-cart-variation.js finds a valid variation in Widget 2's
	 * form (found_variation event), ensures the variation_id hidden input is
	 * populated before any form submission (WC does this natively; this is a
	 * safety catch for edge-case themes that don't enqueue the WC script).
	 */
	function initVariationSync() {

		$( document.body )
			.off( 'found_variation.wse-atc' )
			.on( 'found_variation.wse-atc', 'form.wse-atc-form', function ( e, variation ) {

			var $form = $( this );
			$form.find( 'input.variation_id' ).val( variation.variation_id || 0 );

			// Re-enable button in case WC disabled it during selection.
			$form.find( '.wse-atc-button' )
			     .prop( 'disabled', false )
			     .removeClass( 'disabled' );
		} );

		$( document.body )
			.off( 'reset_data.wse-atc hide_variation.wse-atc' )
			.on( 'reset_data.wse-atc hide_variation.wse-atc', 'form.wse-atc-form', function () {
			$( this ).find( 'input.variation_id' ).val( 0 );
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// 5.  Quantity stepper — +/- buttons (optional theme integration)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Some themes add increment/decrement buttons around the WC quantity input.
	 * This handler supports that pattern if .wse-qty-plus / .wse-qty-minus
	 * elements are present (can be added via theme or custom template override).
	 */
	function initQuantityStepper() {

		$( document.body )
			.off( 'click.wse-qty' )
			.on( 'click.wse-qty', '.wse-qty-plus, .wse-qty-minus', function () {

			var $btn   = $( this );
			var $input = $btn.closest( '.wse-qty-wrap' ).find( 'input.qty' );
			var min    = parseFloat( $input.attr( 'min' ) )  || 1;
			var max    = parseFloat( $input.attr( 'max' ) )  || Infinity;
			var step   = parseFloat( $input.attr( 'step' ) ) || 1;
			var val    = parseFloat( $input.val() ) || min;

			if ( $btn.hasClass( 'wse-qty-plus' ) ) {
				val = Math.min( val + step, max );
			} else {
				val = Math.max( val - step, min );
			}

			$input.val( val ).trigger( 'change' );
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Bootstrap
	// ─────────────────────────────────────────────────────────────────────

	function init() {
		initCrossWidgetSync();
		initAjaxAddToCart();
		initVariationSync();
		initQuantityStepper();
	}

	$( document ).ready( init );

	/**
	 * Reinit after Elementor popups, AJAX page loads, WC quick-views, etc.
	 *
	 * Fix (v1.0.4): every document.body binding in this file now calls
	 * .off() immediately before .on(), so init() is fully idempotent —
	 * safe to call any number of times without stacking duplicate
	 * handlers (the root cause of duplicate AJAX add-to-cart requests).
	 * class-ajax-compat.php additionally caps wse:reinit at one firing
	 * per page load, so in practice init() now runs at most twice total
	 * (document.ready + at most one wse:reinit).
	 */
	$( document.body ).on( 'wse:reinit elementor/popup/show', init );

} )( jQuery, window );
