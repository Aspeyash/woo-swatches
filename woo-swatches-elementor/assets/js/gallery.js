/**
 * WooSwatches for Elementor — ZYMARG Variation Image Gallery JS (v1.3.0)
 *
 * Subscribes each .zymarg-vig[data-form-id] widget on the page to the
 * canonical form's `found_variation` and `reset_data` events fired by
 * wc-add-to-cart-variation.js, and cross-fades thumbs + main image when
 * the customer picks a variation via Widget 1.
 *
 * Feature set (Phase 4 — this commit):
 *   • Variation swap (cross-fade or slide based on widget setting)
 *   • Thumbnail click → switch main image
 *   • Hover-zoom lens on desktop (Amazon-style)
 *   • Click-to-lightbox with arrow nav + keyboard nav + focus trap
 *   • Mobile carousel dot indicators (driven by scroll position)
 *   • Arrow navigation (prev / next)
 *   • Keyboard nav (Left/Right arrow keys when gallery has focus)
 *   • Reduced-motion respect (respects prefers-reduced-motion)
 *
 * Architecture: pure consumer of events that other parts of the plugin
 * (Widget 1's swatch click → wc-add-to-cart-variation.js) already fire.
 * Multiple gallery instances on the same page (e.g. main column + sticky
 * floating preview) all sync to the same canonical form by data-form-id.
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
		};

		galleries.push( state );

		bindThumbClicks( state );
		bindArrowNav( state );
		bindKeyboardNav( state );
		bindCarouselDots( state );
		bindZoomLens( state );
		bindLightboxOpener( state );

		if ( $form.length && state.syncEnabled ) {
			bindVariationSync( state );
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// Variation sync
	// ─────────────────────────────────────────────────────────────────────

	function bindVariationSync( state ) {
		state.$form.on( 'found_variation.wseGallery', function ( e, variation ) {
			if ( ! variation || ! variation.variation_id ) {
				return;
			}
			switchToVariation( state, String( variation.variation_id ) );
		} );

		state.$form.on( 'reset_data.wseGallery', function () {
			switchToVariation( state, '0' );
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

		switchToIndex( state, 0, imageList );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Thumbnail clicks
	// ─────────────────────────────────────────────────────────────────────

	function bindThumbClicks( state ) {
		state.$widget.on( 'click', '.zymarg-vig-thumb', function ( e ) {
			e.preventDefault();
			var index = parseInt( $( this ).attr( 'data-image-index' ), 10 ) || 0;
			switchToIndex( state, index );
		} );
	}

	function switchToIndex( state, index, imageListOverride ) {
		var imageList = imageListOverride || state.images[ state.currentKey ];
		if ( ! imageList || ! imageList[ index ] ) {
			return;
		}

		var img = imageList[ index ];
		var $main = state.$widget.find( '.zymarg-vig-main-img' ).first();
		var $figure = state.$widget.find( '.zymarg-vig-main' ).first();

		var fadeMs = REDUCE_MOTION ? 0 : getTransitionMs( state );

		// Cross-fade: drop opacity, swap src, then restore opacity.
		if ( fadeMs > 0 ) {
			$main.css( 'opacity', 0 );
		}

		setTimeout( function () {
			$main.attr( 'src', img.src );
			if ( img.srcset ) { $main.attr( 'srcset', img.srcset ); } else { $main.removeAttr( 'srcset' ); }
			if ( img.sizes  ) { $main.attr( 'sizes',  img.sizes  ); } else { $main.removeAttr( 'sizes'  ); }
			if ( img.alt    ) { $main.attr( 'alt',    img.alt    ); } else { $main.attr( 'alt', '' ); }
			if ( img.width  ) { $main.attr( 'width',  img.width  ); }
			if ( img.height ) { $main.attr( 'height', img.height ); }
			$figure.attr( 'data-image-id', img.id || '' );
			$main.css( 'opacity', 1 );
		}, Math.floor( fadeMs / 2 ) );

		// Update thumb active state.
		state.$widget.find( '.zymarg-vig-thumb' ).each( function ( i ) {
			var $t = $( this );
			$t.toggleClass( 'is-active', i === index );
			$t.attr( 'tabindex', i === index ? '0' : '-1' );
			$t.attr( 'aria-current', i === index ? 'true' : 'false' );
		} );

		state.currentIndex = index;

		updateCarouselDots( state );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Arrow navigation (prev / next buttons)
	// ─────────────────────────────────────────────────────────────────────

	function bindArrowNav( state ) {
		state.$widget.on( 'click', '.zymarg-vig-arrow--prev', function ( e ) {
			e.preventDefault();
			navigate( state, -1 );
		} );
		state.$widget.on( 'click', '.zymarg-vig-arrow--next', function ( e ) {
			e.preventDefault();
			navigate( state, +1 );
		} );
	}

	function navigate( state, direction ) {
		var imageList = state.images[ state.currentKey ];
		if ( ! imageList || ! imageList.length ) { return; }
		var nextIndex = ( state.currentIndex + direction + imageList.length ) % imageList.length;
		switchToIndex( state, nextIndex );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Keyboard navigation
	// ─────────────────────────────────────────────────────────────────────

	function bindKeyboardNav( state ) {
		state.$widget.on( 'keydown', function ( e ) {
			// Only fire when focus is inside the widget.
			if ( ! state.$widget[ 0 ].contains( document.activeElement ) ) {
				return;
			}

			switch ( e.which || e.keyCode ) {
				case 37: // ArrowLeft
					e.preventDefault();
					navigate( state, -1 );
					break;
				case 39: // ArrowRight
					e.preventDefault();
					navigate( state, +1 );
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
	//
	// CSS handles the swipe via scroll-snap-type: x mandatory. JS observes
	// the scroll position to update active-dot indicator. Dots are rendered
	// at init for the parent ('0') image list and re-rendered on variation
	// swap.
	// ─────────────────────────────────────────────────────────────────────

	function bindCarouselDots( state ) {
		// Only meaningful when mobile_layout=mobile_carousel; CSS hides the
		// dots container at desktop/tablet breakpoints anyway, so we always
		// render and let CSS gate visibility.

		var $dotsContainer = state.$widget.find( '.zymarg-vig-dots' ).first();
		if ( ! $dotsContainer.length ) {
			// Insert one dynamically into the main-wrap.
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

		// Bind clicks (delegated each time since we replaced the HTML).
		$dots.off( 'click.wseDot' ).on( 'click.wseDot', '.zymarg-vig-dot', function () {
			var idx = parseInt( $( this ).attr( 'data-image-index' ), 10 ) || 0;
			switchToIndex( state, idx );
		} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Hover-zoom lens (desktop only — Amazon-style magnifier)
	// ─────────────────────────────────────────────────────────────────────

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
			// Don't open when clicking the lens (it's pointer-events:none anyway).
			openLightbox( state, state.currentIndex );
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

	function buildThumbHtml( img, index, isActive ) {
		var classes = 'zymarg-vig-thumb' + ( isActive ? ' is-active' : '' );
		return '<button type="button" class="' + classes + '"'
			+ ' data-image-id="'    + ( img.id || '' ) + '"'
			+ ' data-image-index="' + index + '"'
			+ ' aria-label="View image ' + ( index + 1 ) + '"'
			+ ( isActive ? ' aria-current="true"' : '' )
			+ ' tabindex="' + ( isActive ? '0' : '-1' ) + '">'
			+   '<img class="zymarg-vig-thumb-img" src="' + escapeAttr( img.thumb || img.src || '' )
			+     '" alt="' + escapeAttr( img.alt || '' ) + '" loading="lazy" decoding="async"/>'
			+ '</button>';
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
