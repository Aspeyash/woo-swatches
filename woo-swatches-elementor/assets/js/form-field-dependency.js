/**
 * WooSwatches for Elementor — Form Field Dependency
 *
 * After a swatch is selected, some values in other attributes may no longer
 * have any valid in-stock variation. This module recalculates availability
 * for ALL attribute swatches whenever any selection changes.
 *
 * How it works:
 *   1. Listens to wse:swatchSelected and wse:swatchReset on document.body
 *   2. Reads current attribute selections from the form
 *   3. Tests each swatch value against $form.data('product_variations')
 *   4. Adds/removes .filtered-out class and aria-disabled on each swatch
 *
 * Gap 50 — form-field-dependency.js progressive attribute disclosure
 * Gap 10 — AJAX variation threshold: gracefully skips when variation
 *           data is not yet available (> 30 variations by default)
 *
 * Requires swatches.js to fire wse:swatchSelected and wse:swatchReset.
 *
 * @package WooSwatchesElementor
 */
( function ( $ ) {
	'use strict';

	// ─────────────────────────────────────────────────────────────────────
	// Core availability engine
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns all current attribute→value selections from visible swatch wraps.
	 *
	 * @param  {jQuery} $form The variations_form.
	 * @return {Object} e.g. { 'attribute_pa_color': 'red', 'attribute_pa_size': '' }
	 */
	function getCurrentSelections( $form ) {
		var selections = {};
		$form.find( '.wse-swatch-wrap' ).each( function () {
			var attribute = $( this ).data( 'attribute' );
			var $selected = $( this ).find( '.wse-swatch.selected' );
			selections[ 'attribute_' + attribute ] = $selected.length
				? String( $selected.data( 'value' ) )
				: '';
		} );
		return selections;
	}

	/**
	 * Tests whether a specific swatch value is available given the
	 * current partial attribute selection.
	 *
	 * A value is "available" if there is at least one variation in the
	 * variations array that:
	 *   a) is in stock (is_in_stock OR is_purchasable)
	 *   b) matches every currently-selected attribute (or has '' = "any")
	 *   c) matches this swatch value for its own attribute (or has '' = "any")
	 *
	 * @param  {Array}  variations       $form.data('product_variations')
	 * @param  {Object} currentSelections Result of getCurrentSelections()
	 * @param  {string} attrKey          e.g. 'attribute_pa_size'
	 * @param  {string} swatchValue      e.g. 'large'
	 * @return {boolean}
	 */
	function isValueAvailable( variations, currentSelections, attrKey, swatchValue ) {
		return variations.some( function ( variation ) {

			// Skip completely unavailable variations
			if ( ! variation.is_in_stock && ! variation.is_purchasable ) {
				return false;
			}

			// Check every OTHER attribute selection against this variation
			for ( var key in currentSelections ) {
				if ( key === attrKey ) {
					continue; // skip the attribute we are testing
				}
				var selectedVal  = currentSelections[ key ];
				if ( ! selectedVal ) {
					continue; // nothing selected yet for this attr — any variation ok
				}
				var variationVal = variation.attributes[ key ] || '';
				// '' in a variation means "any term matches"
				if ( variationVal !== '' && variationVal !== selectedVal ) {
					return false; // this variation doesn't match current selection
				}
			}

			// Check the swatch value against this attribute in the variation
			var ownVariationVal = variation.attributes[ attrKey ] || '';
			// '' = "any" — matches all values for this attribute
			return ownVariationVal === '' || ownVariationVal === swatchValue;
		} );
	}

	/**
	 * Main update function: recalculates and applies filtered-out state
	 * to all swatches based on current selections.
	 *
	 * Gap 10 — If product_variations is false (AJAX threshold exceeded),
	 *           this function exits immediately — graceful degradation.
	 *           Swatches will fall back to their PHP-rendered OOS states only.
	 *
	 * @param {jQuery} $form The variations_form.
	 */
	function updateAvailability( $form ) {

		var variations = $form.data( 'product_variations' );

		// Gap 10 — Graceful fallback: variation data not yet loaded
		// (product has > woocommerce_ajax_variation_threshold variations)
		if ( ! variations || variations === false ) {
			return;
		}

		var currentSelections = getCurrentSelections( $form );

		$form.find( '.wse-swatch-wrap' ).each( function () {
			var attribute = $( this ).data( 'attribute' );
			var attrKey   = 'attribute_' + attribute;

			$( this ).find( '.wse-swatch' ).each( function () {
				var $swatch     = $( this );
				var swatchValue = String( $swatch.data( 'value' ) );

				// Skip swatches that are OOS (disabled via PHP)
				// They stay disabled regardless of selection state
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
					$swatch
						.removeClass( 'filtered-out' )
						.attr( 'aria-disabled', 'false' );
				} else {
					// Filtered-out: not OOS, but incompatible with current selection
					$swatch
						.addClass( 'filtered-out' )
						.attr( 'aria-disabled', 'true' );
				}
			} );
		} );
	}

	/**
	 * Clears all filtered-out states when all selections are reset.
	 *
	 * @param {jQuery} $form The variations_form.
	 */
	function clearFilteredOut( $form ) {
		$form.find( '.wse-swatch.filtered-out' )
			.removeClass( 'filtered-out' )
			.attr( 'aria-disabled', 'false' );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Event bindings
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Triggered by swatches.js when any swatch is selected.
	 * Receives { attribute, value, $swatch, $form }.
	 */
	$( document.body ).on( 'wse:swatchSelected', function ( e, data ) {
		if ( data && data.$form ) {
			updateAvailability( data.$form );
		}
	} );

	/**
	 * Triggered by swatches.js when a single attribute is deselected
	 * (clicking an already-selected swatch toggles it off).
	 */
	$( document.body ).on( 'wse:swatchDeselected', function ( e, data ) {
		if ( data && data.$form ) {
			updateAvailability( data.$form );
		}
	} );

	/**
	 * Triggered by swatches.js when all selections are cleared.
	 * Receives { $form }.
	 */
	$( document.body ).on( 'wse:swatchReset', function ( e, data ) {
		if ( data && data.$form ) {
			clearFilteredOut( data.$form );
		}
	} );

	/**
	 * WooCommerce fires woocommerce_variation_has_changed after every
	 * selection change on a variations_form. We also listen here so the
	 * dependency state updates even when WC processes changes internally
	 * (e.g. default attributes on page load).
	 */
	$( document ).on(
		'woocommerce_variation_has_changed',
		'form.variations_form',
		function () {
			updateAvailability( $( this ) );
		}
	);

	/**
	 * Gap 8 — Re-run after AJAX reinit (popups, quick view, infinite scroll).
	 * When new forms appear after swatches.js reinits them, we also need to
	 * recalculate availability for their current (default) state.
	 */
	$( document.body ).on(
		'wse:reinit post-load elementor/popup/show',
		function () {
			$( 'form.variations_form' ).each( function () {
				updateAvailability( $( this ) );
			} );
		}
	);

} )( jQuery );
