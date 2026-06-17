/**
 * WooSwatches for Elementor — Frontend Swatches Engine (v1.1.0)
 *
 * v1.1.0 (B3) — Single-canonical-form architecture.
 *
 *   In v1.0.5 each widget rendered its own .variations_form, doubling the
 *   wc-add-to-cart-variation.js engine and the variation JSON. v1.1.0
 *   refactors so exactly ONE canonical form exists per product per page,
 *   with widgets coordinating via the WSE_Form_Registry on the PHP side
 *   and the reconciliation pipeline below on the JS side.
 *
 * 5-step DOMReady reconciliation pipeline:
 *
 *   1. Dedupe swatch UI: when both Widget 1 and Widget 2 emit a swatch
 *      block for the same (product, attribute), keep the one inside
 *      Widget 1's wrapper and hide the canonical-form duplicate
 *      (preserving its hidden <select> as the form-state holder).
 *
 *   2. Wrap orphan Widget 1 swatches: if a Widget 1 wrapper exists for
 *      product P but no canonical .wse-canonical-form is on the page,
 *      synthesise a hidden <form id="wse-form-{P}"> from the
 *      <script class="wse-variations-json"> payload so wc-add-to-cart-
 *      variation.js still has a form to bind to.
 *
 *   3. Wire WSE_Swatches per canonical form (one instance per form,
 *      idempotent — same selector match never re-creates).
 *
 *   4. Cross-widget click sync: clicks on Widget 1 swatches resolve
 *      the canonical form via [data-form-id] and update its hidden
 *      <select> via .val().trigger('change') — wc-add-to-cart-variation.js
 *      then handles all variation matching, price updates, and stock checks.
 *
 *   5. Init / reinit handling: same pipeline runs on wse:reinit,
 *      wc_variation_form, post-load, elementor/popup/show. Every binding
 *      is namespaced and .off()-then-.on() idempotent.
 *
 * Other v1.1.0 changes:
 *   • B12 — gallery image swap uses the active-slide-aware selector chain
 *           (.flex-active-slide → :not(.flex-active-slide) → :first-child)
 *           so themes with carousel galleries swap the visible slide.
 *
 * @package WooSwatchesElementor
 * @since   1.1.0
 */
