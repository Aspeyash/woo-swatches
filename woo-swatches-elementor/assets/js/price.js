/**
 * WooSwatches for Elementor — ZYMARG Price Widget JS (v1.3.2 surgical update model)
 *
 * Subscribes each .zymarg-price[data-form-id] widget on the page to the
 * canonical form's `found_variation` and `reset_data` events fired by
 * wc-add-to-cart-variation.js, and re-renders the price in place when
 * the customer picks a variation via Widget 1 (Variation Swatches).
 *
 * v1.3.2 architecture change — surgical DOM updates instead of $widget.html():
 *
 *   The previous string-based `buildInnerHtml()` model wiped the entire
 *   widget's inner HTML on every variation event. That destroyed sibling
 *   elements that the server rendered alongside the price block:
 *
 *     - .zymarg-price-heading       (smart heading: "Limited Time Offer")
 *     - .zymarg-price-shipping-hint (free-shipping hint)
 *
 *   In particular, on variable on-sale products the server-side render
 *   correctly emitted the heading, but `reset_data` (which WC fires
 *   during form init even before any user interaction) ran our
 *   `restoreInitial()` -> `$widget.html(html)` chain, blowing the
 *   heading away. Symptom: heading flashes during page load, then
 *   disappears as soon as JS hydrates.
 *
 *   v1.3.2 fixes this by REMOVING only the price-specific child
 *   elements (.zymarg-price-current / -was / -from / -sep / -range,
 *   .zymarg-sale-badge, .zymarg-price-savings) and INSERTING new price
 *   elements after the heading. Heading + shipping hint stay untouched
 *   across every variation switch.
 *
 * Multiple Widget 3 instances on the same page (e.g. main column + sticky
 * cart bar + gallery sidebar) are all bound to the same canonical form
 * by data-form-id, so a single variation event updates every visible
 * price widget for that product simultaneously.
 *
 * Currency formatting mirrors WooCommerce's wc_price() output exactly:
 * decimals, decimal separator, thousand separator, currency symbol, and
 * currency position are all read from WSEParams (localized in PHP via
 * the wse_frontend_params filter).
 *
 * @package WooSwatchesElementor
 * @since   1.2.0
 */
