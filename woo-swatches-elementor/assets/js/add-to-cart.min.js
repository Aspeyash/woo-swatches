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
	 *
	 * v1.1.3 — accepts an optional jQuery context (the canonical form or
	 * any descendant of an Add to Cart widget) so the per-widget
	 * `data-show-view-cart` override (Inherit / Yes / No) takes precedence
	 * over the global WSEParams.show_view_cart_link.
	 *
	 * @param {jQuery} [$context] Form or any element inside the widget wrapper.
	 * @returns {boolean}
	 */
	function shouldShowViewCart( $context ) {

		// Per-widget override beats the global setting.
		if ( $context && $context.length ) {
			var $wrap = $context.closest( '.wse-widget-add-to-cart' );
			if ( ! $wrap.length ) {
				// Form linkage via HTML5 form="..." — try matching by form id.
				var formId = $context.attr( 'id' );
				if ( formId ) {
					$wrap = $( '.wse-widget-add-to-cart[data-form-id="' + formId + '"]' ).first();
				}
			}
			if ( $wrap.length ) {
				var setting = String( $wrap.attr( 'data-show-view-cart' ) || 'inherit' ).toLowerCase();
				if ( 'yes' === setting ) { return true; }
				if ( 'no'  === setting ) { return false; }
				// 'inherit' falls through to the global setting.
			}
		}

		if ( window.WSEParams && typeof WSEParams.show_view_cart_link !== 'undefined' ) {
			return WSEParams.show_view_cart_link !== false
				&& WSEParams.show_view_cart_link !== 'no'
				&& WSEParams.show_view_cart_link !== 0;
		}
		return true;
	}

	/**
	 * v1.1.3 — When at least one Add to Cart widget on the page has its
	 * per-widget "Show View Cart Link" override set to "No", strip the
	 * `<a class="wc-forward">View cart</a>` link from any pre-existing
	 * WooCommerce notices already rendered into the DOM (e.g. a notice
	 * persisted in the WC session from a previous page-load add-to-cart
	 * action). Without this, refreshing the page would still show the
	 * View cart link inline next to the button even though the merchant
	 * disabled it on the widget.
	 *
	 * v1.1.4 — Now also fires when the GLOBAL `wse_show_view_cart_link`
	 * setting is off (not just per-widget). Previously the global setting
	 * relied on the server-side `wc_add_to_cart_message_html` filter
	 * alone, which doesn't catch the `<a class="added_to_cart wc-forward">`
	 * link that WooCommerce's own frontend `wc-add-to-cart.js` listener
	 * injects after the button on the `added_to_cart` event.
	 */
	function applyViewCartHiding() {
		var globalHidden       = ! shouldShowViewCart(); // no context → global setting
		var anyPerWidgetHidden = $( '.wse-widget-add-to-cart[data-show-view-cart="no"]' ).length > 0;

		if ( ! globalHidden && ! anyPerWidgetHidden ) {
			return;
		}

		// v1.1.5 — Add the `wse-hide-view-cart` body class so the CSS rule
		// in add-to-cart.css hides any injected link permanently. PHP adds
		// this class when the global toggle is off; JS adds it here for
		// per-widget overrides. Idempotent — addClass is a no-op if already set.
		$( 'body' ).addClass( 'wse-hide-view-cart' );

		// Defense-in-depth: also remove the actual elements so they don't
		// take up DOM space (CSS display:none already does this layout-wise,
		// but this is cleaner for memory and screen reader output).
		$( '.woocommerce-message a.wc-forward, ' +
		   '.woocommerce-info a.wc-forward, ' +
		   '.woocommerce-error a.wc-forward, ' +
		   '.wc-block-components-notice-banner a.wc-forward, ' +
		   '.wse-widget-add-to-cart a.wc-forward, ' +
		   '.wse-widget-add-to-cart .added_to_cart.wc-forward, ' +
		   'a.added_to_cart.wc-forward'
		).remove();
	}

	// v1.1.4 — Backwards-compat alias so existing callers still work.
	var applyPerWidgetViewCartHiding = applyViewCartHiding;

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

			// v1.1.2 (CRITICAL FIX) — Always preventDefault FIRST.
			//
			// In v1.1.1 the early-return guards below ran without calling
			// preventDefault, which let the browser fall through to a native
			// form POST. On Dokan Pro stacks that have the Order Min Max
			// module, Dokan's `validate_add_to_cart()` uses a strict-typed
			// `int $variation_id` parameter; WC passes an empty string when
			// no variation is selected, and Dokan throws a fatal TypeError.
			//
			// Calling preventDefault unconditionally guarantees the form
			// never submits via standard POST regardless of guard outcomes —
			// the worst case is now "click does nothing" (correct behaviour
			// when variation isn't selected) rather than a 500 error page.
			e.preventDefault();

			var $form = $( this );
			// Locate the button used for this submit (event.originalEvent.submitter
			// is the spec-compliant way; fall back to the canonical's primary button).
			var $btn = e.originalEvent && e.originalEvent.submitter
				? $( e.originalEvent.submitter )
				: $form.find( '.wse-atc-button' ).first();

			if ( ! $btn.length || $btn.hasClass( 'disabled' ) || $btn.prop( 'disabled' ) ) {
				return;
			}

			// Variation-required guard. WC's native wc-add-to-cart-variation.js
			// will have already shown its "Please select options" notice via
			// its own submit handler running before this one bubbles up to
			// document.body, so a silent bail here is the right behaviour.
			if ( $form.hasClass( 'variations_form' ) ) {
				var matched = parseInt( $form.find( 'input.variation_id' ).val(), 10 );
				if ( ! matched || isNaN( matched ) ) {
					return;
				}
			}

			submitAtcForm( $btn, $form );
		} );

		// Simple/grouped/external add-to-cart forms keep working via direct
		// form submission — no AJAX intercept (matches v1.0.5 behaviour).
	}

	/**
	 * v1.1.1 — Robust payload assembly.
	 *
	 * v1.1.0 relied on $form.serializeArray() alone. That breaks if the
	 * canonical form is unexpectedly empty (nested inside another form,
	 * orphan-presenter synthesis edge cases, third-party plugins that
	 * mangle the form structure). v1.1.1 builds the payload from explicit
	 * sources so the AJAX request always has the fields WC needs:
	 *   - product_id        (form data attr OR hidden input)
	 *   - variation_id      (input.variation_id OR input[name=variation_id])
	 *   - quantity          (canonical qty OR matching presenter qty)
	 *   - attribute_*       (canonical selects + form= linked selects)
	 *   - serializeArray fields filled in last as a backup
	 */
	function buildAtcPayload( $form ) {
		var productId = $form.attr( 'data-product_id' )
		             || $form.find( 'input[name=product_id]' ).val()
		             || '';
		var variationId = $form.find( 'input.variation_id' ).val()
		             || $form.find( 'input[name=variation_id]' ).val()
		             || 0;

		var quantity = $form.find( 'input.qty:not(.wse-presenter-qty)' ).first().val();
		if ( ! quantity ) {
			// Fallback to a presenter qty input for this product.
			quantity = $( '.wse-presenter-qty[data-product-id="' + productId + '"]' ).first().val();
		}
		if ( ! quantity ) {
			quantity = 1;
		}

		var data = {
			action       : 'woocommerce_add_to_cart',
			'add-to-cart': productId,
			product_id   : productId,
			variation_id : variationId,
			quantity     : quantity,
		};

		// Canonical-form attribute selects.
		$form.find( 'select[name^="attribute_"]' ).each( function () {
			data[ this.name ] = $( this ).val() || '';
		} );

		// HTML5 form="..."-linked selects (e.g. on a presenter widget that
		// targets this canonical form).
		var formId = $form.attr( 'id' );
		if ( formId ) {
			$( 'select[form="' + formId + '"][name^="attribute_"]' ).each( function () {
				data[ this.name ] = $( this ).val() || '';
			} );
		}

		// Backstop: anything else $form.serializeArray() returns that we
		// haven't already captured.
		$form.serializeArray().forEach( function ( field ) {
			if ( ! ( field.name in data ) ) {
				data[ field.name ] = field.value;
			}
		} );

		return data;
	}

	function submitAtcForm( $btn, $form ) {
		setBtnState( $btn, BTN_LOADING );

		// v1.1.1 — Capture cart hash BEFORE the request so multi-vendor
		// verification can detect whether the cart actually changed even
		// if WC reports {error:true}.
		var hashBefore = readCartHash();

		var data = buildAtcPayload( $form );

		$.ajax( {
			type : 'POST',
			url  : WSEParams.wc_ajax_url.replace( '%%endpoint%%', 'add_to_cart' ),
			data : data,

			success : function ( response ) {
				// v1.1.1 — Honest path first: if WC reports success, treat as success.
				if ( response && ! response.error ) {
					onAddToCartSuccess( $btn, $form, response );
					return;
				}

				// WC reports error. If multi-vendor compat is on, verify
				// whether the cart actually changed (Dokan / WCFM pipelines
				// frequently report error while having added the item).
				if ( isMultivendorCompatActive() ) {
					verifyMultivendorCart( $btn, $form, response, hashBefore );
					return;
				}

				// Otherwise surface the real error.
				handleAddToCartError( $btn, $form, response );
			},

			error : function ( xhr, textStatus ) {
				if ( textStatus === 'abort' ) {
					setBtnState( $btn, BTN_DEFAULT );
					return;
				}
				// Network / parse errors → fragment-fetch fallback.
				verifyCartChangeFallback( $btn, $form, hashBefore );
			},
		} );
	}

	/**
	 * v1.1.1 — Reads the current wc_cart_hash from localStorage. WooCommerce's
	 * cart-fragments.js maintains this, so it's the most reliable indicator
	 * of "did the cart change?" we have client-side.
	 */
	function readCartHash() {
		try {
			return ( window.localStorage && localStorage.getItem( 'wc_cart_hash' ) ) || '';
		} catch ( e ) {
			return '';
		}
	}

	/**
	 * v1.1.1 — Multi-vendor compat is active when:
	 *   - WSEParams.multivendor_compat is true, OR
	 *   - the user explicitly forced it on via WC settings (server already
	 *     resolves "auto" → boolean before the param reaches us).
	 */
	function isMultivendorCompatActive() {
		return !! ( window.WSEParams && WSEParams.multivendor_compat );
	}

	/**
	 * v1.1.1 — Verifies the cart state when WC returns {error:true} on a
	 * multi-vendor stack. Fetches fresh fragments, compares cart hashes,
	 * and treats a hash change as success (i.e. the item was added by
	 * Dokan/WCFM/etc. server-side even though their validation pipeline
	 * marked the AJAX request as failed).
	 */
	function verifyMultivendorCart( $btn, $form, originalResponse, hashBefore ) {
		$.get(
			WSEParams.wc_ajax_url.replace( '%%endpoint%%', 'get_refreshed_fragments' ),
			function ( fragsResponse ) {
				var hashAfter = ( fragsResponse && fragsResponse.cart_hash ) || readCartHash() || '';

				if ( hashAfter && hashAfter !== hashBefore ) {
					// Cart genuinely changed. Treat as success.
					onAddToCartSuccess( $btn, $form, fragsResponse );
				} else {
					// Cart didn't change — surface the real error.
					handleAddToCartError( $btn, $form, originalResponse );
				}
			}
		).fail( function () {
			// Couldn't verify — fall back to honest error.
			handleAddToCartError( $btn, $form, originalResponse );
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
	 * success from cart_hash divergence. Used only on network failures.
	 *
	 * v1.1.1 — takes hashBefore so it can compare against a known
	 * pre-request value rather than relying on fragment.cart_hash alone.
	 */
	function verifyCartChangeFallback( $btn, $form, hashBefore ) {
		$.get(
			WSEParams.wc_ajax_url.replace( '%%endpoint%%', 'get_refreshed_fragments' ),
			function ( fragsResponse ) {
				var hashAfter = ( fragsResponse && fragsResponse.cart_hash ) || readCartHash() || '';
				if ( hashAfter && hashAfter !== hashBefore ) {
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
			applyFragments( response.fragments, $form );
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

		// v1.1.1 — toast is fired once via the wse:addedToCart event listener
		// in init() so archive-page adds and canonical-form adds use the
		// same single code path. (Avoids duplicate toasts on canonical adds.)

		setTimeout( function () { setBtnState( $btn, BTN_DEFAULT ); }, 3000 );
	}

	/**
	 * Applies a WC fragments map to the DOM, optionally stripping the
	 * View Cart link if the global setting is OFF (Feature A).
	 *
	 * v1.1.3 — accepts a context jQuery object so the per-widget
	 * "Show View Cart Link" override (data-show-view-cart on the widget
	 * wrapper) is honoured before falling back to the global setting.
	 */
	function applyFragments( fragments, $context ) {
		var stripViewCart = ! shouldShowViewCart( $context );

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

		// v1.1.3 — sweep the page after fragments have been applied so any
		// inline notices that received the new HTML (and any older notices
		// already on the page) get the link removed too.
		// v1.1.4 — applyViewCartHiding() also strips WC's
		// `.added_to_cart.wc-forward` link injection from the DOM.
		if ( stripViewCart ) {
			applyViewCartHiding();
		}
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
				    .text( i18n.added || 'Added to cart' );
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
	// 6. Sticky presenter — body-padding management + scroll-trigger (v1.4.7)
	// ─────────────────────────────────────────────────────────────────────

	var STICKY_BREAKPOINTS = {
		desktop : '(min-width: 1025px)',
		tablet  : '(min-width: 768px) and (max-width: 1024px)',
		mobile  : '(max-width: 767px)',
	};

	/**
	 * v1.4.7 — Returns true if scroll-triggered sticky is enabled for the
	 * current viewport breakpoint. Reads WSEParams.sticky_scroll_trigger
	 * which is set per-device from WC settings.
	 */
	function isScrollTriggerActive() {
		var params = ( window.WSEParams && window.WSEParams.sticky_scroll_trigger ) || {};
		if ( window.matchMedia( STICKY_BREAKPOINTS.desktop ).matches ) {
			return !! params.desktop;
		}
		if ( window.matchMedia( STICKY_BREAKPOINTS.tablet ).matches ) {
			return !! params.tablet;
		}
		if ( window.matchMedia( STICKY_BREAKPOINTS.mobile ).matches ) {
			return !! params.mobile;
		}
		return false;
	}

	/**
	 * v1.4.7 — For scroll-trigger mode, returns true if the customer has
	 * scrolled past the original ATC widget (so the sticky bar should now
	 * appear). The "original" widget is the first non-presenter
	 * .wse-widget-add-to-cart on the page that is NOT itself sticky-active.
	 *
	 * For non-scroll-trigger mode (always-on sticky), this returns true
	 * unconditionally.
	 */
	function shouldStickyBeVisible( $stickyEl ) {
		if ( ! isScrollTriggerActive() ) {
			return true;   // always-on mode
		}

		// Find the canonical (non-sticky-positioned) reference element.
		// Strategy: the original widget is the first .wse-widget-add-to-cart
		// on the page. If a presenter sticky-bar is what we're checking,
		// use the canonical form's container. Otherwise we look for ANY
		// other ATC widget that exists on the page in normal position.
		var $reference = null;

		// Look for a non-presenter ATC widget elsewhere on the page.
		$( '.wse-widget-add-to-cart' ).each( function () {
			var $w = $( this );
			if ( $w[0] === $stickyEl[0] ) { return; }
			if ( $w.hasClass( 'wse-presenter' ) ) { return; }
			$reference = $w;
			return false; // break
		} );

		// If no separate reference widget exists, use the sticky widget
		// itself's natural position (the spot it would occupy if it
		// weren't fixed). We track this by reading data-original-top
		// captured at first init.
		if ( ! $reference ) {
			var origTop = parseFloat( $stickyEl.attr( 'data-wse-original-top' ) );
			if ( isNaN( origTop ) ) {
				return true; // can't determine; fail-open to always-on
			}
			var viewportH = window.innerHeight || document.documentElement.clientHeight;
			return ( window.scrollY + viewportH ) >= origTop;
		}

		// Reference widget exists — show sticky once user scrolls past its
		// bottom edge.
		var refRect = $reference[0].getBoundingClientRect();
		var refBottomDocY = refRect.bottom + window.scrollY;
		var viewportH2 = window.innerHeight || document.documentElement.clientHeight;
		return ( window.scrollY + viewportH2 ) > refBottomDocY;
	}

	/**
	 * Captures the original (natural) top position of each sticky-eligible
	 * widget BEFORE we apply any sticky class. Called once on init.
	 */
	function captureOriginalPositions() {
		$( '.wse-widget-add-to-cart' ).each( function () {
			var $el = $( this );
			if ( $el.attr( 'data-wse-original-top' ) ) { return; }
			// Only capture if widget could ever be sticky.
			var canBeSticky =
				   $el.hasClass( 'wse-sticky-desktop' )
				|| $el.hasClass( 'wse-sticky-tablet' )
				|| $el.hasClass( 'wse-sticky-mobile' );
			if ( ! canBeSticky ) { return; }
			var rect = $el[0].getBoundingClientRect();
			$el.attr( 'data-wse-original-top', String( rect.top + window.scrollY ) );
		} );
	}

	/**
	 * Returns the height in px contributed by visible sticky widgets for
	 * the current viewport. ALSO updates .wse-sticky-active class on each
	 * widget based on:
	 *   1. Whether the breakpoint matches (existing logic)
	 *   2. Whether scroll-trigger conditions are met (v1.4.7)
	 */
	function getActiveStickyHeight() {
		var total = 0;

		$( '.wse-widget-add-to-cart' ).each( function () {
			var $el = $( this );
			if ( ! $el.is( ':visible' ) ) {
				return;
			}

			var breakpointMatches =
				   ( $el.hasClass( 'wse-sticky-desktop' ) && window.matchMedia( STICKY_BREAKPOINTS.desktop ).matches )
				|| ( $el.hasClass( 'wse-sticky-tablet'  ) && window.matchMedia( STICKY_BREAKPOINTS.tablet  ).matches )
				|| ( $el.hasClass( 'wse-sticky-mobile'  ) && window.matchMedia( STICKY_BREAKPOINTS.mobile  ).matches );

			if ( ! breakpointMatches ) {
				$el.removeClass( 'wse-sticky-active' );
				return;
			}

			// Scroll-trigger gate.
			if ( ! shouldStickyBeVisible( $el ) ) {
				$el.removeClass( 'wse-sticky-active' );
				return;
			}

			$el.addClass( 'wse-sticky-active' );
			total = Math.max( total, $el.outerHeight( true ) || 0 );
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
		// v1.4.7 — Capture original positions before any sticky class is
		// applied, so scroll-trigger logic has a reference.
		captureOriginalPositions();

		updateStickyBodyPadding();

		// Resize / orientation change
		var resizeTimeout = null;
		$( window ).off( 'resize.wse-sticky orientationchange.wse-sticky' )
			.on( 'resize.wse-sticky orientationchange.wse-sticky', function () {
				if ( resizeTimeout ) {
					clearTimeout( resizeTimeout );
				}
				resizeTimeout = setTimeout( function () {
					captureOriginalPositions();
					updateStickyBodyPadding();
				}, 100 );
			} );

		// v1.4.7 — Scroll handler (throttled via rAF) for scroll-trigger mode.
		var scrollRaf = null;
		$( window ).off( 'scroll.wse-sticky' )
			.on( 'scroll.wse-sticky', function () {
				if ( scrollRaf ) { return; }
				scrollRaf = window.requestAnimationFrame( function () {
					scrollRaf = null;
					updateStickyBodyPadding();
				} );
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
	// 7. (v1.2.2) Quantity stepper - DEAD CODE REMOVED
	//
	// v1.0.x shipped initQuantityStepper() with theme-provided +/- selectors
	// (.wse-qty-plus / .wse-qty-minus) that no theme actually emits. The
	// function registered click handlers that NEVER FIRED because the
	// selectors didn't match anything. The real stepper logic lives in
	// initQtyStepper() further down (uses the correct .wse-qty-btn--minus
	// / .wse-qty-btn--plus selectors).
	//
	// Removed in v1.2.2 after a senior-developer code review caught the
	// dead-code namespace pollution. See the v1.2.2 changelog for the
	// full diagnosis.
	// ─────────────────────────────────────────────────────────────────────

	// ─────────────────────────────────────────────────────────────────────
	// 7.1  v1.1.1 — "Added to cart" toast notification
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Shows a small bottom-right toast confirming that the product was
	 * added to the cart. Auto-dismisses after ~3.5 seconds. Toggleable
	 * server-side via the wse_show_added_toast option (Display tab).
	 *
	 * Respects the View Cart link toggle: when "Show View Cart Link" is
	 * disabled, the toast is just the message; when enabled, a small
	 * "View cart" link is appended.
	 */
	function showAddedToast( $context ) {
		// Server-side toggle off → do nothing.
		if ( window.WSEParams && WSEParams.show_added_toast === false ) {
			return;
		}

		var i18n = ( window.WSEParams && WSEParams.i18n ) ? WSEParams.i18n : {};
		var msg  = i18n.toast_added || 'Added to cart';

		// Remove any existing toast so we don't stack them on rapid clicks.
		$( '.wse-toast' ).remove();

		var $toast = $( '<div/>', {
			'class'    : 'wse-toast',
			'role'     : 'status',
			'aria-live': 'polite',
		} );

		$( '<span/>', { 'class': 'wse-toast-icon', 'aria-hidden': 'true' } )
			.text( '\u2713' )
			.appendTo( $toast );

		$( '<span/>', { 'class': 'wse-toast-message' } )
			.text( msg )
			.appendTo( $toast );

		// v1.1.3 — Optional View Cart link. Respects the per-widget
		// override before the global wse_show_view_cart_link toggle.
		if ( shouldShowViewCart( $context ) && window.WSEParams && WSEParams.cart_url ) {
			$( '<a/>', {
				'class' : 'wse-toast-link',
				'href'  : WSEParams.cart_url,
				'text'  : i18n.view_cart || 'View cart',
			} ).appendTo( $toast );
		}

		$( 'body' ).append( $toast );

		// Force reflow before adding the visible class so the CSS transition fires.
		$toast[0].offsetHeight; // eslint-disable-line no-unused-expressions
		$toast.addClass( 'wse-toast--visible' );

		// Auto-dismiss after 3.5s.
		setTimeout( function () {
			$toast.removeClass( 'wse-toast--visible' );
			setTimeout( function () { $toast.remove(); }, 400 );
		}, 3500 );
	}



	function init() {
		initCrossWidgetSync();
		initAjaxAddToCart();
		initVariationSync();
		initPresenterQtySync();
		// v1.2.2 — initQuantityStepper() removed (dead code, see comment block above)
		initStickyBodyPadding();

		// v1.1.3 — sweep persisted WC notices on page load so the per-widget
		// "Show View Cart Link = No" override hides links that are already
		// in the DOM from a previous page-load add-to-cart action.
		applyViewCartHiding();

		// v1.1.4 — Strip WC's client-side <a class="added_to_cart wc-forward">
		// link AFTER WooCommerce's own wc-add-to-cart.js listener has injected
		// it on the added_to_cart event. setTimeout(0) lets WC's listener
		// finish first, then we remove the link on the next tick.
		$( document.body )
			.off( 'added_to_cart.wse-strip-view-cart' )
			.on( 'added_to_cart.wse-strip-view-cart', function ( e, fragments, cart_hash, $button ) {
				setTimeout( function () {
					applyViewCartHiding();
				}, 0 );
			} );

		// v1.1.1 — Toast on archive add-to-cart success.
		// onAddToCartSuccess() calls showAddedToast() directly for the
		// canonical-form path. The archive path also fires wse:addedToCart
		// (via class-archive-swatches.php inline JS), so we listen to it
		// here to reach the toast for archive adds too. Idempotent off/on.
		$( document.body )
			.off( 'wse:addedToCart.wse-toast' )
			.on( 'wse:addedToCart.wse-toast', function ( e, data ) {
				// v1.1.3 — pass form context so per-widget View Cart toggle wins.
				showAddedToast( data && data.$form ? data.$form : null );
			} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// v1.2.0 — Quantity stepper [-] [qty] [+]
	//
	// Delegated click handlers on the document so the buttons work on
	// any stepper present at any time — including steppers added later
	// by Elementor's editor or AJAX-loaded fragments. Each click reads
	// the input's current value, applies the step (clamped to min/max),
	// writes the new value, fires a 'change' event so WC's variation
	// matcher and any listening cart logic picks up the update, and
	// then re-evaluates the disabled state of both buttons.
	// ─────────────────────────────────────────────────────────────────────

	function clamp( value, min, max ) {
		var v = parseFloat( value );
		if ( isNaN( v ) ) { v = parseFloat( min ) || 0; }
		if ( ! isNaN( min ) && v < min ) { v = min; }
		if ( ! isNaN( max ) && '' !== max && v > max ) { v = max; }
		return v;
	}

	function readBounds( $input ) {
		// min / max / step come from the input's HTML attributes — WC's
		// variation matcher updates these via found_variation when a
		// variation imposes its own per-variation min_qty / max_qty.
		var min  = parseFloat( $input.attr( 'min' ) );
		var max  = $input.attr( 'max' );
		var step = parseFloat( $input.attr( 'step' ) );
		// v1.2.3 (Issue 1a) — Treat WC's -1 ("no max") sentinel and
		// missing-attr / null / undefined as "no max". Defensive in case
		// the template-level guard misses an edge case.
		var hasMax = ( '' !== max
			&& 'undefined' !== typeof max
			&& null !== max
			&& '-1' !== String( max ).trim() );
		return {
			min:  isNaN( min )  ? 0  : min,
			max:  hasMax ? parseFloat( max ) : '',
			step: isNaN( step ) || step <= 0 ? 1 : step,
		};
	}

	function updateStepperButtons( $stepper ) {
		var $input = $stepper.find( 'input.qty' ).first();
		if ( ! $input.length ) { return; }

		var b   = readBounds( $input );
		var val = parseFloat( $input.val() );

		var $minus = $stepper.find( '.wse-qty-btn--minus' );
		var $plus  = $stepper.find( '.wse-qty-btn--plus' );

		$minus.prop( 'disabled', isNaN( val ) || val <= b.min );
		$plus.prop( 'disabled', '' !== b.max && ! isNaN( val ) && val >= b.max );
	}

	function changeStepperValue( $stepper, direction ) {
		var $input = $stepper.find( 'input.qty' ).first();
		if ( ! $input.length ) { return; }

		var b      = readBounds( $input );
		var raw    = parseFloat( $input.val() );
		var current = isNaN( raw ) ? b.min : raw;
		var next   = current + ( direction * b.step );
		var clamped = clamp( next, b.min, b.max );

		// No-op when already at the bound — avoids spurious 'change' events.
		if ( clamped === current ) { return; }

		$input.val( clamped ).trigger( 'change' );
		updateStepperButtons( $stepper );
	}

	function initQtyStepper() {
		// Initial pass: set disabled state for every stepper on the page.
		$( '.wse-qty-stepper' ).each( function () {
			updateStepperButtons( $( this ) );
		} );

		// Delegated click handlers (work on dynamically added steppers).
		$( document )
			.off( 'click.wseQtyStepper' )
			.on( 'click.wseQtyStepper', '.wse-qty-btn--minus', function ( e ) {
				e.preventDefault();
				changeStepperValue( $( this ).closest( '.wse-qty-stepper' ), -1 );
			} )
			.on( 'click.wseQtyStepper', '.wse-qty-btn--plus', function ( e ) {
				e.preventDefault();
				changeStepperValue( $( this ).closest( '.wse-qty-stepper' ), +1 );
			} );

		// Manual entry: re-clamp on blur and refresh button states.
		$( document )
			.off( 'change.wseQtyStepper input.wseQtyStepper' )
			.on( 'change.wseQtyStepper input.wseQtyStepper', '.wse-qty-stepper input.qty', function () {
				var $stepper = $( this ).closest( '.wse-qty-stepper' );
				updateStepperButtons( $stepper );
			} );

		// Variable products: WC's wc-add-to-cart-variation.js may update
		// the input's min/max attributes when a variation is matched.
		// Re-evaluate the disabled state on those events too.
		$( document )
			.off( 'found_variation.wseQtyStepper reset_data.wseQtyStepper' )
			.on( 'found_variation.wseQtyStepper reset_data.wseQtyStepper', 'form.variations_form, .wse-canonical-form', function () {
				var $form = $( this );
				$form.find( '.wse-qty-stepper' ).each( function () {
					updateStepperButtons( $( this ) );
				} );
			} );
	}

	// v1.2.1 (B1) — Replaced the init-reassignment hack with a clean
	// composite handler. Reassigning a function declaration (function init(){})
	// to a function expression (init = function(){}) was technically valid in
	// strict mode but fragile across browsers / minifiers and made the
	// stepper handlers silently miss on simple-product pages where the timing
	// of the IIFE / WC core scripts differs slightly from variable products.
	function initAll() {
		init();
		initQtyStepper();
		initBuyNow();
	}

	// ─────────────────────────────────────────────────────────────────────
	// v1.4.5 — Buy Now button logic
	//
	// Handles:
	//  - Variation form integration (enable/disable on found_variation/reset_data)
	//  - Quantity input syncing from the page's qty input
	//  - AJAX request to store product in session + redirect to checkout
	//
	// The existing cart is NEVER modified on the client — the server swaps
	// it temporarily on the checkout page and restores it after purchase.
	// ─────────────────────────────────────────────────────────────────────

	var BUY_NOW_BTN = '.wse-buy-now-btn';

	function initBuyNow() {
		// Skip if no Buy Now buttons on the page.
		if ( ! $( BUY_NOW_BTN ).length ) {
			return;
		}

		bindBuyNowVariationForms();

		// Delegate click (idempotent — .off then .on).
		$( document.body )
			.off( 'click.wseBuyNow', BUY_NOW_BTN )
			.on( 'click.wseBuyNow', BUY_NOW_BTN, onBuyNowClick );
	}

	function bindBuyNowVariationForms() {
		$( 'form.variations_form' ).each( function () {
			var $form = $( this );
			if ( $form.data( 'wse-bnw-bound' ) ) {
				return;
			}
			$form.data( 'wse-bnw-bound', true );

			var productId = $form.data( 'product_id' ) || $form.attr( 'data-product_id' );

			$form
				.on( 'found_variation.wseBuyNow', function ( e, variation ) {
					updateBuyNowButtons( productId, {
						variationId: variation.variation_id,
						purchasable: variation.is_purchasable && variation.is_in_stock,
						attributes:  collectBuyNowAttributes( $form )
					} );
				} )
				.on( 'reset_data.wseBuyNow hide_variation.wseBuyNow', function () {
					updateBuyNowButtons( productId, {
						variationId: 0,
						purchasable: false,
						attributes:  {}
					} );
				} );
		} );
	}

	function collectBuyNowAttributes( $form ) {
		var attrs = {};
		$form.find( '.variations select, select[data-attribute_name]' ).each( function () {
			var name = $( this ).data( 'attribute_name' ) || $( this ).attr( 'name' );
			if ( name ) {
				attrs[ name ] = $( this ).val() || '';
			}
		} );
		return attrs;
	}

	function updateBuyNowButtons( productId, state ) {
		$( BUY_NOW_BTN + '[data-product-id="' + productId + '"]' ).each( function () {
			var $btn = $( this );
			$btn.data( 'variation-id', state.variationId || 0 );
			$btn.data( 'variation-attrs', state.attributes || {} );

			if ( state.variationId && state.purchasable ) {
				$btn.prop( 'disabled', false )
					.removeAttr( 'disabled' )
					.removeAttr( 'aria-disabled' )
					.removeClass( 'wse-buy-now-disabled' );
			} else if ( $btn.attr( 'data-needs-options' ) ) {
				$btn.prop( 'disabled', true )
					.attr( 'aria-disabled', 'true' )
					.addClass( 'wse-buy-now-disabled' );
			}
		} );
	}

	function onBuyNowClick( e ) {
		e.preventDefault();
		e.stopPropagation();

		var $btn = $( this );
		if ( $btn.prop( 'disabled' ) || $btn.hasClass( 'wse-buy-now-loading' ) ) {
			return;
		}

		var bnwParams = ( window.WSEParams && window.WSEParams.buy_now ) || {};
		var i18n      = bnwParams.i18n || {};
		var nonce     = bnwParams.nonce || '';
		var ajaxUrl   = ( window.WSEParams && window.WSEParams.ajax_url ) || '';

		if ( ! ajaxUrl || ! nonce ) {
			return;
		}

		var productId   = parseInt( $btn.data( 'product-id' ), 10 ) || 0;
		var productType = $btn.data( 'product-type' ) || 'simple';
		var variationId = parseInt( $btn.data( 'variation-id' ), 10 ) || 0;
		var attributes  = $btn.data( 'variation-attrs' ) || {};
		var quantity    = parseInt( $btn.data( 'quantity' ), 10 ) || 1;

		if ( ! productId ) {
			showBuyNowMessage( $btn, i18n.error || 'Error', 'error' );
			return;
		}

		// Sync with the page's quantity input.
		var $qtyInput = findBuyNowQtyInput( $btn );
		if ( $qtyInput.length && $qtyInput.val() ) {
			quantity = Math.max( 1, parseInt( $qtyInput.val(), 10 ) || 1 );
		}

		// Variable product but no variation selected.
		if ( productType === 'variable' && ! variationId ) {
			showBuyNowMessage( $btn, i18n.select_options || 'Please select options', 'error' );
			return;
		}

		setBuyNowLoading( $btn, true, i18n );
		clearBuyNowMessage( $btn );

		$.ajax( {
			url:      ajaxUrl,
			type:     'POST',
			dataType: 'json',
			data: {
				action:       'wse_buy_now',
				nonce:        nonce,
				product_id:   productId,
				variation_id: variationId,
				variation:    attributes,
				quantity:     quantity
			}
		} )
		.done( function ( response ) {
			if ( response && response.success && response.data && response.data.redirect ) {
				$( document.body ).trigger( 'wse_buy_now_before_redirect', [ response, $btn ] );
				window.location.href = response.data.redirect;
				return;
			}
			var msg = ( response && response.data && response.data.message ) || i18n.error || 'Error';
			showBuyNowMessage( $btn, msg, 'error' );
			setBuyNowLoading( $btn, false, i18n );
		} )
		.fail( function () {
			showBuyNowMessage( $btn, i18n.error || 'Error', 'error' );
			setBuyNowLoading( $btn, false, i18n );
		} );
	}

	function findBuyNowQtyInput( $btn ) {
		// Look for the quantity input in the same widget / form context.
		var $widget = $btn.closest( '.wse-widget-add-to-cart' );
		if ( $widget.length ) {
			var $input = $widget.find( 'input.qty' ).first();
			if ( $input.length ) {
				return $input;
			}
		}
		// Fallback: any qty input on the page.
		return $( 'form.cart input.qty, form.variations_form input.qty' ).first();
	}

	function setBuyNowLoading( $btn, loading, i18n ) {
		if ( loading ) {
			$btn.addClass( 'wse-buy-now-loading' ).prop( 'disabled', true );
			$btn.find( '.wse-buy-now-text' ).text( i18n.processing || 'Processing...' );
		} else {
			$btn.removeClass( 'wse-buy-now-loading' );
			if ( $btn.attr( 'data-needs-options' ) && ! parseInt( $btn.data( 'variation-id' ), 10 ) ) {
				$btn.prop( 'disabled', true );
			} else {
				$btn.prop( 'disabled', false );
			}
			var defaultText = $btn.attr( 'data-default-text' );
			if ( defaultText ) {
				$btn.find( '.wse-buy-now-text' ).text( defaultText );
			}
		}
	}

	function showBuyNowMessage( $btn, text, type ) {
		var $msg = $btn.closest( '.wse-buy-now-wrap' ).find( '.wse-buy-now-message' );
		$msg.removeClass( 'wse-bnw-msg-error wse-bnw-msg-success' )
			.addClass( 'wse-bnw-msg-' + ( type || 'info' ) )
			.text( text );
	}

	function clearBuyNowMessage( $btn ) {
		$btn.closest( '.wse-buy-now-wrap' ).find( '.wse-buy-now-message' ).text( '' );
	}

	$( document ).ready( initAll );

	// Reinit hooks (every binding above is .off()-then-.on() idempotent).
	$( document.body ).on( 'wse:reinit elementor/popup/show', initAll );

	// If a variation form is added later (Elementor editor, AJAX load).
	$( document.body ).on( 'wc_variation_form', function () {
		bindBuyNowVariationForms();
	} );

} )( jQuery, window, document );
