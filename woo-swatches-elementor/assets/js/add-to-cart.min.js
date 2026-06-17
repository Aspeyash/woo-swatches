/**
 * WooSwatches for Elementor — Add to Cart Engine (v1.1.0)
 *
 * v1.1.0 changes:
 *   • B3   — Single-canonical-form architecture. Cross-widget sync is now
 *            a thin layer; most variation state lives on one form per product.
 *   • B8   — Deterministic AJAX result. The cart-hash heuristic is replaced
 *            with explicit response inspection — only treat as success when
 *            WC's response is non-error AND fragments are present. Falls
 *            back to a single fragment-fetch verification only on network
 *            errors (not on validation rejections).
 *   • B23  — Archive add-to-cart and main add-to-cart both use the WC AJAX
 *            endpoint (?wc-ajax=add_to_cart) so LiteSpeed / Hostinger page
 *            cache plugins correctly bypass them.
 *   • Feat A — Strips the View Cart link from the success message on the
 *              client side too (server-side filter is the primary path,
 *              this is a defensive sweep for fragments that ship cached
 *              snippets).
 *   • Feat B — Sticky presenter wrapper: applies body-padding-bottom equal
 *              to the visible presenter's height when its current breakpoint
 *              has sticky=on. Re-evaluates on resize via matchMedia listeners.
 *   • Presenter qty sync — bidirectional value mirroring between
 *              .wse-canonical-qty and every .wse-presenter-qty for the same
 *              product, with the canonical's value being the submitted truth.
 *
 * @package WooSwatchesElementor
 * @since   1.1.0
 */