( function ( $, WSEParams ) {
	'use strict';

	/**
	 * Format a numeric amount as a localised currency string,
	 * mimicking the output of PHP's wc_price() for the same value.
	 *
	 * @param {number|string} amount  Raw price (e.g. 1400 or "1400.00").
	 * @returns {string}              Formatted price (e.g. "1,400.00৳").
	 */
	function formatPrice( amount ) {

		var num = parseFloat( amount );
		if ( isNaN( num ) ) {
			return '';
		}

		var p = WSEParams.price || {};
		var decimals      = parseInt( p.decimals, 10 );
		var thousandSep   = p.thousand_sep || ',';
		var decimalSep    = p.decimal_sep  || '.';
		var symbol        = p.currency_symbol || '';
		var position      = p.currency_pos    || 'left';

		if ( isNaN( decimals ) || decimals < 0 ) {
			decimals = 2;
		}

		// 1. Fix to N decimals.
		var fixed = num.toFixed( decimals );

		// 2. Insert thousand separators on the integer portion only.
		var parts   = fixed.split( '.' );
		parts[ 0 ]  = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, thousandSep );
		var joined  = parts.length > 1 ? parts[ 0 ] + decimalSep + parts[ 1 ] : parts[ 0 ];

		// 3. Apply currency position.
		switch ( position ) {
			case 'right':       return joined + symbol;
			case 'left_space':  return symbol + ' ' + joined;
			case 'right_space': return joined + ' ' + symbol;
			case 'left':
			default:            return symbol + joined;
		}
	}

	/**
	 * Build the "savings" line text from sale + regular prices and the
	 * widget's data-savings-format setting.
	 *
	 * @param {jQuery} $widget
	 * @param {number} sale
	 * @param {number} regular
	 * @returns {string}  '' if savings are disabled or zero.
	 */
	function buildSavingsText( $widget, sale, regular ) {

		if ( '1' !== String( $widget.data( 'savings-show' ) ) ) {
			return '';
		}
		if ( ! ( regular > sale ) ) {
			return '';
		}

		var saveAmt = sale > 0 ? ( regular - sale ) : 0;
		var savePct = regular > 0 ? Math.round( ( saveAmt / regular ) * 100 ) : 0;
		var prefix  = $widget.data( 'savings-prefix' ) || 'Save';
		var fmt     = $widget.data( 'savings-format' ) || 'amount_percent';

		switch ( fmt ) {
			case 'amount_only':
				return prefix + ' ' + formatPrice( saveAmt );
			case 'percent_only':
				return prefix + ' ' + savePct + '%';
			default:
				return prefix + ' ' + formatPrice( saveAmt ) + ' (' + savePct + '%)';
		}
	}

	/**
	 * v1.3.2 — Surgically update the price block of a widget.
	 *
	 * Removes the price-specific child elements (current / was / range /
	 * from / sep / sale-badge / savings) and inserts new ones, while
	 * preserving any sibling elements the server rendered (heading,
	 * shipping hint, etc.). Insertion point is AFTER the heading if
	 * present, otherwise at the start of the widget.
	 *
	 * Uses jQuery's `.text()` for text content so currency strings are
	 * auto-escaped.
	 *
	 * @param {jQuery} $widget
	 * @param {Object} parts
	 * @param {string} parts.currentHtml     Already-formatted current/sale price text.
	 * @param {string} parts.regularHtml     Already-formatted regular price text ('' if no sale).
	 * @param {boolean} parts.isOnSale
	 * @param {string} parts.regularPosition subscript | beside | below | hide
	 * @param {string} parts.savingsText     '' if disabled or no savings.
	 * @param {boolean} [animate]            v1.5.0 (C1) — when true, the freshly
	 *                                       inserted price elements get a one-shot
	 *                                       animation class (fade/slide) read from
	 *                                       the widget's data-price-anim attribute.
	 */
	function applyPriceState( $widget, parts, animate ) {

		// 1. Remove all dynamic price-block elements that the server may
		//    have rendered (range / current / was / from / sep) plus any
		//    pre-existing badge / savings spans we previously injected.
		//    Heading (.zymarg-price-heading) and shipping hint
		//    (.zymarg-price-shipping-hint) are left in place.
		$widget.find(
			'.zymarg-price-range, ' +
			'.zymarg-price-current, ' +
			'.zymarg-price-was, ' +
			'.zymarg-price-from, ' +
			'.zymarg-price-sep, ' +
			'.zymarg-sale-badge, ' +
			'.zymarg-price-savings'
		).remove();

		// 2. Build the new elements.
		var $newGroup = $();

		var $current = $( '<span class="zymarg-price-current"></span>' ).text( parts.currentHtml );
		$newGroup = $newGroup.add( $current );

		if ( parts.isOnSale && 'hide' !== parts.regularPosition && parts.regularHtml ) {
			var posClass = 'zymarg-price-was--' + parts.regularPosition;
			var $was;
			if ( 'subscript' === parts.regularPosition ) {
				$was = $( '<del class="zymarg-price-was ' + posClass + '"><sub></sub></del>' );
				$was.find( 'sub' ).text( parts.regularHtml );
			} else {
				$was = $( '<del class="zymarg-price-was ' + posClass + '"></del>' ).text( parts.regularHtml );
			}
			$newGroup = $newGroup.add( $was );
		}

		if ( parts.savingsText ) {
			var $savings = $( '<span class="zymarg-price-savings"></span>' ).text( parts.savingsText );
			$newGroup = $newGroup.add( $savings );
		}

		// v1.5.0 (C1) — Apply a one-shot animation class to the freshly
		// built elements. Because these nodes are brand-new, the CSS
		// animation plays once on insert with no cleanup needed (the next
		// applyPriceState call removes + recreates them, giving a fresh
		// animation). The OS "reduce motion" setting is honoured purely in
		// CSS, so we don't gate on it here. 'none' = no class = no motion.
		if ( animate ) {
			var animMode = String( $widget.data( 'price-anim' ) || 'fade' );
			if ( 'none' !== animMode ) {
				$newGroup.addClass( 'zymarg-price-anim-' + animMode );
			}
		}

		// 3. Insert the group: AFTER heading if present, else at start.
		var $heading = $widget.find( '.zymarg-price-heading' ).first();
		if ( $heading.length ) {
			$heading.after( $newGroup );
		} else {
			$widget.prepend( $newGroup );
		}

		// 4. Toggle the on-sale wrapper class.
		$widget.toggleClass( 'zymarg-price--on-sale', !! parts.isOnSale );
	}

	/**
	 * Re-render a price widget for a specific variation.
	 *
	 * @param {jQuery} $widget
	 * @param {Object} variation  WC variation payload (from `found_variation`).
	 */
	function renderVariation( $widget, variation ) {

		var sale     = parseFloat( variation.display_price );
		var regular  = parseFloat( variation.display_regular_price );
		var isOnSale = ! isNaN( sale ) && ! isNaN( regular ) && regular > sale;

		applyPriceState( $widget, {
			currentHtml:     formatPrice( sale ),
			regularHtml:     formatPrice( regular ),
			isOnSale:        isOnSale,
			regularPosition: $widget.data( 'regular-position' ) || 'subscript',
			savingsText:     buildSavingsText( $widget, sale, regular ),
		}, true ); // v1.5.0 (C1) — a variation pick is always an explicit
		           // user action, so animate the price change.

		$widget.attr( 'data-variation-id', variation.variation_id || '' );
	}

	/**
	 * Restore a price widget to its initial baseline (the lowest-active
	 * + lowest-regular state from the server-side render). Triggered on
	 * the canonical form's `reset_data` event, which WC fires during
	 * form init *and* when the customer clears their swatch selection.
	 *
	 * v1.3.2 — Heading + shipping hint are preserved (see applyPriceState).
	 *
	 * @param {jQuery}  $widget
	 * @param {boolean} [animate]  v1.5.0 (C1) — animate the revert. Passed
	 *                             false for the init-time reset_data (which
	 *                             WC fires during form hydration before any
	 *                             user interaction) so the price doesn't
	 *                             flash-animate on page load; true for a
	 *                             user-initiated "clear selection".
	 */
	function restoreInitial( $widget, animate ) {

		var initialCurrent = $widget.data( 'initial-current' ) || '';
		var initialRegular = $widget.data( 'initial-regular' ) || '';
		var initialOnSale  = '1' === String( $widget.data( 'initial-on-sale' ) );

		// For initial-state savings: derive from data attributes if a
		// numeric pair was localised. We don't always have these so we
		// keep savings to '' when reverting (the heading carries the
		// sale-state messaging).
		var initialSale    = parseFloat( $widget.data( 'initial-sale-amt' ) );
		var initialReg     = parseFloat( $widget.data( 'initial-regular-amt' ) );
		var initialSavings = '';
		if ( initialOnSale && ! isNaN( initialSale ) && ! isNaN( initialReg ) ) {
			initialSavings = buildSavingsText( $widget, initialSale, initialReg );
		}

		applyPriceState( $widget, {
			currentHtml:     initialCurrent,
			regularHtml:     initialRegular,
			isOnSale:        initialOnSale,
			regularPosition: $widget.data( 'regular-position' ) || 'subscript',
			savingsText:     initialSavings,
		}, !! animate );

		$widget.removeAttr( 'data-variation-id' );
	}

	/**
	 * Bind a single price widget to its canonical form.
	 *
	 * @param {jQuery} $widget
	 */
	function bindWidget( $widget ) {

		var formId = $widget.data( 'form-id' );
		if ( ! formId ) {
			return; // simple product or no canonical form on page
		}

		var $form = $( '#' + formId );

		// If the canonical form isn't on the page yet (e.g. Widget 2 not
		// placed), poll briefly — Form Registry may render late on some
		// themes that defer Elementor shortcodes. Give up after 2 seconds.
		if ( ! $form.length ) {
			var attempts = 0;
			var poll = setInterval( function () {
				$form = $( '#' + formId );
				if ( $form.length || ++attempts >= 20 ) {
					clearInterval( poll );
					if ( $form.length ) {
						attachListeners( $widget, $form );
					}
				}
			}, 100 );
			return;
		}

		attachListeners( $widget, $form );
	}

	/**
	 * Attach the variation listeners to a widget+form pair.
	 *
	 * @param {jQuery} $widget
	 * @param {jQuery} $form
	 */
	function attachListeners( $widget, $form ) {

		// Avoid double-binding if Elementor re-renders the widget in
		// editor mode.
		if ( $widget.data( 'wse-bound' ) ) {
			return;
		}
		$widget.data( 'wse-bound', true );

		// v1.2.1 (P4) — Loading skeleton during variation lookup.
		// Toggle on any select change in the canonical form; removed on
		// found_variation/reset_data once the new state is known.
		var skeletonEnabled = '1' === String( $widget.data( 'skeleton-enabled' ) );

		if ( skeletonEnabled ) {
			$form.on( 'change.wsePriceSkeleton', 'select', function () {
				$widget.addClass( 'zymarg-price--loading' );
			} );
		}

		$form.on( 'found_variation.wsePrice', function ( e, variation ) {
			if ( skeletonEnabled ) {
				$widget.removeClass( 'zymarg-price--loading' );
			}
			if ( variation && 'undefined' !== typeof variation.variation_id ) {
				renderVariation( $widget, variation );
			}
		} );

		// v1.5.0 (C1) — WC fires reset_data once during form hydration
		// (before any user interaction). We suppress the animation on that
		// first init-time reset so the price doesn't flash-animate on page
		// load, and animate every subsequent (user-initiated) clear.
		var initialResetSeen = false;

		$form.on( 'reset_data.wsePrice', function () {
			if ( skeletonEnabled ) {
				$widget.removeClass( 'zymarg-price--loading' );
			}
			restoreInitial( $widget, initialResetSeen );
			initialResetSeen = true;
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// DOMReady — bind every Widget 3 instance on the page.
	// ─────────────────────────────────────────────────────────────────────

	$( function () {

		$( '.zymarg-price[data-form-id]' ).each( function () {
			bindWidget( $( this ) );
		} );

		// Elementor editor: the widget can be added/duplicated/moved at
		// runtime. Re-bind when the preview is rebuilt.
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction(
				'frontend/element_ready/zymarg-price.default',
				function ( $scope ) {
					bindWidget( $scope.find( '.zymarg-price[data-form-id]' ).first() );
				}
			);
		}
	} );

} )( jQuery, window.WSEParams || {} );
