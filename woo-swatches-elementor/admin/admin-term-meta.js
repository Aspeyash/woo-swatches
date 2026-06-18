/**
 * WooSwatches for Elementor — Admin Term Meta JS
 *
 * Initialises:
 *   1. wp-color-picker on .wse-color-picker inputs
 *   2. wp.media uploader on .wse-upload-image-btn buttons
 *   3. Remove image handler on .wse-remove-image-btn
 *
 * Only loaded on WC attribute term edit screens (edit-pa_*)
 * via WSE_Term_Meta::admin_enqueue() — Gap 23.
 *
 * @package WooSwatchesElementor
 */
( function ( $, WSETermMeta ) {
	'use strict';

	// ── 1. wp-color-picker ───────────────────────────────────────────────
	// Converts the plain <input type="text"> into a full color picker widget.
	// wp-color-picker is bundled with WordPress — no CDN or npm needed.

	function initColorPicker() {
		var $pickers = $( '.wse-color-picker' );
		if ( ! $pickers.length ) {
			return;
		}
		if ( typeof $.fn.wpColorPicker !== 'function' ) {
			return;
		}
		$pickers.wpColorPicker( {
			// Update the hidden input value whenever the picker changes
			change: function ( event, ui ) {
				$( this ).val( ui.color.toString() );
			},
			// Clear the value when the "Clear" button is clicked
			clear: function () {
				$( this ).val( '' );
			},
		} );
	}

	// ── 2. WordPress media uploader ──────────────────────────────────────
	// One uploader instance per page. Re-opens the same frame on repeat clicks
	// to preserve the user's last browsing position in the media library.

	var mediaUploader = null;

	function openMediaUploader() {
		// Re-use existing frame if already created
		if ( mediaUploader ) {
			mediaUploader.open();
			return;
		}

		mediaUploader = wp.media( {
			title:    WSETermMeta.choose_image,
			button:   { text: WSETermMeta.use_image },
			multiple: false,
			library:  { type: 'image' },
		} );

		mediaUploader.on( 'select', function () {
			var attachment = mediaUploader
				.state()
				.get( 'selection' )
				.first()
				.toJSON();

			// Store attachment ID in the hidden input
			$( '#wse_image' ).val( attachment.id );

			// Show preview image — use thumbnail size if available
			var previewUrl = attachment.sizes && attachment.sizes.thumbnail
				? attachment.sizes.thumbnail.url
				: attachment.url;

			$( '#wse_image_preview' )
				.attr( 'src', previewUrl )
				.removeClass( 'hidden' );

			$( '.wse-remove-image-btn' ).removeClass( 'hidden' );
		} );

		mediaUploader.open();
	}

	// ── 3. Remove image ──────────────────────────────────────────────────

	function removeImage() {
		// Clear hidden input
		$( '#wse_image' ).val( '' );

		// Reset preview to placeholder
		$( '#wse_image_preview' )
			.attr( 'src', WSETermMeta.placeholder )
			.addClass( 'hidden' );

		// Hide remove button
		$( '.wse-remove-image-btn' ).addClass( 'hidden' );
	}

	// ── Event bindings ───────────────────────────────────────────────────

	$( document ).on( 'click', '.wse-upload-image-btn', function ( e ) {
		e.preventDefault();
		openMediaUploader();
	} );

	$( document ).on( 'click', '.wse-remove-image-btn', function ( e ) {
		e.preventDefault();
		removeImage();
	} );

	// ── Init ─────────────────────────────────────────────────────────────

	$( function () {
		initColorPicker();
	} );

} )( jQuery, window.WSETermMeta || {} );