( function ( $, window, document ) {
	'use strict';

	// ─────────────────────────────────────────────────────────────────────
	// Module-level utilities
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the canonical form jQuery object for a product, or null.
	 *
	 * @param {number|string} productId
	 * @returns {jQuery|null}
	 */
	function getCanonicalForm( productId ) {
		var $form = $( '#wse-form-' + String( productId ) );
		return $form.length ? $form : null;
	}

	/**
	 * Reads the variation JSON for a product from either the canonical form's
	 * data-product_variations attribute (preferred — already attached by WC)
	 * or from the <script class="wse-variations-json"> payload emitted by
	 * Widget 1.
	 *
	 * @param {number|string} productId
	 * @returns {Array|null}
	 */
	function getVariationsForProduct( productId ) {
		var $form = getCanonicalForm( productId );
		if ( $form ) {
			var fromForm = $form.data( 'product_variations' );
			if ( fromForm ) {
				return fromForm;
			}
		}
		var $script = $( 'script.wse-variations-json[data-product-id="' + String( productId ) + '"]' );
		if ( ! $script.length ) {
			return null;
		}
		try {
			return JSON.parse( $script.text() );
		} catch ( e ) {
			return null;
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// Step 1 — Dedupe swatch UI between Widget 1 and the canonical form
	//
	// v1.1.1 — FOUC fix. The canonical form's swatches now ship with
	// `display:none` baked into the CSS. This step REVEALS them (by
	// toggling the `wse-show-canonical-swatches` class on the form) only
	// when no Widget 1 exists for the product — i.e. the canonical-only
	// "Widget 2 alone" scenario. The common Widget 1 + Widget 2 case
	// keeps the default hidden state, so there is zero flash of duplicate
	// swatches on first paint.
	// ─────────────────────────────────────────────────────────────────────

	function dedupeSwatchUI() {
		$( '.wse-canonical-form[data-product_id]' ).each( function () {
			var $canonical = $( this );
			var productId  = String( $canonical.attr( 'data-product_id' ) || '' );
			if ( ! productId ) {
				return;
			}

			var hasWidget1 = $( '.wse-widget-swatches[data-product-id="' + productId + '"]' ).length > 0;

			if ( ! hasWidget1 ) {
				// Widget 2 is alone for this product — show its swatches.
				$canonical.addClass( 'wse-show-canonical-swatches' );
			}
			// else: leave default hidden state in place. Widget 1 owns the visible UI.
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Step 2 — Wrap orphan Widget 1 (no canonical form on page → scenario c)
	//         Plus orphan Presenter (v1.1.1)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Builds a synthetic hidden canonical form for either:
	 *   • A Widget 1 (Swatches) wrapper that has no Widget 2 on the page
	 *   • A presenter Widget 2 whose data-form-id target form doesn't exist
	 *     (v1.1.1 — orphan presenter scenario)
	 *
	 * The form contains:
	 *   - hidden <select> per attribute (built from variation data)
	 *   - data-product_variations attribute populated from the script tag
	 * so wc-add-to-cart-variation.js can bind and the click-sync (Step 4)
	 * has selects to update.
	 */
	function wrapOrphanWidget1() {

		// Collect orphan candidates — Widget 1 wrappers + orphan presenter Widget 2s.
		var $orphans = $();
		$( '.wse-widget-swatches[data-product-id]' ).each( function () {
			$orphans = $orphans.add( this );
		} );
		$( '.wse-widget-add-to-cart.wse-presenter[data-product-id]' ).each( function () {
			$orphans = $orphans.add( this );
		} );

		var seenProducts = {};

		$orphans.each( function () {
			var $orphan   = $( this );
			var productId = String( $orphan.attr( 'data-product-id' ) || '' );
			var formId    = String( $orphan.attr( 'data-form-id' ) || ( 'wse-form-' + productId ) );

			if ( ! productId ) {
				return;
			}

			// Skip if a canonical form already exists for this product.
			if ( document.getElementById( formId ) ) {
				return;
			}

			// Don't synthesise twice for the same product.
			if ( seenProducts[ productId ] ) {
				return;
			}
			seenProducts[ productId ] = true;

			var variations = getVariationsForProduct( productId );
			if ( ! variations || ! variations.length ) {
				return; // no JSON available — cannot synthesise
			}

			// Collect every attribute key referenced by any variation.
			var attrKeys = {};
			variations.forEach( function ( v ) {
				if ( v && v.attributes ) {
					Object.keys( v.attributes ).forEach( function ( k ) {
						attrKeys[ k ] = true;
					} );
				}
			} );

			// Build the form.
			var $form = $( '<form/>' )
				.attr( 'id', formId )
				.attr( 'class', 'variations_form cart wse-canonical-form wse-canonical-form--synthetic' )
				.attr( 'method', 'post' )
				.attr( 'enctype', 'multipart/form-data' )
				.attr( 'data-product_id', productId )
				.attr( 'data-product_variations', JSON.stringify( variations ) )
				.css( { position: 'absolute', left: '-9999px', width: 0, height: 0, overflow: 'hidden' } )
				.attr( 'aria-hidden', 'true' );

			Object.keys( attrKeys ).forEach( function ( name ) {
				var $select = $( '<select/>' )
					.attr( 'name', name )
					.attr( 'data-attribute_name', name );
				$( '<option/>' ).val( '' ).text( '' ).appendTo( $select );
				$form.append( $select );
			} );

			// Required hidden inputs so wc-ajax/add_to_cart receives the
			// right product_id / variation_id even on orphan presenter setups.
			$form.append( '<input type="hidden" name="add-to-cart" value="' + productId + '"/>' );
			$form.append( '<input type="hidden" name="product_id"  value="' + productId + '"/>' );
			$form.append( '<input type="hidden" name="variation_id" class="variation_id" value=""/>' );

			// Quantity input — a hidden mirror so $form.serializeArray() picks
			// up a value when the user only has presenter qty input.
			$form.append( '<input type="hidden" name="quantity" class="qty wse-canonical-qty" value="1"/>' );

			// Append at the same DOM level as the orphan so it's reachable
			// via standard delegated handlers.
			$( 'body' ).append( $form );

			$form.data( 'product_variations', variations );
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Step 3 — One WSE_Swatches per canonical form
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * @param {jQuery} $form  The canonical form (real or synthetic).
	 * @param {jQuery} $scope The DOM scope to listen for swatch clicks in.
	 *                        For Widget 1 → its outer .wse-widget-swatches.
	 *                        For canonical-form-only setups → the form itself.
	 */
	function WSE_Swatches( $form, $scope ) {
		this.$form  = $form;
		this.$scope = $scope || $form;
		this._origGallery = null;
		this._init();
	}

	WSE_Swatches.prototype = {

		_init: function () {
			this._bindSwatchClick();
			this._bindKeyboardNav();
			this._bindResetData();
			this._bindVariationEvents();
			this._updateAllResetLinks();
			this._syncInitialSelections();
		},

		// ── Swatch click ────────────────────────────────────────────────

		_bindSwatchClick: function () {
			var self = this;

			this.$scope.off( 'click.wse', '.wse-swatch' )
				.on( 'click.wse', '.wse-swatch', function ( e ) {

				e.preventDefault();
				var $swatch   = $( this );
				var attribute = $swatch.data( 'attribute' );
				var value     = $swatch.data( 'value' );

				if ( $swatch.hasClass( 'disabled' ) ) {
					return;
				}
				if ( $swatch.hasClass( 'selected' ) ) {
					self._deselectAttribute( attribute );
					return;
				}
				self._selectSwatch( $swatch, attribute, value );
			} );
		},

		_selectSwatch: function ( $swatch, attribute, value ) {
			var self      = this;
			var $wrap     = this.$scope.find( '.wse-swatch-wrap[data-attribute="' + attribute + '"]' );
			var $select   = this.$form.find( 'select[name="attribute_' + attribute + '"]' );
			var $siblings = $wrap.find( '.wse-swatch' );

			$siblings
				.removeClass( 'selected' )
				.attr( 'aria-checked', 'false' )
				.attr( 'tabindex', '-1' );

			$swatch
				.addClass( 'selected' )
				.attr( 'aria-checked', 'true' )
				.attr( 'tabindex', '0' );

			$select.val( value ).trigger( 'change' );
			$wrap.find( '.wse-reset-link' ).show();

			var $selectedVal = self.$scope.find(
				'.wse-attr-selected-val[data-attribute="' + attribute + '"]'
			);
			if ( $selectedVal.length ) {
				$selectedVal.text( $swatch.attr( 'title' ) || value );
			}

			$( document.body ).trigger( 'wse:swatchSelected', [ {
				attribute : attribute,
				value     : value,
				$swatch   : $swatch,
				$form     : self.$form,
			} ] );
		},

		_syncInitialSelections: function () {
			var self = this;

			// v1.1.0 (B10) — collect first, fire once at end so
			// form-field-dependency.js runs updateAvailability ONCE
			// per init regardless of how many default attributes exist.
			var pending = [];

			this.$scope.find( '.wse-swatch-wrap[data-attribute]' ).each( function () {
				var $wrap     = $( this );
				var attribute = $wrap.data( 'attribute' );
				var $select   = self.$form.find( 'select[name="attribute_' + attribute + '"]' );
				var value     = $select.val();
				if ( ! value ) {
					return;
				}
				pending.push( {
					attribute : attribute,
					value     : value,
					$swatch   : $wrap.find( '.wse-swatch[data-value="' + value + '"]' ),
					$form     : self.$form,
				} );
			} );

			if ( ! pending.length ) {
				return;
			}

			// Fire individual events so DOM stays in sync …
			pending.forEach( function ( payload ) {
				$( document.body ).trigger( 'wse:swatchSelected', [ payload ] );
			} );

			// … then a single batched event for availability recompute.
			$( document.body ).trigger( 'wse:bulkSyncComplete', [ { $form: self.$form } ] );
		},

		_deselectAttribute: function ( attribute ) {
			var self    = this;
			var $wrap   = this.$scope.find( '.wse-swatch-wrap[data-attribute="' + attribute + '"]' );
			var $select = this.$form.find( 'select[name="attribute_' + attribute + '"]' );

			$wrap.find( '.wse-swatch' )
				.removeClass( 'selected' )
				.attr( 'aria-checked', 'false' )
				.attr( 'tabindex', '-1' );

			$wrap.find( '.wse-swatch:not(.disabled)' ).first().attr( 'tabindex', '0' );

			$select.val( '' ).trigger( 'change' );
			$wrap.find( '.wse-reset-link' ).hide();

			self.$scope.find(
				'.wse-attr-selected-val[data-attribute="' + attribute + '"]'
			).text( '' );

			$( document.body ).trigger( 'wse:swatchDeselected', [ {
				attribute : attribute,
				$form     : self.$form,
			} ] );
		},

		// ── Keyboard navigation (WCAG 2.2) ──────────────────────────────

		_bindKeyboardNav: function () {
			var self = this;

			this.$scope.off( 'keydown.wse', '.wse-swatch' )
				.on( 'keydown.wse', '.wse-swatch', function ( e ) {

				var $current   = $( this );
				var attribute  = $current.data( 'attribute' );
				var $wrap      = self.$scope.find( '.wse-swatch-wrap[data-attribute="' + attribute + '"]' );
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

		// ── Reset / clear ───────────────────────────────────────────────

		_bindResetData: function () {
			var self = this;

			this.$form.off( 'reset_data.wse' )
				.on( 'reset_data.wse', function () { self._onResetData(); } );

			this.$scope.off( 'click.wse', '.wse-reset-link' )
				.on( 'click.wse', '.wse-reset-link', function ( e ) {
					e.preventDefault();
					self.$form.find( '.variations select' ).val( '' ).trigger( 'change' );
					self._onResetData();
				} );
		},

		_onResetData: function () {
			this.$scope.find( '.wse-swatch' )
				.removeClass( 'selected' )
				.attr( 'aria-checked', 'false' )
				.attr( 'tabindex', '-1' );

			this.$scope.find( '.wse-swatch-wrap' ).each( function () {
				$( this ).find( '.wse-swatch:not(.disabled)' ).first().attr( 'tabindex', '0' );
			} );

			this.$scope.find( '.wse-reset-link' ).hide();
			this.$scope.find( '.wse-attr-selected-val' ).text( '' );
			this._restoreGallery();

			$( document.body ).trigger( 'wse:swatchReset', [ { $form: this.$form } ] );
		},

		// ── Variation events + gallery (B12 fix) ────────────────────────

		_bindVariationEvents: function () {
			var self = this;

			this.$form.off( 'found_variation.wse' )
				.on( 'found_variation.wse', function ( e, variation ) {
					self._onFoundVariation( variation );
				} );

			this.$form.off( 'hide_variation.wse' )
				.on( 'hide_variation.wse', function () { self._restoreGallery(); } );
		},

		_onFoundVariation: function ( variation ) {
			if ( ! variation || ! variation.image || ! variation.image.src ) {
				return;
			}

			var $img = this._getGalleryImage();
			if ( ! $img.length ) {
				return;
			}

			if ( ! this._origGallery ) {
				var $link = $img.closest( 'a' );
				this._origGallery = {
					src    : $img.attr( 'src' )    || '',
					srcset : $img.attr( 'srcset' ) || '',
					href   : $link.attr( 'href' )  || '',
				};
			}

			$img.attr( 'src',    variation.image.src );
			$img.attr( 'srcset', variation.image.srcset || '' );

			var $anchor = $img.closest( 'a' );
			if ( $anchor.length ) {
				$anchor.attr( 'href', variation.image.full_src || variation.image.src );
			}

			$( document.body ).trigger( 'wc_update_variation_image', [ variation ] );
		},

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
		 * v1.1.0 (B12) — Gallery selector chain.
		 *
		 * Themes with carousel/slider galleries (Astra Pro, Flatsome, Hello+
		 * builders) make a non-first-child slide visible. Try in order:
		 *   1. WC FlexSlider's active slide image
		 *   2. Any gallery image NOT marked as a clone duplicate
		 *   3. First-child fallback (matches v1.0.5 behaviour)
		 */
		_getGalleryImage: function () {
			var $active = $( '.woocommerce-product-gallery__image.flex-active-slide img' );
			if ( $active.length ) {
				return $active.first();
			}
			var $visible = $( '.woocommerce-product-gallery__image:not(.clone) img' );
			if ( $visible.length ) {
				return $visible.first();
			}
			return $( '.woocommerce-product-gallery__image:first-child img' );
		},

		// ── Reset link visibility ───────────────────────────────────────

		_updateAllResetLinks: function () {
			this.$scope.find( '.wse-swatch-wrap' ).each( function () {
				var $wrap       = $( this );
				var hasSelected = $wrap.find( '.wse-swatch.selected' ).length > 0;
				$wrap.find( '.wse-reset-link' ).toggle( hasSelected );
			} );
		},
	};

	// ─────────────────────────────────────────────────────────────────────
	// Step 4 — Cross-widget click sync (Widget 1 click → canonical select)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * When a swatch is clicked inside a .wse-widget-swatches (Widget 1)
	 * wrapper that has a canonical form somewhere on the page, this handler
	 * mirrors the value into the canonical form's hidden select. Idempotent
	 * — safe to call repeatedly via wse:reinit.
	 */
	function bindCrossWidgetSync() {
		$( document.body )
			.off( 'click.wse-cross', '.wse-widget-swatches .wse-swatch' )
			.on( 'click.wse-cross', '.wse-widget-swatches .wse-swatch', function () {
				var $swatch    = $( this );
				if ( $swatch.hasClass( 'disabled' ) ) {
					return;
				}
				var $widget1   = $swatch.closest( '.wse-widget-swatches' );
				var formId     = String( $widget1.attr( 'data-form-id' ) || '' );
				var $form      = formId ? $( '#' + formId ) : $();
				if ( ! $form.length ) {
					return; // canonical form will be wired by Step 3 alone
				}
				var attribute  = $swatch.data( 'attribute' );
				var value      = String( $swatch.data( 'value' ) || '' );
				$form
					.find( 'select[name="attribute_' + attribute + '"]' )
					.val( value )
					.trigger( 'change' );
			} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Step 5 — Init pipeline
	// ─────────────────────────────────────────────────────────────────────

	function initPipeline() {
		// 1. Dedup before WC binds — wc_variation_form runs after .ready
		dedupeSwatchUI();

		// 2. Wrap orphans (Widget 1 alone)
		wrapOrphanWidget1();

		// 3. Wire WSE_Swatches per canonical form
		$( 'form.variations_form' ).each( function () {
			var $form = $( this );
			if ( $form.data( 'wse-swatches' ) ) {
				return; // already initialised
			}
			// Scope: prefer Widget 1 wrapper for this product if present,
			// else default to the form itself.
			var productId = String( $form.attr( 'data-product_id' ) || '' );
			var $widget1  = productId
				? $( '.wse-widget-swatches[data-product-id="' + productId + '"]' )
				: $();
			var $scope    = $widget1.length ? $widget1 : $form;

			$form.data( 'wse-swatches', new WSE_Swatches( $form, $scope ) );
		} );

		// 4. Cross-widget click sync (only effective when Widget 1 + canonical
		//    are wired against different scopes)
		bindCrossWidgetSync();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Bootstrap
	// ─────────────────────────────────────────────────────────────────────

	$( document ).ready( initPipeline );

	$( document.body ).on(
		'wc_variation_form woocommerce_variation_has_changed ' +
		'post-load elementor/popup/show wse:reinit',
		initPipeline
	);

} )( jQuery, window, document );
