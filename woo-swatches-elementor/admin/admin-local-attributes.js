/**
 * WooSwatches for Elementor — Admin Local Attributes JS
 *
 * Handles the Local Attribute Swatches metabox on the product edit screen:
 *   1. Type selector — shows/hides color or image fields per attribute section
 *   2. wp-color-picker — initialised on all visible color inputs
 *   3. Per-field media uploader — each image field has its own upload button
 *      keyed by data-field-id so multiple image fields never conflict
 *
 * Loaded only on product add/edit pages via WSE_Local_Attributes::admin_enqueue()
 *
 * @package WooSwatchesElementor
 */
( function ( $, WSELocalAttr ) {
	'use strict';

	// ─────────────────────────────────────────────────────────────────────
	// 1. Type selector — show/hide color or image field groups
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * For a given attribute section, shows only the field group matching
	 * the currently selected type and hides all others.
	 *
	 * @param {jQuery} $section The .wse-local-attr-section wrapper.
	 * @param {string} type     Selected swatch type value.
	 */
	function applyTypeVisibility( $section, type ) {
		// Hide all typed field groups first
		$section.find( '[data-for-type]' ).addClass( 'wse-hidden' );

		// Show matching group (color or image only — label/button need no extra fields)
		if ( type === 'color' || type === 'image' ) {
			$section
				.find( '[data-for-type="' + type + '"]' )
				.removeClass( 'wse-hidden' );
		}

		// Reinitialise color pickers in newly visible section
		if ( type === 'color' ) {
			initColorPickersIn( $section );
		}
	}

	// Bind change on type selects — delegated so it works after AJAX reloads
	$( document ).on( 'change', '.wse-local-type-select', function () {
		var $select  = $( this );
		var $section = $select.closest( '.wse-local-attr-section' );
		applyTypeVisibility( $section, $select.val() );
	} );

	// ─────────────────────────────────────────────────────────────────────
	// 2. wp-color-picker
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Initialises wpColorPicker on every uninitialised .wse-local-color-picker
	 * inside a given container. Guards against double-initialisation.
	 *
	 * @param {jQuery} $container jQuery element to scope the search.
	 */
	function initColorPickersIn( $container ) {
		if ( typeof $.fn.wpColorPicker !== 'function' ) {
			return;
		}

		$container.find( '.wse-local-color-picker:not(.wp-color-picker)' )
			.each( function () {
				$( this ).wpColorPicker( {
					change: function ( event, ui ) {
						$( this ).val( ui.color.toString() );
					},
					clear: function () {
						$( this ).val( '' );
					},
				} );
			} );
	}

	// ─────────────────────────────────────────────────────────────────────
	// 3. Per-field media uploader
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Each image field has a unique data-field-id.
	 * We keep one wp.media frame per field-id to avoid cross-field
	 * selection conflicts when multiple attributes each have image swatches.
	 *
	 * @type {Object.<string, wp.media.view.MediaFrame>}
	 */
	var mediaFrames = {};

	/**
	 * Opens (or creates) a wp.media frame for the given field ID.
	 *
	 * @param {string} fieldId  Unique field identifier (data-field-id value).
	 */
	function openMediaUploaderFor( fieldId ) {

		if ( mediaFrames[ fieldId ] ) {
			mediaFrames[ fieldId ].open();
			return;
		}

		mediaFrames[ fieldId ] = wp.media( {
			title:    WSELocalAttr.choose_image,
			button:   { text: WSELocalAttr.use_image },
			multiple: false,
			library:  { type: 'image' },
		} );

		mediaFrames[ fieldId ].on( 'select', function () {
			var attachment = mediaFrames[ fieldId ]
				.state()
				.get( 'selection' )
				.first()
				.toJSON();

			// Write attachment ID to the hidden input
			$( '#' + fieldId ).val( attachment.id );

			// Update preview
			var previewUrl = ( attachment.sizes && attachment.sizes.thumbnail )
				? attachment.sizes.thumbnail.url
				: attachment.url;

			$( '#' + fieldId + '_preview' )
				.attr( 'src', previewUrl )
				.removeClass( 'hidden' );

			// Show remove button
			$( '[data-field-id="' + fieldId + '"].wse-local-remove-btn' )
				.removeClass( 'hidden' );
		} );

		mediaFrames[ fieldId ].open();
	}

	/**
	 * Clears image data for the given field ID.
	 *
	 * @param {string} fieldId Unique field identifier.
	 */
	function removeImageFor( fieldId ) {
		$( '#' + fieldId ).val( '' );
		$( '#' + fieldId + '_preview' )
			.attr( 'src', WSELocalAttr.placeholder )
			.addClass( 'hidden' );
		$( '[data-field-id="' + fieldId + '"].wse-local-remove-btn' )
			.addClass( 'hidden' );
	}

	// Delegated click handlers
	$( document ).on( 'click', '.wse-local-upload-btn', function ( e ) {
		e.preventDefault();
		openMediaUploaderFor( $( this ).data( 'field-id' ) );
	} );

	$( document ).on( 'click', '.wse-local-remove-btn', function ( e ) {
		e.preventDefault();
		removeImageFor( $( this ).data( 'field-id' ) );
	} );

	// ─────────────────────────────────────────────────────────────────────
	// Init on DOM ready
	// ─────────────────────────────────────────────────────────────────────

	$( function () {

		// Apply initial visibility for each attribute section
		// (based on the saved/default type already selected in the <select>)
		$( '.wse-local-attr-section' ).each( function () {
			var $section = $( this );
			var type     = $section.find( '.wse-local-type-select' ).val();
			applyTypeVisibility( $section, type );
		} );

	} );

} )( jQuery, window.WSELocalAttr || {} );
