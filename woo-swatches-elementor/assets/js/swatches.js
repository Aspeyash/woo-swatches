/**
 * WooSwatches for Elementor — Frontend Swatches Engine
 *
 * One WSE_Swatches instance per .variations_form on the page.
 * All event handlers are namespaced to '.wse' so they can be cleanly
 * unbound on AJAX reinit without touching WooCommerce's own handlers.
 *
 * Responsibilities:
 *   - Swatch click → sync hidden <select> → fire .change() (Emran Ahmed pattern)
 *   - Keyboard navigation — WCAG 2.2 roving tabindex (Gap 14)
 *   - reset_data event → deselect all swatches (Gap 40)
 *   - Clear link click → trigger WC's own .reset_variations (Gap 40)
 *   - found_variation → safe gallery image swap (Gap 9, Gap 32)
 *   - hide_variation / reset_data → restore original gallery image
 *   - wc_update_variation_image trigger for theme sliders (Gap 56)
 *   - Gap 8 — AJAX reinit on Elementor popups, WC quick views, post-load
 *   - Gap 35 — All handlers scoped to their $form instance
 *   - Developer events: wse:swatchSelected, wse:swatchReset (API)
 *
 * @package WooSwatchesElementor
 */
( function ( $, window, document ) {
	'use strict';

	// ─────────────────────────────────────────────────────────────────────
	// WSE_Swatches constructor
	// Gap 35 — one instance per form; all handlers scoped to this.$form
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * @param {jQuery} $form The .variations_form element.
	 */
	function WSE_Swatches( $form ) {
		this.$form   = $form;
		/** @type {{src:string, srcset:string, href:string}|null} */
		this._origGallery = null;
		this._init();
	}

	WSE_Swatches.prototype = {

		// ── Init ─────────────────────────────────────────────────────────

		_init: function () {
			this._bindSwatchClick();
			this._bindKeyboardNav();
			this._bindResetData();
			this._bindVariationEvents();
			this._updateAllResetLinks();
			this._syncInitialSelections();
		},

		// ── Swatch click ─────────────────────────────────────────────────

		/**
		 * Handles swatch click.
		 * Disabled swatches (.disabled) silently absorb the click.
		 * Clicking an already-selected swatch toggles it off (deselect).
		 */
		_bindSwatchClick: function () {
			var self = this;

			this.$form.on( 'click.wse', '.wse-swatch', function ( e ) {
				e.preventDefault();

				var $swatch   = $( this );
				var attribute = $swatch.data( 'attribute' );
				var value     = $swatch.data( 'value' );

				// Silently ignore disabled (OOS) swatches
				if ( $swatch.hasClass( 'disabled' ) ) {
					return;
				}

				// Toggle off if already selected
				if ( $swatch.hasClass( 'selected' ) ) {
					self._deselectAttribute( attribute );
					return;
				}

				self._selectSwatch( $swatch, attribute, value );
			} );
		},

		/**
		 * Selects a swatch:
		 *   1. Marks it selected + updates ARIA + roving tabindex
		 *   2. Syncs the hidden WC <select> (Emran Ahmed's confirmed approach)
		 *   3. Fires WC's .change() so wc-add-to-cart-variation.js takes over
		 *   4. Shows the clear link for this attribute
		 *   5. Fires the developer wse:swatchSelected event
		 *
		 * @param {jQuery} $swatch   The clicked swatch element.
		 * @param {string} attribute Attribute name e.g. 'pa_color'.
		 * @param {string} value     Term slug / option value e.g. 'red'.
		 */
		_selectSwatch: function ( $swatch, attribute, value ) {
			var self     = this;
			var $wrap    = this.$form.find( '.wse-swatch-wrap[data-attribute="' + attribute + '"]' );
			var $select  = this.$form.find( 'select[name="attribute_' + attribute + '"]' );
			var $siblings = $wrap.find( '.wse-swatch' );

			// Deselect all siblings — remove selected, reset aria-checked + tabindex
			$siblings
				.removeClass( 'selected' )
				.attr( 'aria-checked', 'false' )
				.attr( 'tabindex', '-1' );

			// Select this swatch
			$swatch
				.addClass( 'selected' )
				.attr( 'aria-checked', 'true' )
				.attr( 'tabindex', '0' );

			// Gap 2 — Sync the hidden WC <select> and fire .change()
			// wc-add-to-cart-variation.js listens for this .change() event
			// and handles all variation matching, price updates, stock checks
			$select.val( value ).trigger( 'change' );

			// Show the clear link for this attribute wrap
			$wrap.find( '.wse-reset-link' ).show();

			// Update the visible selected-value label (Widget 1 label row — Phase 10)
			var $selectedVal = self.$form.find(
				'.wse-attr-selected-val[data-attribute="' + attribute + '"]'
			);
			if ( $selectedVal.length ) {
				// Prefer the swatch's title attribute (term label) over raw slug
				$selectedVal.text( $swatch.attr( 'title' ) || value );
			}

			// Gap 53 / developer API — fire custom event on document.body
			$( document.body ).trigger( 'wse:swatchSelected', [ {
				attribute : attribute,
				value     : value,
				$swatch   : $swatch,
				$form     : self.$form,
			} ] );
		},

		/**
		 * Fix (v1.0.3, Bug D): replays the wse:swatchSelected event on init
		 * for every attribute that already has a non-empty value.
		 *
		 * WooCommerce pre-populates a .variations select with the product's
		 * default attribute (selected="selected" on the matching <option>),
		 * and wc-add-to-cart-variation.js's own init picks this up for THIS
		 * form — but no 'change' event fires for that pre-selection, so the
		 * wse:swatchSelected cross-widget bridge (normally fired only from
		 * _selectSwatch on click) never runs. Widget 2's hidden selects stay
		 * empty and its Add to Cart button remains permanently disabled for
		 * any product with default attributes, even though Widget 1's
		 * swatches already render with .selected applied.
		 *
		 * This brings Widget 2 into sync at load time, mirroring the exact
		 * event payload _selectSwatch fires on click. No-op for attributes
		 * with no default (select value is '' — "Choose an option").
		 */
		_syncInitialSelections: function () {
			var self = this;

			this.$form.find( '.wse-swatch-wrap[data-attribute]' ).each( function () {
				var $wrap     = $( this );
				var attribute = $wrap.data( 'attribute' );
				var $select   = self.$form.find( 'select[name="attribute_' + attribute + '"]' );
				var value     = $select.val();

				if ( ! value ) {
					return; // No default for this attribute.
				}

				var $swatch = $wrap.find( '.wse-swatch[data-value="' + value + '"]' );

				$( document.body ).trigger( 'wse:swatchSelected', [ {
					attribute : attribute,
					value     : value,
					$swatch   : $swatch,
					$form     : self.$form,
				} ] );
			} );
		},

		/**
		 * Deselects all swatches for one attribute and clears the hidden select.
		 *
		 * @param {string} attribute Attribute name.
		 */
		_deselectAttribute: function ( attribute ) {
			var self    = this;
			var $wrap   = this.$form.find( '.wse-swatch-wrap[data-attribute="' + attribute + '"]' );
			var $select = this.$form.find( 'select[name="attribute_' + attribute + '"]' );

			$wrap.find( '.wse-swatch' )
				.removeClass( 'selected' )
				.attr( 'aria-checked', 'false' )
				.attr( 'tabindex', '-1' );

			// Make first available swatch tabbable (roving tabindex)
			$wrap.find( '.wse-swatch:not(.disabled)' ).first().attr( 'tabindex', '0' );

			$select.val( '' ).trigger( 'change' );
			$wrap.find( '.wse-reset-link' ).hide();

			// Clear the visible selected-value label (Widget 1 label row)
			self.$form.find(
				'.wse-attr-selected-val[data-attribute="' + attribute + '"]'
			).text( '' );

			$( document.body ).trigger( 'wse:swatchDeselected', [ {
				attribute : attribute,
				$form     : self.$form,
			} ] );
		},

		// ── Keyboard navigation — WCAG 2.2 (Gap 14) ─────────────────────

		/**
		 * Roving tabindex keyboard navigation within a swatch group.
		 *
		 * Arrow keys move focus between available swatches in the same group.
		 * Enter / Space select the focused swatch (matching native radio behavior).
		 * Keys wrap around (last → first, first → last).
		 */
		_bindKeyboardNav: function () {
			var self = this;

			this.$form.on( 'keydown.wse', '.wse-swatch', function ( e ) {
				var $current   = $( this );
				var attribute  = $current.data( 'attribute' );
				var $wrap      = self.$form.find( '.wse-swatch-wrap[data-attribute="' + attribute + '"]' );
				// Only navigate between available (non-disabled) swatches
				var $available = $wrap.find( '.wse-swatch:not(.disabled)' );
				var idx        = $available.index( $current );
				var last       = $available.length - 1;

				switch ( e.key ) {

					case 'Enter':
					case ' ':
						e.preventDefault();
						$current.trigger( 'click.wse' );
						break;

					case 'ArrowRight':
					case 'ArrowDown':
						e.preventDefault();
						// Update tabindex (roving tabindex pattern)
						$available.attr( 'tabindex', '-1' );
						$available.eq( idx < last ? idx + 1 : 0 )
						         .attr( 'tabindex', '0' )
						         .focus();
						break;

					case 'ArrowLeft':
					case 'ArrowUp':
						e.preventDefault();
						$available.attr( 'tabindex', '-1' );
						$available.eq( idx > 0 ? idx - 1 : last )
						         .attr( 'tabindex', '0' )
						         .focus();
						break;
				}
			} );
		},

		// ── Reset / clear (Gap 40) ────────────────────────────────────────

		/**
		 * Wires reset_data (fired by WC when .reset_variations is clicked)
		 * and our styled .wse-reset-link to the WC mechanism.
		 */
		_bindResetData: function () {
			var self = this;

			// WC fires this on $form when .reset_variations is clicked
			this.$form.on( 'reset_data.wse', function () {
				self._onResetData();
			} );

			/**
			 * Fix (v1.0.3, Bug B): .reset_variations is part of WC core's
			 * default <table class="variations"> template, which this
			 * plugin never renders — so .reset_variations never exists and
			 * .trigger('click') on it was a complete no-op, leaving Clear
			 * permanently broken.
			 *
			 * Instead, directly reset every variation-bound <select> in this
			 * form (both swatch-type hidden selects and, since v1.0.3,
			 * passthrough native selects — both now carry .variations) and
			 * trigger 'change' so wc-add-to-cart-variation.js recalculates
			 * and disables Add to Cart. _onResetData() then handles our own
			 * swatch UI (deselect, hide clear links, restore gallery) and
			 * fires wse:swatchReset so Widget 2 clears in sync.
			 */
			this.$form.on( 'click.wse', '.wse-reset-link', function ( e ) {
				e.preventDefault();
				self.$form.find( '.variations select' ).val( '' ).trigger( 'change' );
				self._onResetData();
			} );
		},

		/**
		 * Handles the WC reset_data event:
		 * deselects all swatches, hides all clear links, restores gallery.
		 */
		_onResetData: function () {
			// Deselect all swatches and reset ARIA states
			this.$form.find( '.wse-swatch' )
				.removeClass( 'selected' )
				.attr( 'aria-checked', 'false' )
				.attr( 'tabindex', '-1' );

			// Make first available swatch in each group tabbable
			var self = this;
			this.$form.find( '.wse-swatch-wrap' ).each( function () {
				$( this ).find( '.wse-swatch:not(.disabled)' ).first()
				         .attr( 'tabindex', '0' );
			} );

			// Hide all clear links
			this.$form.find( '.wse-reset-link' ).hide();

			// Clear all visible selected-value labels (Widget 1 label rows)
			this.$form.find( '.wse-attr-selected-val' ).text( '' );

			// Restore original gallery image
			this._restoreGallery();

			// Developer API event
			$( document.body ).trigger( 'wse:swatchReset', [ { $form: self.$form } ] );
		},

		// ── Variation events + gallery (Gap 9, Gap 32, Gap 56) ───────────

		_bindVariationEvents: function () {
			var self = this;

			// fired by WC when a full valid variation is found
			this.$form.on( 'found_variation.wse', function ( e, variation ) {
				self._onFoundVariation( variation );
			} );

			// fired when the current selection no longer matches any variation
			this.$form.on( 'hide_variation.wse', function () {
				self._restoreGallery();
			} );
		},

		/**
		 * Handles found_variation:
		 *   - Swaps the main product gallery image (Gap 9)
		 *   - Uses the safe src/srcset swap approach to avoid breaking
		 *     Flexslider or PhotoSwipe (Gap 32)
		 *   - Fires wc_update_variation_image for theme sliders (Gap 56)
		 *
		 * @param {Object} variation WooCommerce variation data object.
		 */
		_onFoundVariation: function ( variation ) {
			if (
				! variation.image ||
				! variation.image.src ||
				variation.image.src.length === 0
			) {
				return;
			}

			var $img  = this._getGalleryImage();
			if ( ! $img.length ) {
				return;
			}

			// Store original once (only before the very first swap)
			if ( ! this._origGallery ) {
				var $link = $img.closest( 'a' );
				this._origGallery = {
					src    : $img.attr( 'src' )    || '',
					srcset : $img.attr( 'srcset' ) || '',
					href   : $link.attr( 'href' )  || '',
				};
			}

			// Gap 32 — safe swap: update src/srcset on the existing <img>
			// DO NOT remove/replace the img or reinitialise the gallery slider
			$img.attr( 'src',    variation.image.src );
			$img.attr( 'srcset', variation.image.srcset || '' );

			// Update lightbox anchor href
			var $anchor = $img.closest( 'a' );
			if ( $anchor.length ) {
				$anchor.attr( 'href', variation.image.full_src || variation.image.src );
			}

			// Gap 56 — Notify theme sliders (Swiper, Flickity, Splide, etc.)
			// so they can update their own internal state if needed
			$( document.body ).trigger( 'wc_update_variation_image', [ variation ] );
		},

		/**
		 * Restores the original gallery image when a variation is deselected.
		 */
		_restoreGallery: function () {
			if ( ! this._origGallery ) {
				return;
			}

			var $img    = this._getGalleryImage();
			var $anchor = $img.closest( 'a' );

			$img.attr( 'src',    this._origGallery.src );
			$img.attr( 'srcset', this._origGallery.srcset );

			if ( $anchor.length && this._origGallery.href ) {
				$anchor.attr( 'href', this._origGallery.href );
			}
		},

		/**
		 * Returns the first product gallery image jQuery object.
		 * Targets WooCommerce's standard gallery markup.
		 *
		 * @return {jQuery}
		 */
		_getGalleryImage: function () {
			// Standard WooCommerce gallery structure since v3.x
			return $( '.woocommerce-product-gallery__image:first-child img' );
		},

		// ── Reset link visibility ─────────────────────────────────────────

		/**
		 * Updates the visible/hidden state of all .wse-reset-link elements
		 * based on whether each attribute has a selected swatch.
		 * Called after init so saved/default selections show the link.
		 */
		_updateAllResetLinks: function () {
			this.$form.find( '.wse-swatch-wrap' ).each( function () {
				var $wrap      = $( this );
				var hasSelected = $wrap.find( '.wse-swatch.selected' ).length > 0;
				$wrap.find( '.wse-reset-link' ).toggle( hasSelected );
			} );
		},

	}; // end WSE_Swatches.prototype

	// ─────────────────────────────────────────────────────────────────────
	// Init — create WSE_Swatches instance per form
	// Gap 35 — each form gets its own isolated instance
	// ─────────────────────────────────────────────────────────────────────

	function initAll() {
		$( 'form.variations_form' ).each( function () {
			var $form = $( this );
			// Prevent double-initialisation
			if ( $form.data( 'wse-swatches' ) ) {
				return;
			}
			$form.data( 'wse-swatches', new WSE_Swatches( $form ) );
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Bootstrap
	// ─────────────────────────────────────────────────────────────────────

	$( document ).ready( initAll );

	/**
	 * Gap 8 — AJAX reinit.
	 *
	 * All triggers that may add new .variations_form elements to the DOM:
	 *   wc_variation_form           — WC fires this after AJAX variation load
	 *   woocommerce_variation_has_changed — WC variation selection change
	 *   added_to_cart               — WC after cart AJAX
	 *   post-load                   — Jetpack infinite scroll
	 *   elementor/popup/show        — Elementor Pro popup opens
	 *   wse:reinit                  — Manual reinit (for custom integrations)
	 */
	$( document.body ).on(
		'wc_variation_form woocommerce_variation_has_changed ' +
		'post-load elementor/popup/show wse:reinit',
		initAll
	);

} )( jQuery, window, document );
