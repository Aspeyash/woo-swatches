/**
 * WooSwatches for Elementor — ZYMARG Variation Image Gallery JS (v1.4.2)
 *
 * Subscribes each .zymarg-vig[data-form-id] widget on the page to the
 * canonical form's `found_variation` and `reset_data` events fired by
 * wc-add-to-cart-variation.js, and cross-fades thumbs + main image when
 * the customer picks a variation via Widget 1.
 *
 * Feature set (v1.3.2 expanded):
 *   • Variation swap (cross-fade or slide based on widget setting)
 *   • Thumbnail click → switch main image
 *   • Hover-zoom lens on desktop (Amazon-style)
 *   • Click-to-lightbox with arrow nav + keyboard nav + focus trap + SWIPE
 *   • Mobile carousel: REAL horizontal scroll-snap strip of all images
 *     with bidirectional sync (scroll updates active thumb; thumb click
 *     scrolls carousel)
 *   • Mobile carousel dot indicators (driven by scroll position)
 *   • Image counter overlay (1 / N) — editable format, mobile/tablet only
 *   • Full keyboard navigation (Left/Right/Up/Down + Home/End + roving
 *     tabindex on thumbs so arrow keys keep firing after a click)
 *   • Mouse drag-to-scroll on thumbnail strip (Apple Store / Nike pattern)
 *   • Reduced-motion respect (prefers-reduced-motion)
 *
 * Architecture: pure consumer of events that other parts of the plugin
 * (Widget 1's swatch click → wc-add-to-cart-variation.js) already fire.
 * Multiple gallery instances on the same page (e.g. main column + sticky
 * floating preview) all sync to the same canonical form by data-form-id.
 *
 * Central navigation point: every input source (thumb click, arrow key,
 * carousel scroll, dot click, keyboard, swipe, lightbox prev/next) feeds
 * into switchToIndex(state, index), which is the single source of truth
 * for "the active image just changed".
 *
 * @package WooSwatchesElementor
 * @since   1.3.0
 */