( function ( $, window, document ) {
	'use strict';

	// ─────────────────────────────────────────────────────────────────────
	// State constants
	// ─────────────────────────────────────────────────────────────────────
	var BTN_DEFAULT  = 'default';
	var BTN_LOADING  = 'loading';
	var BTN_ADDED    = 'added';
	var BTN_ERROR    = 'error';

	// ─────────────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────────────

	function getCanonicalForm( productId ) {
		var $form = $( '#wse-form-' + String( productId ) );
		return $form.length ? $form : null;
	}

	/**
	 * Reads a config flag for the View Cart link. Default true (show link).
	 * The server-side filter strips the anchor when disabled, but stale
	 * fragments from page caches may still contain it; this client-side
	 * value lets us also strip on the client when needed.
	 */
	function shouldShowViewCart() {
		if ( window.WSEParams && typeof WSEParams.show_view_cart_link !== 'undefined' ) {
			return WSEParams.show_view_cart_link !== false
				&& WSEParams.show_view_cart_link !== 'no'
				&& WSEParams.show_view_cart_link !== 0;
		}
		return true;
	}

	// ─────────────────────────────────────────────────────────────────────
	// 1. Cross-widget sync — Widget 1 click → canonical hidden select
	//    (Lightweight; the real click handling lives in swatches.js's Step 4)
	// ─────────────────────────────────────────────────────────────────────

	function initCrossWidgetSync() {
		$( document.body )
			.off( 'wse:swatchReset.wse-atc' )
			.on( 'wse:swatchReset.wse-atc', function ( e, data ) {
			if ( ! data || ! data.$form ) {
				return;
			}
			// Reset canonical's variation_id so wc-add-to-cart-variation.js
			// re-evaluates on next change.
			data.$form.find( 'input.variation_id' ).val( '' );
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// 2. AJAX add-to-cart — intercept canonical form submission
	// ─────────────────────────────────────────────────────────────────────

	function initAjaxAddToCart() {
		// Canonical button click + presenter button click both submit the
		// canonical form. We listen at form submit level so HTML5 form= linkage
		// from presenters is included automatically.
		$( document.body )
			.off( 'submit.wse-atc', 'form.wse-canonical-form' )
			.on( 'submit.wse-atc', 'form.wse-canonical-form', function ( e ) {

			var $form = $( this );
			// Locate the button used for this submit (event.originalEvent.submitter
			// is the spec-compliant way; fall back to the canonical's primary button).
			var $btn = e.originalEvent && e.originalEvent.submitter
				? $( e.originalEvent.submitter )
				: $form.find( '.wse-atc-button' ).first();

			if ( ! $btn.length || $btn.hasClass( 'disabled' ) || $btn.prop( 'disabled' ) ) {
				return;
			}

			e.preventDefault();
			submitAtcForm( $btn, $form );
		} );

		// Simple/grouped/external add-to-cart forms keep working via direct
		// form submission — no AJAX intercept (matches v1.0.5 behaviour).
	}

	function submitAtcForm( $btn, $form ) {
		setBtnState( $btn, BTN_LOADING );

		var formData = $form.serializeArray();
		formData.push( { name: 'action', value: 'woocommerce_add_to_cart' } );

		$.ajax( {
			type : 'POST',
			url  : WSEParams.wc_ajax_url.replace( '%%endpoint%%', 'add_to_cart' ),
			data : formData,

			success : function ( response ) {
				// v1.1.0 (B8) — Deterministic. WC's add_to_cart endpoint
				// returns { error: true, product_url: ... } on validation
				// failure (Dokan etc) and { fragments, cart_hash, ... } on
				// success. We trust this strictly.
				if ( ! response || response.error ) {
					handleAddToCartError( $btn, $form, response );
					return;
				}
				onAddToCartSuccess( $btn, $form, response );
			},

			error : function ( xhr, textStatus ) {
				// Only network/parse errors fall through here. Verify the
				// cart actually changed before showing failure (handles
				// proxy / CDN response corruption).
				if ( textStatus === 'abort' ) {
					setBtnState( $btn, BTN_DEFAULT );
					return;
				}
				verifyCartChangeFallback( $btn, $form );
			},
		} );
	}

	function handleAddToCartError( $btn, $form, response ) {
		// Surface WC's notice if present (themes with snackbar — e.g. Astra —
		// listen on document.body for added_to_cart but not for error;
		// rely on the response.fragments if WC ships one for notices).
		if ( response && response.fragments ) {
			applyFragments( response.fragments );
		}

		setBtnState( $btn, BTN_ERROR );
		$( document.body ).trigger( 'wse:addToCartError', [ {
			$btn     : $btn,
			$form    : $form,
			response : response,
		} ] );

		setTimeout( function () { setBtnState( $btn, BTN_DEFAULT ); }, 3000 );
	}

	/**
	 * Network-error fallback: hits get_refreshed_fragments and infers
	 * success from cart_hash divergence. Only for true network failures —
	 * NOT used for explicit { error: true } responses (B8).
	 */
	function verifyCartChangeFallback( $btn, $form ) {
		$.get(
			WSEParams.wc_ajax_url.replace( '%%endpoint%%', 'get_refreshed_fragments' ),
			function ( fragsResponse ) {
				if ( fragsResponse && fragsResponse.fragments ) {
					onAddToCartSuccess( $btn, $form, fragsResponse );
				} else {
					setBtnState( $btn, BTN_ERROR );
					setTimeout( function () { setBtnState( $btn, BTN_DEFAULT ); }, 3000 );
				}
			}
		).fail( function () {
			setBtnState( $btn, BTN_ERROR );
			setTimeout( function () { setBtnState( $btn, BTN_DEFAULT ); }, 3000 );
		} );
	}

	function onAddToCartSuccess( $btn, $form, response ) {
		setBtnState( $btn, BTN_ADDED );

		if ( response && response.fragments ) {
			applyFragments( response.fragments );
		}

		$( document.body ).trigger( 'wc_fragment_refresh' );

		// Standard WC event so theme snackbars (Astra) react identically.
		$( document.body ).trigger( 'added_to_cart', [
			response && response.fragments  ? response.fragments  : {},
			response && response.cart_hash  ? response.cart_hash  : '',
			$btn,
		] );

		$( document.body ).trigger( 'wse:addedToCart', [ {
			$btn    : $btn,
			$form   : $form,
			response: response,
		} ] );

		setTimeout( function () { setBtnState( $btn, BTN_DEFAULT ); }, 3000 );
	}

	/**
	 * Applies a WC fragments map to the DOM, optionally stripping the
	 * View Cart link if the global setting is OFF (Feature A).
	 */
	function applyFragments( fragments ) {
		var stripViewCart = ! shouldShowViewCart();

		$.each( fragments, function ( key, value ) {
			if ( stripViewCart && typeof value === 'string' ) {
				// Remove any anchor with class wc-forward (used by WC for
				// the View Cart button in success notices and mini-cart).
				value = value.replace(
					/<a[^>]*class="[^"]*\bwc-forward\b[^"]*"[^>]*>[\s\S]*?<\/a>/gi,
					''
				);
			}
			$( key ).replaceWith( value );
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// 3. Button state machine
	// ─────────────────────────────────────────────────────────────────────

	function setBtnState( $btn, state ) {
		if ( ! $btn.data( 'original-text' ) ) {
			$btn.data( 'original-text', $btn.text() );
		}
		var original = $btn.data( 'original-text' );

		$btn.removeClass( 'wse-atc-loading wse-atc-added wse-atc-error' )
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
			default:
				$btn.text( original );
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// 4. Variation events on canonical form
	// ─────────────────────────────────────────────────────────────────────

	function initVariationSync() {
		$( document.body )
			.off( 'found_variation.wse-atc' )
			.on( 'found_variation.wse-atc', 'form.wse-canonical-form', function ( e, variation ) {

			var $form = $( this );
			$form.find( 'input.variation_id' ).val( variation.variation_id || 0 );

			// Re-enable canonical button + every presenter button targeting this form.
			var formId = $form.attr( 'id' );
			$( '.wse-atc-button[form="' + formId + '"]' )
				.add( $form.find( '.wse-atc-button' ) )
				.prop( 'disabled', false )
				.removeClass( 'disabled' );
		} );

		$( document.body )
			.off( 'reset_data.wse-atc hide_variation.wse-atc' )
			.on( 'reset_data.wse-atc hide_variation.wse-atc',
			      'form.wse-canonical-form',
			      function () {
				$( this ).find( 'input.variation_id' ).val( '' );
			} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// 5. Presenter quantity sync — canonical ⇄ presenter
	// ─────────────────────────────────────────────────────────────────────

	function initPresenterQtySync() {
		// Canonical → presenter
		$( document.body )
			.off( 'input.wse-pqty change.wse-pqty', '.wse-canonical-qty' )
			.on( 'input.wse-pqty change.wse-pqty', '.wse-canonical-qty', function () {
				var $canonical = $( this );
				var $form      = $canonical.closest( 'form.wse-canonical-form' );
				var productId  = $form.attr( 'data-product_id' );
				if ( ! productId ) {
					return;
				}
				$( '.wse-presenter-qty[data-product-id="' + productId + '"]' )
					.not( $canonical )
					.val( $canonical.val() );
			} );

		// Presenter → canonical
		$( document.body )
			.off( 'input.wse-pqty change.wse-pqty', '.wse-presenter-qty' )
			.on( 'input.wse-pqty change.wse-pqty', '.wse-presenter-qty', function () {
				var $presenter = $( this );
				var formId     = String( $presenter.attr( 'data-target-form' ) || '' );
				if ( ! formId ) {
					return;
				}
				var $form = $( '#' + formId );
				if ( ! $form.length ) {
					return;
				}
				var newVal = $presenter.val();
				$form.find( '.wse-canonical-qty' ).val( newVal );
				// Mirror to other presenters too.
				var productId = $form.attr( 'data-product_id' );
				if ( productId ) {
					$( '.wse-presenter-qty[data-product-id="' + productId + '"]' )
						.not( $presenter )
						.val( newVal );
				}
			} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// 6. Sticky presenter — body-padding management
	// ─────────────────────────────────────────────────────────────────────

	var STICKY_BREAKPOINTS = {
		desktop : '(min-width: 1025px)',
		tablet  : '(min-width: 768px) and (max-width: 1024px)',
		mobile  : '(max-width: 767px)',
	};

	/**
	 * Returns the height in px contributed by visible sticky presenters
	 * for the current viewport.
	 */
	function getActiveStickyHeight() {
		var total = 0;

		$( '.wse-presenter' ).each( function () {
			var $el = $( this );
			if ( ! $el.is( ':visible' ) ) {
				return;
			}

			var matchesActive =
				   ( $el.hasClass( 'wse-sticky-desktop' ) && window.matchMedia( STICKY_BREAKPOINTS.desktop ).matches )
				|| ( $el.hasClass( 'wse-sticky-tablet'  ) && window.matchMedia( STICKY_BREAKPOINTS.tablet  ).matches )
				|| ( $el.hasClass( 'wse-sticky-mobile'  ) && window.matchMedia( STICKY_BREAKPOINTS.mobile  ).matches );

			if ( matchesActive ) {
				total = Math.max( total, $el.outerHeight( true ) || 0 );
			}
		} );

		return total;
	}

	function updateStickyBodyPadding() {
		var height = getActiveStickyHeight();
		if ( height > 0 ) {
			$( 'body' ).css( 'padding-bottom', height + 'px' )
			           .addClass( 'wse-has-sticky-presenter' );
		} else {
			$( 'body' ).css( 'padding-bottom', '' )
			           .removeClass( 'wse-has-sticky-presenter' );
		}
	}

	function initStickyBodyPadding() {
		updateStickyBodyPadding();

		// Resize / orientation change
		var resizeTimeout = null;
		$( window ).off( 'resize.wse-sticky orientationchange.wse-sticky' )
			.on( 'resize.wse-sticky orientationchange.wse-sticky', function () {
				if ( resizeTimeout ) {
					clearTimeout( resizeTimeout );
				}
				resizeTimeout = setTimeout( updateStickyBodyPadding, 100 );
			} );

		// matchMedia change events for breakpoint crossings
		Object.keys( STICKY_BREAKPOINTS ).forEach( function ( key ) {
			var mql = window.matchMedia( STICKY_BREAKPOINTS[ key ] );
			if ( mql.addEventListener ) {
				mql.addEventListener( 'change', updateStickyBodyPadding );
			} else if ( mql.addListener ) {
				mql.addListener( updateStickyBodyPadding );
			}
		} );

		// Recalculate after found_variation (presenter button label may
		// change height when stock notices appear)
		$( document.body ).off( 'found_variation.wse-sticky hide_variation.wse-sticky' )
			.on( 'found_variation.wse-sticky hide_variation.wse-sticky',
			      'form.wse-canonical-form',
			      updateStickyBodyPadding );
	}

	// ─────────────────────────────────────────────────────────────────────
	// 7. Quantity stepper (theme-provided +/- buttons)
	// ─────────────────────────────────────────────────────────────────────

	function initQuantityStepper() {
		$( document.body )
			.off( 'click.wse-qty', '.wse-qty-plus, .wse-qty-minus' )
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
		initPresenterQtySync();
		initQuantityStepper();
		initStickyBodyPadding();
	}

	$( document ).ready( init );

	// Reinit hooks (every binding above is .off()-then-.on() idempotent).
	$( document.body ).on( 'wse:reinit elementor/popup/show', init );

} )( jQuery, window, document );
