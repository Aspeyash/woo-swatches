/**
 * WooSwatches for Elementor — Form Field Dependency (v1.1.0)
 *
 * After a swatch is selected, some values in other attributes may no longer
 * have any valid in-stock variation. This module recalculates availability
 * for ALL attribute swatches whenever any selection changes.
 *
 * v1.1.0 (B10) — debounced updates.
 *   The previous version called updateAvailability() once per event. On a
 *   product with N default attributes, _syncInitialSelections fired N
 *   events at init, triggering N full availability scans. v1.1.0 collapses
 *   any number of events fired within the same tick into a single
 *   updateAvailability() call per form via requestAnimationFrame batching.
 *
 * v1.1.0 (B3) — scope changes.
 *   The dependency engine still operates against the canonical form
 *   (.variations_form). Visible swatches now live in Widget 1's wrapper,
 *   not inside the form, so we walk the swatch elements via either the
 *   form's matching .wse-widget-swatches[data-product-id] OR the form's
 *   own descendants for canonical-only setups.
 *
 * @package WooSwatchesElementor
 */
( function ( $ ) {
	'use strict';

	// ─────────────────────────────────────────────────────────────────────
	// Scoping helper — find swatch UI for a given canonical form
	// ─────────────────────────────────────────────────────────────────────

	function getSwatchScope( $form ) {
		var productId = String( $form.attr( 'data-product_id' ) || $form.data( 'product_id' ) || '' );
		if ( productId ) {
			var $widget1 = $( '.wse-widget-swatches[data-product-id="' + productId + '"]' );
			if ( $widget1.length ) {
				return $widget1;
			}
		}
		return $form;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Core availability engine
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns all current attribute→value selections from visible swatch wraps.
	 * Reads from the form's hidden selects (single source of truth in v1.1.0).
	 *
	 * @param {jQuery} $form Canonical variations_form.
	 * @returns {Object} e.g. { 'attribute_pa_color': 'red', 'attribute_pa_size': '' }
	 */
	function getCurrentSelections( $form ) {
		var selections = {};
		$form.find( 'select[name^="attribute_"]' ).each( function () {
			selections[ $( this ).attr( 'name' ) ] = String( $( this ).val() || '' );
		} );
		return selections;
	}

	function isValueAvailable( variations, currentSelections, attrKey, swatchValue ) {
		return variations.some( function ( variation ) {
			if ( ! variation.is_in_stock && ! variation.is_purchasable ) {
				return false;
			}
			for ( var key in currentSelections ) {
				if ( key === attrKey ) {
					continue;
				}
				var selectedVal = currentSelections[ key ];
				if ( ! selectedVal ) {
					continue;
				}
				var variationVal = variation.attributes[ key ] || '';
				if ( variationVal !== '' && variationVal !== selectedVal ) {
					return false;
				}
			}
			var ownVariationVal = variation.attributes[ attrKey ] || '';
			return ownVariationVal === '' || ownVariationVal === swatchValue;
		} );
	}

	function updateAvailability( $form ) {
		var variations = $form.data( 'product_variations' );
		if ( ! variations || variations === false ) {
			return;
		}

		var currentSelections = getCurrentSelections( $form );
		var $scope            = getSwatchScope( $form );

		$scope.find( '.wse-swatch-wrap' ).each( function () {
			var attribute = $( this ).data( 'attribute' );
			var attrKey   = 'attribute_' + attribute;

			$( this ).find( '.wse-swatch' ).each( function () {
				var $swatch     = $( this );
				var swatchValue = String( $swatch.data( 'value' ) );

				if ( $swatch.hasClass( 'disabled' ) ) {
					return;
				}

				var available = isValueAvailable(
					variations,
					currentSelections,
					attrKey,
					swatchValue
				);

				if ( available ) {
					$swatch.removeClass( 'filtered-out' )
					       .attr( 'aria-disabled', 'false' );
				} else {
					$swatch.addClass( 'filtered-out' )
					       .attr( 'aria-disabled', 'true' );
				}
			} );
		} );
	}

	function clearFilteredOut( $form ) {
		var $scope = getSwatchScope( $form );
		$scope.find( '.wse-swatch.filtered-out' )
			.removeClass( 'filtered-out' )
			.attr( 'aria-disabled', 'false' );
	}

	// ─────────────────────────────────────────────────────────────────────
	// v1.1.0 (B10) — Per-form debounced scheduling
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Stores rAF handles per form-id. requestAnimationFrame collapses any
	 * number of update requests fired within the same tick into a single
	 * actual update — N events at init → 1 updateAvailability() call.
	 */
	var pendingUpdates = {};

	function scheduleUpdate( $form ) {
		// Use the form's id (or a generated key) as the dedup token.
		var key = $form.attr( 'id' ) || $form.attr( 'data-product_id' ) || 'wse-form-default';

		if ( pendingUpdates[ key ] ) {
			return; // already scheduled for this tick
		}

		var raf = window.requestAnimationFrame || function ( cb ) {
			return window.setTimeout( cb, 16 );
		};

		pendingUpdates[ key ] = raf( function () {
			delete pendingUpdates[ key ];
			updateAvailability( $form );
		} );
	}

	function scheduleClear( $form ) {
		var key = $form.attr( 'id' ) || $form.attr( 'data-product_id' ) || 'wse-form-default';

		if ( pendingUpdates[ key ] ) {
			return;
		}

		var raf = window.requestAnimationFrame || function ( cb ) {
			return window.setTimeout( cb, 16 );
		};

		pendingUpdates[ key ] = raf( function () {
			delete pendingUpdates[ key ];
			clearFilteredOut( $form );
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Event bindings
	// ─────────────────────────────────────────────────────────────────────

	$( document.body )
		.off( 'wse:swatchSelected.wse-ffd' )
		.on( 'wse:swatchSelected.wse-ffd', function ( e, data ) {
			if ( data && data.$form ) {
				scheduleUpdate( data.$form );
			}
		} );

	$( document.body )
		.off( 'wse:swatchDeselected.wse-ffd' )
		.on( 'wse:swatchDeselected.wse-ffd', function ( e, data ) {
			if ( data && data.$form ) {
				scheduleUpdate( data.$form );
			}
		} );

	$( document.body )
		.off( 'wse:swatchReset.wse-ffd' )
		.on( 'wse:swatchReset.wse-ffd', function ( e, data ) {
			if ( data && data.$form ) {
				scheduleClear( data.$form );
			}
		} );

	// v1.1.0 — fired once at end of swatches.js _syncInitialSelections so
	// availability runs exactly once per init even when many defaults exist.
	$( document.body )
		.off( 'wse:bulkSyncComplete.wse-ffd' )
		.on( 'wse:bulkSyncComplete.wse-ffd', function ( e, data ) {
			if ( data && data.$form ) {
				scheduleUpdate( data.$form );
			}
		} );

	$( document )
		.off( 'woocommerce_variation_has_changed.wse-ffd', 'form.variations_form' )
		.on( 'woocommerce_variation_has_changed.wse-ffd', 'form.variations_form', function () {
			scheduleUpdate( $( this ) );
		} );

	$( document.body )
		.off( 'wse:reinit.wse-ffd post-load.wse-ffd elementor/popup/show.wse-ffd' )
		.on( 'wse:reinit.wse-ffd post-load.wse-ffd elementor/popup/show.wse-ffd', function () {
			$( 'form.variations_form' ).each( function () {
				scheduleUpdate( $( this ) );
			} );
		} );

} )( jQuery );
