/**
 * WooSwatches for Elementor — ZYMARG Price Widget JS (v1.2.0)
 *
 * Subscribes each .zymarg-price[data-form-id] widget on the page to the
 * canonical form's `found_variation` and `reset_data` events fired by
 * wc-add-to-cart-variation.js, and re-renders the price in place when
 * the customer picks a variation via Widget 1 (Variation Swatches).
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
 * Sale formatting (option (ii) per ZYMARG product spec):
 *   When the variation's display_price < display_regular_price, the
 *   regular price is rendered next to the current price in whichever
 *   position the user picked (subscript / beside / below). The widget's
 *   data-show-sale-badge attribute decides whether the Sale badge is
 *   appended.
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
	 * Build the inner HTML for a price widget given the formatted current
	 * price, optional regular price, and on-sale state. Mirrors the markup
	 * produced by templates/price/{simple,variable}.php exactly so the DOM
	 * shape stays identical between server and client renders.
	 *
	 * @param {Object} parts
	 * @param {string} parts.currentHtml   Already-formatted current/sale price.
	 * @param {string} parts.regularHtml   Already-formatted regular price ('' if no sale).
	 * @param {boolean} parts.isOnSale
	 * @param {string} parts.regularPosition  subscript | beside | below | hide
	 * @param {boolean} parts.showSaleBadge
	 * @param {string} parts.saleBadgeText
	 * @returns {string}                   HTML to set on .zymarg-price.
	 */
	function buildInnerHtml( parts ) {

		var html = '<span class="zymarg-price-current">'
			+ escapeHtml( parts.currentHtml )
			+ '</span>';

		if ( parts.isOnSale && 'hide' !== parts.regularPosition && parts.regularHtml ) {

			var posClass = 'zymarg-price-was--' + parts.regularPosition;

			if ( 'subscript' === parts.regularPosition ) {
				html += '<del class="zymarg-price-was ' + posClass + '">'
					+ '<sub>' + escapeHtml( parts.regularHtml ) + '</sub>'
					+ '</del>';
			} else {
				html += '<del class="zymarg-price-was ' + posClass + '">'
					+ escapeHtml( parts.regularHtml )
					+ '</del>';
			}
		}

		if ( parts.isOnSale && parts.showSaleBadge ) {
			html += '<span class="zymarg-sale-badge">'
				+ escapeHtml( parts.saleBadgeText )
				+ '</span>';
		}

		return html;
	}

	/**
	 * Minimal HTML-escaper for the values we control upstream (currency
	 * formatter output, sale badge label). Defensive against the rare
	 * case where a developer overrides decimal/thousand sep with a
	 * markup-bearing value via filter.
	 *
	 * @param {string} s
	 * @returns {string}
	 */
	function escapeHtml( s ) {
		return String( s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	/**
	 * Re-render a price widget for a specific variation.
	 *
	 * @param {jQuery} $widget
	 * @param {Object} variation  WC variation payload (from `found_variation`).
	 */
	function renderVariation( $widget, variation ) {

		var sale    = parseFloat( variation.display_price );
		var regular = parseFloat( variation.display_regular_price );
		var isOnSale = ! isNaN( sale ) && ! isNaN( regular ) && regular > sale;

		var html = buildInnerHtml( {
			currentHtml:     formatPrice( sale ),
			regularHtml:     formatPrice( regular ),
			isOnSale:        isOnSale,
			regularPosition: $widget.data( 'regular-position' ) || 'subscript',
			showSaleBadge:   '1' === String( $widget.data( 'show-sale-badge' ) ),
			saleBadgeText:   $widget.data( 'sale-badge-text' ) || 'Sale',
		} );

		// v1.2.1 (P1) — append savings span if enabled and on sale.
		if ( isOnSale && '1' === String( $widget.data( 'savings-show' ) ) ) {
			var saveAmt = sale > 0 ? ( regular - sale ) : 0;
			var savePct = regular > 0 ? Math.round( ( saveAmt / regular ) * 100 ) : 0;
			var prefix  = $widget.data( 'savings-prefix' ) || 'Save';
			var fmt     = $widget.data( 'savings-format' ) || 'amount_percent';
			var saveTxt = '';

			switch ( fmt ) {
				case 'amount_only':
					saveTxt = prefix + ' ' + formatPrice( saveAmt );
					break;
				case 'percent_only':
					saveTxt = prefix + ' ' + savePct + '%';
					break;
				default:
					saveTxt = prefix + ' ' + formatPrice( saveAmt ) + ' (' + savePct + '%)';
					break;
			}
			html += '<span class="zymarg-price-savings">' + escapeHtml( saveTxt ) + '</span>';
		}

		$widget
			.toggleClass( 'zymarg-price--on-sale', isOnSale )
			.attr( 'data-variation-id', variation.variation_id || '' )
			.html( html );
	}

	/**
	 * Restore a price widget to its initial baseline (the lowest-active
	 * + lowest-regular state from the server-side render). Triggered on
	 * the canonical form's `reset_data` event when the customer clears
	 * their swatch selection.
	 *
	 * @param {jQuery} $widget
	 */
	function restoreInitial( $widget ) {

		var initialCurrent = $widget.data( 'initial-current' ) || '';
		var initialRegular = $widget.data( 'initial-regular' ) || '';
		var initialOnSale  = '1' === String( $widget.data( 'initial-on-sale' ) );

		var html = buildInnerHtml( {
			currentHtml:     initialCurrent,
			regularHtml:     initialRegular,
			isOnSale:        initialOnSale,
			regularPosition: $widget.data( 'regular-position' ) || 'subscript',
			showSaleBadge:   '1' === String( $widget.data( 'show-sale-badge' ) ),
			saleBadgeText:   $widget.data( 'sale-badge-text' ) || 'Sale',
		} );

		$widget
			.toggleClass( 'zymarg-price--on-sale', initialOnSale )
			.removeAttr( 'data-variation-id' )
			.html( html );
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

		$form.on( 'reset_data.wsePrice', function () {
			if ( skeletonEnabled ) {
				$widget.removeClass( 'zymarg-price--loading' );
			}
			restoreInitial( $widget );
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