( function ( $, WSEParams ) {
	'use strict';

	// Per-page registry of bound galleries (lets the lightbox know which
	// gallery's images list to use when navigating with arrow keys).
	var galleries = [];

	// Lazily-created shared lightbox modal (one per page, regardless of
	// how many gallery widgets are on the page).
	var $lightbox = null;
	var lightboxState = null;

	var REDUCE_MOTION = window.matchMedia
		&& window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	// ─────────────────────────────────────────────────────────────────────
	// Init
	// ─────────────────────────────────────────────────────────────────────

	$( function () {
		$( '.zymarg-vig' ).each( function () {
			initGallery( $( this ) );
		} );

		// Elementor editor: re-init when the widget is added / re-rendered.
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction(
				'frontend/element_ready/zymarg-variation-image-gallery.default',
				function ( $scope ) {
					$scope.find( '.zymarg-vig' ).each( function () {
						initGallery( $( this ) );
					} );
				}
			);
		}
	} );

	function initGallery( $widget ) {
		if ( ! $widget.length || $widget.data( 'wse-vig-bound' ) ) {
			return;
		}
		$widget.data( 'wse-vig-bound', true );

		var raw = $widget.attr( 'data-variation-images' );
		var images = raw ? safeParseJSON( raw ) : null;
		if ( ! images ) {
			return; // can't operate without the variation→images map
		}

		var formId = $widget.attr( 'data-form-id' );
		var $form  = formId ? $( '#' + formId ) : $();

		var state = {
			$widget:        $widget,
			$form:          $form,
			images:         images,
			currentKey:     '0',                          // '0' = parent
			currentIndex:   0,
			syncEnabled:    '1' === String( $widget.attr( 'data-sync-enabled' )    || '1' ),
			fallbackParent: '1' === String( $widget.attr( 'data-fallback-parent' ) || '1' ),
			// v1.3.2 — internal flag to suppress focus-restore during
			// programmatic carousel scroll so the page doesn't jump.
			programmaticScroll: false,
			// v1.3.2 — guard against scroll-handler / thumb-click feedback
			// loops (carousel scroll updates thumb, thumb click scrolls
			// carousel — without this, both sides keep firing each other).
			suppressScrollSync: false,
			// v1.4.0 — Variation-images-in-gallery + reverse-sync state.
			gallerySource:       String( $widget.attr( 'data-gallery-source' )    || 'parent_only' ),
			triggerSelection:    '1' === String( $widget.attr( 'data-trigger-selection' ) || '0' ),
			hoverPreviewEnabled: '1' === String( $widget.attr( 'data-hover-preview' )     || '0' ),
			imageBearingAttrs:   safeParseJSON( $widget.attr( 'data-image-bearing-attrs' ) || '[]' ) || [],
			// Loop prevention for reverse-sync. When the gallery responds
			// to a swatch-driven found_variation event, we must not turn
			// around and re-trigger swatch selection from that update.
			suppressSwatchSync:  false,
			// Hover-preview save/restore state (S6 — desktop only).
			hoverPreviewBackup:  null,
		};

		galleries.push( state );

		bindThumbClicks( state );
		bindArrowNav( state );
		bindKeyboardNav( state );
		bindCarouselDots( state );
		bindCarouselScroll( state );      // v1.3.2 (F4)
		bindThumbDragScroll( state );     // v1.3.2 (S3)
		bindMainSwipe( state );           // v1.3.3 (F3) — touch swipe on main image
		bindHoverPreview( state );        // v1.4.0 (S6) — desktop hover-to-preview
		bindZoomLens( state );
		bindLightboxOpener( state );

		if ( $form.length && state.syncEnabled ) {
			bindVariationSync( state );
		}

		// v1.3.2 (F6) — Initialize counter text from current state on bind.
		updateImageCounter( state );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Variation sync
	// ─────────────────────────────────────────────────────────────────────

	function bindVariationSync( state ) {
		state.$form.on( 'found_variation.wseGallery', function ( e, variation ) {
			if ( ! variation || ! variation.variation_id ) {
				return;
			}
			// v1.4.0 — Loop prevention: this handler responds to a
			// swatch-driven event, so we must NOT trigger reverse-sync
			// (back into swatches) when the resulting switchToIndex
			// fires. Suppress for this synchronous call chain; release
			// on next tick once WC's chain has finished.
			state.suppressSwatchSync = true;

			// v1.4.2 (B2) — When gallery_source is 'both' or 'variation_only',
			// the initial extended image list contains ALL variation featured
			// images alongside parent gallery images. We must NOT rebuild the
			// thumbnail strip from the per-variation key (which only holds
			// that one variation's image + parent gallery = reduced set).
			// Instead, find the selected variation's image within the CURRENT
			// extended list and navigate to its index — keeping all thumbnails
			// visible. Only fall through to switchToVariation() for
			// 'parent_only' mode (v1.3.x behavior) or if the variation image
			// can't be found in the current list (defensive fallback).
			if ( state.gallerySource !== 'parent_only' ) {
				var currentList = state.images[ state.currentKey ];
				if ( currentList && currentList.length ) {
					var varId = String( variation.variation_id );
					for ( var i = 0; i < currentList.length; i++ ) {
						if ( String( currentList[ i ].variation_id || 0 ) === varId ) {
							switchToIndex( state, i );
							setTimeout( function () { state.suppressSwatchSync = false; }, 50 );
							return;
						}
					}
				}
			}

			// Fallback: parent_only mode OR variation not found in the
			// current extended list — use the legacy per-variation rebuild.
			switchToVariation( state, String( variation.variation_id ) );
			setTimeout( function () { state.suppressSwatchSync = false; }, 50 );
		} );

		state.$form.on( 'reset_data.wseGallery', function () {
			state.suppressSwatchSync = true;

			// v1.4.2 (B2) — When gallery_source != 'parent_only', reset
			// should return to the first image in the current extended list
			// (index 0) rather than rebuilding from images['0']. The v1.4.1
			// fix ensured images['0'] = extended list, so a rebuild would
			// produce the same result — but navigating by index is cheaper
			// and avoids a full DOM rebuild (no flash/flicker on reset).
			if ( state.gallerySource !== 'parent_only' ) {
				switchToIndex( state, 0 );
				setTimeout( function () { state.suppressSwatchSync = false; }, 50 );
				return;
			}

			switchToVariation( state, '0' );
			setTimeout( function () { state.suppressSwatchSync = false; }, 50 );
		} );
	}

	function switchToVariation( state, key ) {
		var imageList = state.images[ key ];

		if ( ( ! imageList || ! imageList.length ) && state.fallbackParent ) {
			imageList = state.images[ '0' ];
			key = '0';
		}
		if ( ! imageList || ! imageList.length ) {
			return;
		}

		state.currentKey   = key;
		state.currentIndex = 0;

		renderImageList( state, imageList );
	}

	function renderImageList( state, imageList ) {
		var $thumbStrip = state.$widget.find( '.zymarg-vig-thumbs' ).first();

		if ( $thumbStrip.length ) {
			var thumbsHtml = imageList.map( function ( img, i ) {
				return buildThumbHtml( img, i, 0 === i );
			} ).join( '' );
			$thumbStrip.html( thumbsHtml );
		}

		// v1.3.2 (F4) — Re-render the mobile-carousel slides too if the
		// carousel is present (mobile_carousel layout).
		var $carousel = state.$widget.find( '.zymarg-vig-carousel' ).first();
		if ( $carousel.length ) {
			var carouselHtml = imageList.map( function ( img, i ) {
				return buildCarouselSlideHtml( img, i, 0 === i, imageList.length );
			} ).join( '' );
			$carousel.html( carouselHtml );
			// Reset scroll position to the first slide.
			$carousel[0].scrollLeft = 0;
		}

		// v1.3.2 (F6) — Update counter total + reset to slide 1.
		var $counter = state.$widget.find( '.zymarg-vig-counter' ).first();
		if ( $counter.length ) {
			$counter.attr( 'data-total', imageList.length );
		}

		switchToIndex( state, 0, imageList );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Thumbnail clicks
	// ─────────────────────────────────────────────────────────────────────

	function bindThumbClicks( state ) {
		state.$widget.on( 'click', '.zymarg-vig-thumb', function ( e ) {
			// v1.3.2 (S3) — If a drag-scroll just ended, swallow the
			// resulting click so the user doesn't jump to a thumb they
			// didn't intend to select.
			if ( state.$widget.data( 'wse-was-dragging' ) ) {
				state.$widget.removeData( 'wse-was-dragging' );
				e.preventDefault();
				return;
			}
			e.preventDefault();
			var index = parseInt( $( this ).attr( 'data-image-index' ), 10 ) || 0;
			switchToIndex( state, index, null, { focusThumb: true } );
		} );
	}

	/**
	 * Central navigation point. Every input source (thumb click, arrow,
	 * keyboard, swipe, dot click, carousel scroll) routes through here.
	 *
	 * @param {Object} state
	 * @param {number} index           Target image index.
	 * @param {Array}  imageListOverride Optional alternative image list
	 *                                  (used during variation swap before
	 *                                  state.currentKey has been read).
	 * @param {Object} opts
	 * @param {boolean} opts.focusThumb     If true, move focus to the new
	 *                                       active thumb (keeps arrow keys
	 *                                       firing after a thumb click).
	 * @param {boolean} opts.skipCarouselScroll  If true, don't programmatically
	 *                                       scroll the carousel (used when
	 *                                       the carousel scroll event is
	 *                                       what triggered this call).
	 */
	function switchToIndex( state, index, imageListOverride, opts ) {
		opts = opts || {};
		var imageList = imageListOverride || state.images[ state.currentKey ];
		if ( ! imageList || ! imageList[ index ] ) {
			return;
		}

		var img = imageList[ index ];
		var $main = state.$widget.find( '.zymarg-vig-main-img' ).first();
		var $figure = state.$widget.find( '.zymarg-vig-main' ).first();

		var fadeMs = REDUCE_MOTION ? 0 : getTransitionMs( state );

		// Cross-fade: drop opacity, swap src, then restore opacity.
		if ( fadeMs > 0 && $main.length ) {
			$main.css( 'opacity', 0 );
		}

		setTimeout( function () {
			if ( $main.length ) {
				$main.attr( 'src', img.src );
				if ( img.srcset ) { $main.attr( 'srcset', img.srcset ); } else { $main.removeAttr( 'srcset' ); }
				if ( img.sizes  ) { $main.attr( 'sizes',  img.sizes  ); } else { $main.removeAttr( 'sizes'  ); }
				if ( img.alt    ) { $main.attr( 'alt',    img.alt    ); } else { $main.attr( 'alt', '' ); }
				if ( img.width  ) { $main.attr( 'width',  img.width  ); }
				if ( img.height ) { $main.attr( 'height', img.height ); }
				$figure.attr( 'data-image-id', img.id || '' );
				$main.css( 'opacity', 1 );
			}
		}, Math.floor( fadeMs / 2 ) );

		// v1.3.2 (F5) — Update thumb roving tabindex + active state.
		state.$widget.find( '.zymarg-vig-thumb' ).each( function ( i ) {
			var $t = $( this );
			$t.toggleClass( 'is-active', i === index );
			$t.attr( 'tabindex', i === index ? '0' : '-1' );
			$t.attr( 'aria-current', i === index ? 'true' : 'false' );
		} );

		// v1.3.2 (F4) — Update carousel slide active state.
		state.$widget.find( '.zymarg-vig-carousel-slide' ).each( function ( i ) {
			$( this ).toggleClass( 'is-active', i === index );
		} );

		state.currentIndex = index;

		updateCarouselDots( state );
		updateImageCounter( state );

		// v1.3.2 (F4) — When something other than a carousel scroll caused
		// this switch (thumb click, arrow key, etc.), scroll the carousel
		// to match. Skip when called BY the scroll handler to avoid loop.
		if ( ! opts.skipCarouselScroll ) {
			scrollCarouselToIndex( state, index );
		}

		// v1.3.3 (F2) — Always scroll the thumb strip so the new active
		// thumb is visible, regardless of how the navigation happened
		// (arrow key, swipe, dot click, programmatic). Centers the thumb
		// in the strip without jumping the page.
		scrollThumbsToActive( state );

		// v1.4.0 — Reverse-sync: if this image is associated with a
		// variation, programmatically select the matching swatch(es) in
		// Widget 1. Suppressed when the navigation came FROM a swatch-
		// driven found_variation event (loop prevention via the flag set
		// by bindVariationSync).
		if ( state.triggerSelection && ! state.suppressSwatchSync ) {
			syncSwatchesFromGalleryImage( state, img );
		}

		// v1.3.2 (F5) — Focus the new active thumb so arrow keys keep
		// firing without the user needing to re-tab. Only when explicitly
		// requested (thumb click, keyboard nav) — not on every variation
		// swap (would steal focus from the rest of the page).
		if ( opts.focusThumb ) {
			var $activeThumb = state.$widget
				.find( '.zymarg-vig-thumb.is-active' ).first();
			if ( $activeThumb.length ) {
				try { $activeThumb[0].focus( { preventScroll: true } ); }
				catch ( err ) { try { $activeThumb[0].focus(); } catch ( e2 ) {} }
			}
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// v1.3.2 (F6) — Image counter overlay sync
	// ─────────────────────────────────────────────────────────────────────

	function updateImageCounter( state ) {
		// v1.3.3 (F4) — Update ALL counters in the widget, not just the
		// first. Layouts may render multiple (e.g. mobile_carousel renders
		// one inside the figure for non-carousel viewports + one inside
		// the carousel for the carousel viewport; CSS shows whichever is
		// visible at the current breakpoint). All read their format from
		// data-format and their total from the current image list.
		var $counters = state.$widget.find( '.zymarg-vig-counter' );
		if ( ! $counters.length ) { return; }

		var imageList = state.images[ state.currentKey ];
		if ( ! imageList ) { return; }

		$counters.each( function () {
			var $c = $( this );
			var format = $c.attr( 'data-format' ) || '{current} / {total}';
			var text   = format
				.replace( /\{current\}/g, String( state.currentIndex + 1 ) )
				.replace( /\{total\}/g,   String( imageList.length ) );
			$c.text( text );
			$c.attr( 'data-total', String( imageList.length ) );
		} );
	}

	/**
	 * v1.3.3 (F2) — Scroll the active thumb into view inside the thumb
	 * strip's scroll container. Uses direct scrollLeft / scrollTop math
	 * rather than scrollIntoView() so the page itself never jumps when
	 * the gallery is partially in viewport. Centers the active thumb in
	 * the strip when possible.
	 *
	 * Called from switchToIndex after the new active class + tabindex
	 * are applied, so the .is-active selector resolves to the just-
	 * activated thumb.
	 */
	function scrollThumbsToActive( state ) {
		var $thumbs = state.$widget.find( '.zymarg-vig-thumbs' ).first();
		var $active = state.$widget.find( '.zymarg-vig-thumb.is-active' ).first();
		if ( ! $thumbs.length || ! $active.length ) { return; }

		var strip = $thumbs[0];
		var thumb = $active[0];
		if ( ! strip || ! thumb ) { return; }

		// Only scroll if the strip is actually scrollable (otherwise it's
		// a no-op and we save an unnecessary scrollTo call).
		var hHorizontal = strip.scrollWidth  > strip.clientWidth;
		var hVertical   = strip.scrollHeight > strip.clientHeight;
		if ( ! hHorizontal && ! hVertical ) { return; }

		var behavior = REDUCE_MOTION ? 'auto' : 'smooth';

		if ( hHorizontal ) {
			// Center the active thumb horizontally inside the strip.
			var targetX = thumb.offsetLeft - ( strip.clientWidth - thumb.clientWidth ) / 2;
			targetX = Math.max( 0, Math.min( strip.scrollWidth - strip.clientWidth, targetX ) );
			try { strip.scrollTo( { left: targetX, behavior: behavior } ); }
			catch ( e ) { strip.scrollLeft = targetX; }
		}

		if ( hVertical ) {
			// Center the active thumb vertically inside the strip.
			var targetY = thumb.offsetTop - ( strip.clientHeight - thumb.clientHeight ) / 2;
			targetY = Math.max( 0, Math.min( strip.scrollHeight - strip.clientHeight, targetY ) );
			try { strip.scrollTo( { top: targetY, behavior: behavior } ); }
			catch ( e ) { strip.scrollTop = targetY; }
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// Arrow navigation (prev / next buttons)
	// ─────────────────────────────────────────────────────────────────────

	function bindArrowNav( state ) {
		state.$widget.on( 'click', '.zymarg-vig-arrow--prev', function ( e ) {
			e.preventDefault();
			navigate( state, -1, { focusThumb: false } );
		} );
		state.$widget.on( 'click', '.zymarg-vig-arrow--next', function ( e ) {
			e.preventDefault();
			navigate( state, +1, { focusThumb: false } );
		} );
	}

	function navigate( state, direction, opts ) {
		var imageList = state.images[ state.currentKey ];
		if ( ! imageList || ! imageList.length ) { return; }
		var nextIndex = ( state.currentIndex + direction + imageList.length ) % imageList.length;
		switchToIndex( state, nextIndex, null, opts || {} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// v1.3.2 (F5) — Keyboard navigation (full set)
	//
	// Arrow Left / Right / Up / Down — navigate ±1 (Up/Down for vertical
	// thumb layouts; all four are wired so users don't guess wrong).
	// Home — jump to first image.
	// End  — jump to last image.
	// Enter / Space when focus on main figure — open lightbox.
	//
	// Roving tabindex is maintained by switchToIndex() so the active thumb
	// always has tabindex=0 and arrow keys keep firing without re-tabbing.
	// ─────────────────────────────────────────────────────────────────────

	function bindKeyboardNav( state ) {
		state.$widget.on( 'keydown', function ( e ) {
			// Only fire when focus is inside the widget. Lightbox has its
			// own document-level handler.
			if ( ! state.$widget[ 0 ].contains( document.activeElement ) ) {
				return;
			}
			// Don't intercept keys inside form inputs (defensive — gallery
			// shouldn't normally contain inputs).
			var tag = ( document.activeElement && document.activeElement.tagName ) || '';
			if ( /^(INPUT|TEXTAREA|SELECT)$/.test( tag ) ) {
				return;
			}

			var imageList = state.images[ state.currentKey ];
			var lastIdx   = imageList ? imageList.length - 1 : 0;

			switch ( e.which || e.keyCode ) {
				case 37: // ArrowLeft
				case 38: // ArrowUp
					e.preventDefault();
					navigate( state, -1, { focusThumb: true } );
					break;
				case 39: // ArrowRight
				case 40: // ArrowDown
					e.preventDefault();
					navigate( state, +1, { focusThumb: true } );
					break;
				case 36: // Home
					e.preventDefault();
					switchToIndex( state, 0, null, { focusThumb: true } );
					break;
				case 35: // End
					e.preventDefault();
					switchToIndex( state, lastIdx, null, { focusThumb: true } );
					break;
				case 13: // Enter — open lightbox if focus on main image
				case 32: // Space
					if ( $( document.activeElement ).hasClass( 'zymarg-vig-main' )
					  || $( document.activeElement ).closest( '.zymarg-vig-main--lightbox' ).length ) {
						e.preventDefault();
						openLightbox( state, state.currentIndex );
					}
					break;
			}
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Mobile carousel — dot indicators
	// ─────────────────────────────────────────────────────────────────────

	function bindCarouselDots( state ) {
		var $dotsContainer = state.$widget.find( '.zymarg-vig-dots' ).first();
		if ( ! $dotsContainer.length ) {
			$dotsContainer = $( '<div class="zymarg-vig-dots" role="tablist" aria-label="Image navigation"></div>' );
			state.$widget.find( '.zymarg-vig-main-wrap' ).first().append( $dotsContainer );
		}

		updateCarouselDots( state );
	}

	function updateCarouselDots( state ) {
		var $dots = state.$widget.find( '.zymarg-vig-dots' ).first();
		if ( ! $dots.length ) { return; }

		var imageList = state.images[ state.currentKey ];
		if ( ! imageList ) { return; }

		var html = imageList.map( function ( img, i ) {
			return '<button type="button" class="zymarg-vig-dot' + ( i === state.currentIndex ? ' is-active' : '' )
				+ '" data-image-index="' + i + '" aria-label="View image ' + ( i + 1 ) + '"></button>';
		} ).join( '' );

		$dots.html( html );

		$dots.off( 'click.wseDot' ).on( 'click.wseDot', '.zymarg-vig-dot', function () {
			var idx = parseInt( $( this ).attr( 'data-image-index' ), 10 ) || 0;
			switchToIndex( state, idx );
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// v1.3.2 (F4) — Mobile carousel scroll observer
	//
	// When user swipes the .zymarg-vig-carousel, calculate which slide is
	// most-centered in the viewport and update active thumb / counter /
	// dots to match. Uses requestAnimationFrame throttling to avoid
	// stalling the scroll thread on iOS.
	// ─────────────────────────────────────────────────────────────────────

	function bindCarouselScroll( state ) {
		var $carousel = state.$widget.find( '.zymarg-vig-carousel' ).first();
		if ( ! $carousel.length ) { return; }

		var rafPending = false;

		$carousel.on( 'scroll.wseCarousel', function () {
			if ( state.suppressScrollSync ) { return; }
			if ( rafPending ) { return; }
			rafPending = true;

			window.requestAnimationFrame( function () {
				rafPending = false;

				var el = $carousel[0];
				if ( ! el ) { return; }

				var slideWidth = el.clientWidth;
				if ( slideWidth <= 0 ) { return; }

				// Index of the slide most-centered in the viewport.
				var idx = Math.round( el.scrollLeft / slideWidth );
				var imageList = state.images[ state.currentKey ];
				if ( ! imageList ) { return; }
				idx = Math.max( 0, Math.min( imageList.length - 1, idx ) );

				if ( idx !== state.currentIndex ) {
					switchToIndex( state, idx, null, { skipCarouselScroll: true } );
				}
			} );
		} );
	}

	function scrollCarouselToIndex( state, index ) {
		var $carousel = state.$widget.find( '.zymarg-vig-carousel' ).first();
		if ( ! $carousel.length ) { return; }

		var el = $carousel[0];
		if ( ! el ) { return; }

		var slideWidth = el.clientWidth;
		if ( slideWidth <= 0 ) { return; }

		var target = index * slideWidth;
		if ( Math.abs( el.scrollLeft - target ) < 4 ) { return; }

		// Suppress the scroll handler firing while we programmatically
		// scroll, then release after a short delay (smooth scrolls take
		// ~300ms on most browsers).
		state.suppressScrollSync = true;
		try {
			el.scrollTo( {
				left: target,
				behavior: REDUCE_MOTION ? 'auto' : 'smooth',
			} );
		} catch ( e ) {
			// Older browsers without scrollTo({behavior}) — fall back.
			el.scrollLeft = target;
		}
		setTimeout( function () {
			state.suppressScrollSync = false;
		}, REDUCE_MOTION ? 50 : 400 );
	}

	// ─────────────────────────────────────────────────────────────────────
	// v1.3.2 (S3) — Mouse drag-to-scroll on the thumbnail strip
	//
	// Improves the desktop UX for galleries with many thumbnails. Users
	// can click+drag the strip to scroll horizontally or vertically (CSS
	// flex-direction determines axis). A threshold of 5px distinguishes a
	// drag from a click so individual thumb clicks still work.
	// ─────────────────────────────────────────────────────────────────────

	function bindThumbDragScroll( state ) {
		var $thumbs = state.$widget.find( '.zymarg-vig-thumbs' ).first();
		if ( ! $thumbs.length ) { return; }

		var el = $thumbs[0];
		var dragging = false;
		var startX, startY, startScrollLeft, startScrollTop;
		var DRAG_THRESHOLD = 5; // px before a mousedown becomes a drag

		$thumbs.on( 'mousedown.wseDrag', function ( e ) {
			// Only main button (left click).
			if ( 0 !== e.button ) { return; }
			dragging = true;
			startX = e.pageX;
			startY = e.pageY;
			startScrollLeft = el.scrollLeft;
			startScrollTop  = el.scrollTop;
			$thumbs.addClass( 'wse-vig-drag-active' );
		} );

		$( document ).on( 'mousemove.wseDrag-' + Math.random().toString( 36 ).slice( 2 ), function ( e ) {
			if ( ! dragging ) { return; }
			var dx = e.pageX - startX;
			var dy = e.pageY - startY;

			if ( Math.abs( dx ) > DRAG_THRESHOLD || Math.abs( dy ) > DRAG_THRESHOLD ) {
				state.$widget.data( 'wse-was-dragging', true );
			}

			el.scrollLeft = startScrollLeft - dx;
			el.scrollTop  = startScrollTop  - dy;
		} );

		$( document ).on( 'mouseup.wseDrag', function () {
			if ( ! dragging ) { return; }
			dragging = false;
			$thumbs.removeClass( 'wse-vig-drag-active' );
			// Clear the was-dragging flag on next tick so the upcoming
			// click (if any) can read it; the click handler then clears.
			setTimeout( function () {
				if ( state.$widget.data( 'wse-was-dragging' ) ) {
					state.$widget.removeData( 'wse-was-dragging' );
				}
			}, 50 );
		} );

		// Cancel drag if the cursor leaves the window.
		$thumbs.on( 'mouseleave.wseDrag', function () {
			if ( dragging ) {
				dragging = false;
				$thumbs.removeClass( 'wse-vig-drag-active' );
			}
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Hover-zoom lens (desktop only — Amazon-style magnifier)
	// ─────────────────────────────────────────────────────────────────────

	// ─────────────────────────────────────────────────────────────────────
	// v1.3.3 (F3) — Touch swipe on the main image figure
	//
	// In v1.3.2 we added swipe via the .zymarg-vig-carousel scroll-snap
	// strip (mobile_carousel layout only). For all OTHER mobile layouts
	// (horizontal_below, horizontal_above, mobile_stacked) the main image
	// is a single static figure with no swipe. v1.3.3 adds direct touch-
	// swipe handlers on the figure so any layout can be navigated by
	// swiping. Same threshold logic as the lightbox swipe: ≥ 50px
	// horizontal travel + ≤ 60px vertical travel to register.
	//
	// The handler is BOUND on every device (not just mobile-detected) so
	// touch-enabled laptops / Surface devices work too. The carousel-
	// scroll observer (bindCarouselScroll) is independent and handles
	// mobile_carousel layout's scroll-snap on its own; the two don't
	// conflict because in mobile_carousel the .zymarg-vig-main figure is
	// hidden via CSS, so touchstart on it never fires there.
	// ─────────────────────────────────────────────────────────────────────

	function bindMainSwipe( state ) {
		var $main = state.$widget.find( '.zymarg-vig-main' ).first();
		if ( ! $main.length ) { return; }

		var startX  = 0;
		var startY  = 0;
		var tracking = false;
		var SWIPE_THRESHOLD       = 50; // px horizontal travel required
		var SWIPE_VERTICAL_TOLER  = 60; // px max vertical travel

		$main.on( 'touchstart.wseMainSwipe', function ( e ) {
			var t = e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0];
			if ( ! t ) { return; }
			startX   = t.clientX;
			startY   = t.clientY;
			tracking = true;
		} );

		$main.on( 'touchmove.wseMainSwipe', function ( e ) {
			if ( ! tracking ) { return; }
			var t = e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0];
			if ( ! t ) { return; }
			var dx = Math.abs( t.clientX - startX );
			var dy = Math.abs( t.clientY - startY );
			// If horizontal gesture is dominant, prevent default so the
			// page doesn't scroll vertically while we're swiping. Let
			// vertical-dominant gestures pass through (page scroll wins).
			if ( dx > dy && dx > 10 ) {
				e.preventDefault();
			}
		} );

		$main.on( 'touchend.wseMainSwipe touchcancel.wseMainSwipe', function ( e ) {
			if ( ! tracking ) { return; }
			tracking = false;
			var t = e.originalEvent && e.originalEvent.changedTouches && e.originalEvent.changedTouches[0];
			if ( ! t ) { return; }
			var dx = t.clientX - startX;
			var dy = t.clientY - startY;
			if ( Math.abs( dx ) >= SWIPE_THRESHOLD && Math.abs( dy ) <= SWIPE_VERTICAL_TOLER ) {
				// Swipe LEFT (dx negative) → next image
				// Swipe RIGHT (dx positive) → previous image
				navigate( state, dx < 0 ? +1 : -1, { focusThumb: false } );
			}
		} );
	}

	function bindZoomLens( state ) {
		var $main = state.$widget.find( '.zymarg-vig-main--zoomable' ).first();
		if ( ! $main.length ) { return; }

		var $img  = $main.find( '.zymarg-vig-main-img' ).first();
		var $lens = $main.find( '.zymarg-vig-zoom-lens' ).first();
		if ( ! $lens.length ) { return; }

		// Disable on touch devices — pinch-zoom natively works.
		var isTouch = ( 'ontouchstart' in window ) || ( navigator.maxTouchPoints > 0 );
		if ( isTouch ) { return; }

		var ZOOM_FACTOR = 2.5;

		$main.on( 'mouseenter.wseZoom', function () {
			$lens.css( 'display', 'block' );
		} );

		$main.on( 'mouseleave.wseZoom', function () {
			$lens.css( 'display', 'none' );
			$img.css( 'transform', '' );
			$img.css( 'transform-origin', '' );
		} );

		$main.on( 'mousemove.wseZoom', function ( e ) {
			var rect = $main[0].getBoundingClientRect();
			var x    = e.clientX - rect.left;
			var y    = e.clientY - rect.top;
			var pctX = ( x / rect.width  ) * 100;
			var pctY = ( y / rect.height ) * 100;

			$img.css( 'transform-origin', pctX + '% ' + pctY + '%' );
			$img.css( 'transform', 'scale(' + ZOOM_FACTOR + ')' );

			// Position lens following cursor. Read size from CSS var so the
			// Style → Zoom Lens → Lens size control takes effect at runtime.
			var lensVar  = getComputedStyle( $main[0] ).getPropertyValue( '--zymarg-vig-lens-size' ).trim();
			var lensSize = parseInt( lensVar, 10 );
			if ( isNaN( lensSize ) || lensSize < 20 ) { lensSize = 80; }
			$lens.css( {
				width:  lensSize + 'px',
				height: lensSize + 'px',
				left:   ( x - lensSize / 2 ) + 'px',
				top:    ( y - lensSize / 2 ) + 'px',
			} );
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Lightbox
	// ─────────────────────────────────────────────────────────────────────

	function bindLightboxOpener( state ) {
		state.$widget.on( 'click', '.zymarg-vig-main--lightbox', function ( e ) {
			openLightbox( state, state.currentIndex );
		} );
		// v1.3.2 — Clicking a carousel slide also opens the lightbox.
		state.$widget.on( 'click', '.zymarg-vig-carousel-slide', function () {
			var i = parseInt( $( this ).attr( 'data-image-index' ), 10 ) || 0;
			openLightbox( state, i );
		} );
	}

	function openLightbox( state, startIndex ) {
		ensureLightboxDom();

		lightboxState = {
			state: state,
			index: startIndex,
		};

		renderLightboxImage();

		$lightbox.attr( 'aria-hidden', 'false' );
		$lightbox.addClass( 'is-open' );
		document.body.style.overflow = 'hidden';

		// Save focus to restore on close.
		lightboxState.lastFocus = document.activeElement;

		// Focus close button so keyboard users can dismiss.
		$lightbox.find( '.zymarg-vig-lb-close' ).first().trigger( 'focus' );
	}

	function closeLightbox() {
		if ( ! $lightbox ) { return; }
		$lightbox.removeClass( 'is-open' );
		$lightbox.attr( 'aria-hidden', 'true' );
		document.body.style.overflow = '';

		if ( lightboxState && lightboxState.lastFocus ) {
			try { lightboxState.lastFocus.focus(); } catch ( err ) { /* noop */ }
		}
		lightboxState = null;
	}

	function ensureLightboxDom() {
		if ( $lightbox ) { return; }

		var html = ''
			+ '<div class="zymarg-vig-lightbox" role="dialog" aria-modal="true" aria-hidden="true" aria-label="Product image gallery">'
			+   '<button type="button" class="zymarg-vig-lb-close" aria-label="Close gallery">&times;</button>'
			+   '<button type="button" class="zymarg-vig-lb-arrow zymarg-vig-lb-arrow--prev" aria-label="Previous image">&#10094;</button>'
			+   '<div class="zymarg-vig-lb-stage"><img class="zymarg-vig-lb-img" alt=""/></div>'
			+   '<button type="button" class="zymarg-vig-lb-arrow zymarg-vig-lb-arrow--next" aria-label="Next image">&#10095;</button>'
			+   '<div class="zymarg-vig-lb-counter" aria-live="polite"></div>'
			+ '</div>';

		$lightbox = $( html );
		$( document.body ).append( $lightbox );

		// Wire events once.
		$lightbox.on( 'click', '.zymarg-vig-lb-close', closeLightbox );
		$lightbox.on( 'click', '.zymarg-vig-lb-arrow--prev', function () { lightboxNav( -1 ); } );
		$lightbox.on( 'click', '.zymarg-vig-lb-arrow--next', function () { lightboxNav( +1 ); } );

		// Click backdrop (the lightbox itself, not its children) closes.
		$lightbox.on( 'click', function ( e ) {
			if ( e.target === $lightbox[0] ) { closeLightbox(); }
		} );

		$( document ).on( 'keydown.wseLightbox', function ( e ) {
			if ( ! $lightbox.hasClass( 'is-open' ) ) { return; }
			switch ( e.which || e.keyCode ) {
				case 27: // Esc
					closeLightbox();
					break;
				case 37: // Left
					lightboxNav( -1 );
					break;
				case 39: // Right
					lightboxNav( +1 );
					break;
			}
		} );

		// v1.3.2 (S1) — Touch swipe gestures inside the lightbox stage.
		// Track horizontal delta on the stage element; if the swipe is
		// >50px and predominantly horizontal, treat it as a prev/next.
		bindLightboxSwipe();
	}

	function bindLightboxSwipe() {
		var $stage = $lightbox.find( '.zymarg-vig-lb-stage' );
		if ( ! $stage.length ) { return; }

		var startX  = 0;
		var startY  = 0;
		var tracking = false;
		var SWIPE_THRESHOLD       = 50; // px horizontal travel required
		var SWIPE_VERTICAL_TOLER  = 60; // px max vertical travel to still count as horizontal

		$stage.on( 'touchstart.wseLbSwipe', function ( e ) {
			var t = e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0];
			if ( ! t ) { return; }
			startX   = t.clientX;
			startY   = t.clientY;
			tracking = true;
		} );

		$stage.on( 'touchmove.wseLbSwipe', function ( e ) {
			// Prevent vertical scroll-bounce while user is swiping the
			// lightbox horizontally; let it through if the gesture turns
			// out to be vertical.
			if ( ! tracking ) { return; }
			var t = e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0];
			if ( ! t ) { return; }
			var dx = Math.abs( t.clientX - startX );
			var dy = Math.abs( t.clientY - startY );
			if ( dx > dy && dx > 10 ) {
				e.preventDefault();
			}
		} );

		$stage.on( 'touchend.wseLbSwipe touchcancel.wseLbSwipe', function ( e ) {
			if ( ! tracking ) { return; }
			tracking = false;
			var t = e.originalEvent && e.originalEvent.changedTouches && e.originalEvent.changedTouches[0];
			if ( ! t ) { return; }
			var dx = t.clientX - startX;
			var dy = t.clientY - startY;
			if ( Math.abs( dx ) >= SWIPE_THRESHOLD && Math.abs( dy ) <= SWIPE_VERTICAL_TOLER ) {
				lightboxNav( dx < 0 ? +1 : -1 );
			}
		} );
	}

	function renderLightboxImage() {
		if ( ! lightboxState ) { return; }
		var s         = lightboxState.state;
		var imageList = s.images[ s.currentKey ];
		if ( ! imageList || ! imageList.length ) { return; }

		var idx = ( ( lightboxState.index % imageList.length ) + imageList.length ) % imageList.length;
		lightboxState.index = idx;
		var img = imageList[ idx ];

		var $imgEl = $lightbox.find( '.zymarg-vig-lb-img' ).first();
		$imgEl.attr( 'src', img.src );
		if ( img.srcset ) { $imgEl.attr( 'srcset', img.srcset ); } else { $imgEl.removeAttr( 'srcset' ); }
		if ( img.sizes  ) { $imgEl.attr( 'sizes',  img.sizes  ); } else { $imgEl.removeAttr( 'sizes'  ); }
		$imgEl.attr( 'alt', img.alt || '' );

		$lightbox.find( '.zymarg-vig-lb-counter' ).first().text( ( idx + 1 ) + ' / ' + imageList.length );
	}

	function lightboxNav( direction ) {
		if ( ! lightboxState ) { return; }
		lightboxState.index += direction;
		renderLightboxImage();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * v1.4.0 — Reverse-sync from gallery image to swatches.
	 *
	 * When the customer navigates the gallery to an image that's
	 * associated with a variation (clicked thumb, swiped carousel,
	 * arrow-key, dot click, lightbox prev/next — all routed through
	 * switchToIndex), this helper programmatically selects the matching
	 * swatch(es) in Widget 1. The swatch click cascades through the
	 * existing v1.3.x chain (selectSwatch → form change → WC's
	 * found_variation → price update → add-to-cart enable → smart
	 * heading recompute), so the entire plugin updates as if the
	 * customer clicked the swatch directly.
	 *
	 * S4 multi-attribute behavior:
	 *   - state.imageBearingAttrs is the array of attribute keys
	 *     auto-detected as image-bearing (e.g., ['attribute_color']).
	 *     When non-empty, only those attributes get set — preserving
	 *     the customer's existing Size or other non-image picks.
	 *   - When the array is empty (settings: 'all' mode, or single-
	 *     attribute product, or auto-detect found nothing), every
	 *     attribute on the variation gets set. Also handles the case
	 *     where the image is associated with a variation but has no
	 *     attributes (defensive — no-op).
	 *
	 * @param {Object} state Gallery state.
	 * @param {Object} img   Image record from the gallery list. Carries
	 *                       variation_id (number) and attributes (assoc).
	 */
	function syncSwatchesFromGalleryImage( state, img ) {
		if ( ! img ) { return; }
		var variationId = parseInt( img.variation_id, 10 );
		if ( isNaN( variationId ) || variationId <= 0 ) {
			return;   // parent-only image — no reverse-sync
		}
		var attrs = img.attributes || {};
		var attrKeys = Object.keys( attrs );
		if ( ! attrKeys.length ) {
			return;   // variation associated but no attributes — defensive
		}

		// Find Widget 1 on the same page. Look for the matching attr-block
		// inside the canonical form's surrounding swatches widget. If
		// Widget 1 isn't on the page, skip silently.
		var $widgetSwatches = $( '.wse-widget-swatches' );
		if ( ! $widgetSwatches.length ) { return; }

		// Apply S4 image-bearing filter. When state.imageBearingAttrs has
		// entries, restrict to those. When empty, apply all.
		var keysToSet = attrKeys;
		if ( state.imageBearingAttrs && state.imageBearingAttrs.length ) {
			keysToSet = attrKeys.filter( function ( k ) {
				return state.imageBearingAttrs.indexOf( k ) !== -1;
			} );
			if ( ! keysToSet.length ) {
				// Image-bearing list provided but none of the variation's
				// attribute keys match it — fall back to setting all to
				// avoid a silent no-op.
				keysToSet = attrKeys;
			}
		}

		keysToSet.forEach( function ( attrKey ) {
			// attrKey is in the form 'attribute_color' or 'attribute_pa_color'
			// (with pa_ prefix for taxonomy attributes). Strip 'attribute_'
			// to get the slug used in .wse-swatch-wrap[data-attribute].
			var slug = String( attrKey ).replace( /^attribute_/, '' );
			var value = String( attrs[ attrKey ] || '' );
			if ( ! slug || ! value ) { return; }

			// Find every Widget 1 instance and the matching swatch.
			$widgetSwatches.each( function () {
				var $wrap = $( this ).find(
					'.wse-swatch-wrap[data-attribute="' + slug + '"]'
				);
				if ( ! $wrap.length ) { return; }
				var $swatch = $wrap.find(
					'.wse-swatch[data-value="' + value + '"]'
				);
				if ( ! $swatch.length ) { return; }
				if ( $swatch.hasClass( 'selected' ) ) { return; }
				if ( $swatch.hasClass( 'disabled' ) ) { return; }

				// Trigger the click — same chain as a real swatch click.
				$swatch.trigger( 'click.wse' );
			} );
		} );
	}

	/**
	 * v1.4.0 (S6) — Desktop hover-to-preview for variation thumbnails.
	 *
	 * Hovering a variation thumb temporarily swaps the main image to
	 * that variation's image (without committing the selection).
	 * Mouse-leave reverts to whatever was active before the hover.
	 * Click commits via the normal switchToIndex flow.
	 *
	 * Touch devices ignore hover events (no mouseenter on tap), so this
	 * is naturally desktop-only without needing an explicit gate.
	 */
	function bindHoverPreview( state ) {
		if ( ! state.hoverPreviewEnabled ) { return; }

		state.$widget.on( 'mouseenter.wseHoverPreview',
			'.zymarg-vig-thumb--variation', function () {
				var $main = state.$widget.find( '.zymarg-vig-main-img' ).first();
				if ( ! $main.length ) { return; }

				// Save the current main src/srcset/sizes/alt for restoration
				// on mouseleave. Don't double-save if a hover is already in
				// progress (rapid-fire mouseenter from sibling thumbs).
				if ( ! state.hoverPreviewBackup ) {
					state.hoverPreviewBackup = {
						src:    $main.attr( 'src'    ) || '',
						srcset: $main.attr( 'srcset' ) || '',
						sizes:  $main.attr( 'sizes'  ) || '',
						alt:    $main.attr( 'alt'    ) || '',
					};
				}

				// Find the image record for this thumb and preview it.
				var idx = parseInt( $( this ).attr( 'data-image-index' ), 10 );
				if ( isNaN( idx ) ) { return; }
				var imageList = state.images[ state.currentKey ];
				if ( ! imageList || ! imageList[ idx ] ) { return; }
				var img = imageList[ idx ];

				$main.attr( 'src', img.src );
				if ( img.srcset ) { $main.attr( 'srcset', img.srcset ); }
				if ( img.sizes  ) { $main.attr( 'sizes',  img.sizes  ); }
				if ( img.alt    ) { $main.attr( 'alt',    img.alt    ); }
			}
		);

		state.$widget.on( 'mouseleave.wseHoverPreview',
			'.zymarg-vig-thumb--variation', function () {
				if ( ! state.hoverPreviewBackup ) { return; }
				var $main = state.$widget.find( '.zymarg-vig-main-img' ).first();
				if ( ! $main.length ) { return; }

				// Defer restore one tick so a click on the same thumb
				// (which fires mouseleave just before click) doesn't
				// race the click's switchToIndex.
				setTimeout( function () {
					if ( ! state.hoverPreviewBackup ) { return; }
					$main.attr( 'src', state.hoverPreviewBackup.src );
					if ( state.hoverPreviewBackup.srcset ) {
						$main.attr( 'srcset', state.hoverPreviewBackup.srcset );
					} else { $main.removeAttr( 'srcset' ); }
					if ( state.hoverPreviewBackup.sizes ) {
						$main.attr( 'sizes', state.hoverPreviewBackup.sizes );
					} else { $main.removeAttr( 'sizes' ); }
					$main.attr( 'alt', state.hoverPreviewBackup.alt );
					state.hoverPreviewBackup = null;
				}, 30 );
			}
		);

		// On click, the thumb's switchToIndex will run and update the
		// main image. Clear the backup so mouseleave doesn't revert
		// after the commit.
		state.$widget.on( 'click.wseHoverPreview',
			'.zymarg-vig-thumb--variation', function () {
				state.hoverPreviewBackup = null;
			}
		);
	}

	function buildThumbHtml( img, index, isActive ) {
		var classes = 'zymarg-vig-thumb' + ( isActive ? ' is-active' : '' );
		// v1.4.0 — variation association for reverse-sync + S5 lazy-load.
		var variationId = parseInt( img.variation_id, 10 );
		var hasVar      = ! isNaN( variationId ) && variationId > 0;
		if ( hasVar ) { classes += ' zymarg-vig-thumb--variation'; }
		var attrsJson   = ( img.attributes && Object.keys( img.attributes ).length )
			? JSON.stringify( img.attributes )
			: '';

		return '<button type="button" class="' + classes + '"'
			+ ' data-image-id="'    + ( img.id || '' ) + '"'
			+ ' data-image-index="' + index + '"'
			+ ( hasVar ? ' data-variation-id="' + variationId + '"' : '' )
			+ ( hasVar && attrsJson ? ' data-variation-attrs="' + escapeAttr( attrsJson ) + '"' : '' )
			+ ' aria-label="View image ' + ( index + 1 ) + '"'
			+ ( isActive ? ' aria-current="true"' : '' )
			+ ' tabindex="' + ( isActive ? '0' : '-1' ) + '">'
			+   '<img class="zymarg-vig-thumb-img" src="' + escapeAttr( img.thumb || img.src || '' )
			+     '" alt="' + escapeAttr( img.alt || '' ) + '"'
			+     ' loading="lazy" decoding="async"/>'
			+ '</button>';
	}

	/**
	 * v1.3.2 (F4) — Build a single mobile-carousel slide HTML string.
	 * Mirrors the server-side template in vertical-thumbs.php.
	 * v1.4.0 — Carries variation_id + attrs for reverse-sync.
	 */
	function buildCarouselSlideHtml( img, index, isActive, total ) {
		var classes = 'zymarg-vig-carousel-slide' + ( isActive ? ' is-active' : '' );
		var aria    = ( index + 1 ) + ' of ' + total;
		// v1.4.0 — variation association.
		var variationId = parseInt( img.variation_id, 10 );
		var hasVar      = ! isNaN( variationId ) && variationId > 0;
		if ( hasVar ) { classes += ' zymarg-vig-carousel-slide--variation'; }
		var attrsJson   = ( img.attributes && Object.keys( img.attributes ).length )
			? JSON.stringify( img.attributes )
			: '';

		return '<figure class="' + classes + '"'
			+ ' data-image-id="' + ( img.id || '' ) + '"'
			+ ' data-image-index="' + index + '"'
			+ ( hasVar ? ' data-variation-id="' + variationId + '"' : '' )
			+ ( hasVar && attrsJson ? ' data-variation-attrs="' + escapeAttr( attrsJson ) + '"' : '' )
			+ ' aria-roledescription="slide"'
			+ ' aria-label="' + escapeAttr( aria ) + '">'
			+   '<img class="zymarg-vig-carousel-img"'
			+     ' src="' + escapeAttr( img.src || '' ) + '"'
			+     ' alt="' + escapeAttr( img.alt || '' ) + '"'
			+     ' loading="' + ( 0 === index ? 'eager' : 'lazy' ) + '"'
			+     ' decoding="async"/>'
			+ '</figure>';
	}

	function getTransitionMs( state ) {
		var raw = state.$widget.find( '.zymarg-vig' ).first().css( '--zymarg-vig-transition-ms' )
			|| state.$widget.css( '--zymarg-vig-transition-ms' );
		var n = parseFloat( raw );
		if ( isNaN( n ) || n < 0 ) { n = 200; }
		return n;
	}

	function safeParseJSON( s ) {
		try { return JSON.parse( s ); } catch ( e ) { return null; }
	}

	function escapeAttr( s ) {
		return String( s )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /</g, '&lt;'  )
			.replace( />/g, '&gt;'  );
	}

} )( jQuery, window.WSEParams || {} );
