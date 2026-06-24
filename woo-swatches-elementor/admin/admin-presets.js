/**
 * ZYMARG widget presets — Elementor editor JS (v1.7.0).
 *
 * Decorates the "ZYMARG Presets" RAW_HTML panel that PHP mounts at the
 * top of every WooSwatches widget's Style tab. Provides three things:
 *
 *   1. Storage  — thin AJAX wrapper around the wp_ajax_wse_preset_*
 *                 endpoints in class-presets.php. Every call carries the
 *                 nonce + widget_type and short-circuits on auth/4xx.
 *   2. Panel    — populates the saved-presets dropdown, wires the Apply /
 *                 Save-as-new / Update / Delete buttons, and updates the
 *                 "Auto-apply on new widget" select. Re-runs whenever the
 *                 user opens a fresh widget editor (because Elementor
 *                 reuses the same DOM but swaps the bound model).
 *   3. AutoApply — listens for fresh widget inserts on the editor canvas
 *                 and applies the active preset for that widget type.
 *                 Detects a "fresh" insert by the brand-new model carrying
 *                 only its plugin-default settings (no user changes).
 *
 * Capability gate is server-side (manage_options on every endpoint). This
 * script is enqueued only inside the Elementor editor by class-assets.php,
 * so non-editor admin pages never load it.
 *
 * @package WooSwatchesElementor
 * @since   1.7.0
 */
( function ( $, WSEPresets ) {
	'use strict';

	// WSEPresets is localized by enqueue_editor() in class-assets.php and
	// carries: { ajax_url, nonce, supported, i18n }.
	if ( ! WSEPresets || ! WSEPresets.ajax_url ) {
		return;
	}

	var SUPPORTED  = WSEPresets.supported || [];
	var I18N       = WSEPresets.i18n      || {};

	// In-memory cache: widget_type → { presets:[], active:'' }. Avoids
	// re-fetching the same list every time the user re-opens a panel for
	// the same widget type within an editor session. Mutated by Storage
	// after every successful save/delete/set-active so the cache stays
	// authoritative for the rest of the session.
	var cache = {};

	// ─────────────────────────────────────────────────────────────────────
	// Storage — AJAX wrapper
	// ─────────────────────────────────────────────────────────────────────

	var Storage = {
		_post: function ( action, payload ) {
			var data = $.extend( {
				action:      action,
				security:    WSEPresets.nonce,
				widget_type: payload.widget_type
			}, payload || {} );
			return $.post( WSEPresets.ajax_url, data ).then(
				function ( resp ) {
					if ( ! resp || ! resp.success ) {
						var msg = ( resp && resp.data && resp.data.message ) || I18N.generic_error;
						return $.Deferred().reject( new Error( msg ) ).promise();
					}
					return resp.data;
				},
				function () {
					return $.Deferred().reject( new Error( I18N.network_error ) ).promise();
				}
			);
		},

		list: function ( widget_type ) {
			return Storage._post( 'wse_presets_list', { widget_type: widget_type } )
				.then( function ( data ) {
					cache[ widget_type ] = {
						presets: data.presets || [],
						active:  data.active  || ''
					};
					return cache[ widget_type ];
				} );
		},

		save: function ( widget_type, preset_id, name, settings ) {
			return Storage._post( 'wse_preset_save', {
				widget_type: widget_type,
				preset_id:   preset_id || '',
				name:        name      || '',
				settings:    JSON.stringify( settings || {} )
			} ).then( function ( data ) {
				cache[ widget_type ] = {
					presets: data.presets || [],
					active:  ( cache[ widget_type ] && cache[ widget_type ].active ) || ''
				};
				return data;
			} );
		},

		remove: function ( widget_type, preset_id ) {
			return Storage._post( 'wse_preset_delete', {
				widget_type: widget_type,
				preset_id:   preset_id
			} ).then( function ( data ) {
				cache[ widget_type ] = {
					presets: data.presets || [],
					active:  data.active  || ''
				};
				return data;
			} );
		},

		setActive: function ( widget_type, preset_id ) {
			return Storage._post( 'wse_preset_set_active', {
				widget_type: widget_type,
				preset_id:   preset_id
			} ).then( function ( data ) {
				cache[ widget_type ] = {
					presets: data.presets || [],
					active:  data.active  || ''
				};
				return data;
			} );
		},

		getCached: function ( widget_type ) {
			return cache[ widget_type ] || null;
		}
	};

	// ─────────────────────────────────────────────────────────────────────
	// Panel — decorate the RAW_HTML panel inside an open widget editor
	// ─────────────────────────────────────────────────────────────────────

	var Panel = {

		// Find the most recently-opened panel that matches a widget type,
		// scoped to the Elementor editor frame's panel area. Returns a
		// jQuery wrapper or null. The Style tab DOM is rebuilt every time
		// the user opens a different widget, so we can't cache the node.
		findPanel: function () {
			// Elementor's editor panel renders inside #elementor-panel.
			// Our RAW_HTML control mounts `[data-wse-presets-panel]` once
			// per opened widget editor.
			var $panel = $( '[data-wse-presets-panel]', document );
			if ( ! $panel.length ) {
				return null;
			}
			// If multiple widgets are simultaneously rendered (rare, e.g.
			// nested editor previews), prefer the visible one.
			var $visible = $panel.filter( ':visible' );
			return $visible.length ? $visible.first() : $panel.first();
		},

		// Populate / refresh both selects from a {presets, active} payload.
		render: function ( $panel, payload ) {
			var $select       = $panel.find( '[data-wse-presets-select]' );
			var $activeSelect = $panel.find( '[data-wse-presets-active-select]' );
			var presets       = ( payload && payload.presets ) || [];
			var activeId      = ( payload && payload.active )  || '';

			// Saved-presets select.
			$select.empty();
			if ( presets.length ) {
				$select.append( $( '<option/>' ).val( '' ).text( I18N.choose_preset || '— Select a preset —' ) );
				presets.forEach( function ( p ) {
					$select.append( $( '<option/>' ).val( p.id ).text( p.name || p.id ) );
				} );
			} else {
				$select.append( $( '<option/>' ).val( '' ).text( I18N.no_presets || 'No saved presets yet' ) );
			}

			// Auto-apply select.
			$activeSelect.empty();
			$activeSelect.append( $( '<option/>' ).val( '' ).text( I18N.auto_apply_off || '— None (off) —' ) );
			presets.forEach( function ( p ) {
				var $opt = $( '<option/>' ).val( p.id ).text( p.name || p.id );
				if ( p.id === activeId ) {
					$opt.prop( 'selected', true );
				}
				$activeSelect.append( $opt );
			} );

			Panel.updateButtons( $panel );
		},

		// Toggle button enabled-states based on whether a preset is selected.
		updateButtons: function ( $panel ) {
			var hasSel = !! $panel.find( '[data-wse-presets-select]' ).val();
			$panel.find( '[data-wse-presets-action="apply"]'  ).prop( 'disabled', ! hasSel );
			$panel.find( '[data-wse-presets-action="update"]' ).prop( 'disabled', ! hasSel );
			$panel.find( '[data-wse-presets-action="delete"]' ).prop( 'disabled', ! hasSel );
		},

		setStatus: function ( $panel, message, isError ) {
			$panel.find( '[data-wse-presets-status]' )
				.text( message || '' )
				.css( 'color', isError ? '#b91c1c' : '#475569' );
		},

		// Returns the model of the widget whose editor is currently open.
		// Falls back to null when the editor isn't open (e.g. the panel is
		// being rendered outside the widget edit context).
		currentModel: function () {
			try {
				var current = window.elementor && window.elementor.getPanelView
					? window.elementor.getPanelView().getCurrentPageView()
					: null;
				return ( current && current.model ) ? current.model : null;
			} catch ( e ) {
				return null;
			}
		},

		// Return a clean copy of the current widget's settings — Elementor
		// stores them on `model.attributes.settings.attributes` as a model.
		// We grab toJSON() so we get plain values (responsive variants
		// included as their own keys e.g. `padding_tablet`).
		currentSettings: function () {
			var model = Panel.currentModel();
			if ( ! model || ! model.get ) { return null; }
			var settingsModel = model.get( 'settings' );
			if ( ! settingsModel || ! settingsModel.toJSON ) { return null; }
			var raw = settingsModel.toJSON() || {};
			return Panel.stripVolatile( raw );
		},

		// Drop keys that should never be copied across widget instances:
		// the per-instance product_id, dynamic-tag bindings, _id-like keys,
		// and Elementor's internal `__globals__` / `__dynamic__`.
		stripVolatile: function ( raw ) {
			var BLOCK = {
				'product_id':   true,
				'_id':          true,
				'_element_id':  true,
				'__globals__':  true,
				'__dynamic__':  true
			};
			var out = {};
			Object.keys( raw ).forEach( function ( key ) {
				if ( BLOCK[ key ] ) { return; }
				out[ key ] = raw[ key ];
			} );
			return out;
		},

		// Apply a preset's settings onto the currently-open widget.
		// Iterates keys and calls model.setSetting() so Elementor's preview
		// re-renders cleanly. Wrapped in a `silent` block per key would be
		// faster but a per-key set lets the preview animate naturally.
		applyToCurrent: function ( settings ) {
			var model = Panel.currentModel();
			if ( ! model ) { return false; }
			var settingsModel = model.get( 'settings' );
			if ( ! settingsModel ) { return false; }

			Object.keys( settings || {} ).forEach( function ( key ) {
				try {
					settingsModel.setExternalChange( key, settings[ key ] );
				} catch ( e ) {
					// Older Elementors lack setExternalChange — fall back.
					try { settingsModel.set( key, settings[ key ] ); } catch ( _ ) {}
				}
			} );

			// Trigger the standard render-trigger event Elementor uses
			// after batched changes so the preview iframe repaints.
			model.renderRemoteServer && model.renderRemoteServer();
			return true;
		},

		// Apply a preset onto a specific (just-inserted) model. Used by
		// AutoApply where the model isn't necessarily the open panel's
		// current page view.
		applyToModel: function ( model, settings ) {
			if ( ! model || ! model.get ) { return false; }
			var settingsModel = model.get( 'settings' );
			if ( ! settingsModel ) { return false; }
			Object.keys( settings || {} ).forEach( function ( key ) {
				try {
					settingsModel.setExternalChange( key, settings[ key ] );
				} catch ( e ) {
					try { settingsModel.set( key, settings[ key ] ); } catch ( _ ) {}
				}
			} );
			model.renderRemoteServer && model.renderRemoteServer();
			return true;
		},

		// Wire all panel buttons + selects. Idempotent: uses namespaced
		// .off().on() so calling it twice on the same DOM node is safe
		// (Elementor sometimes re-renders the panel without destroying it).
		bind: function ( $panel ) {
			var widgetType = $panel.attr( 'data-wse-presets-widget-type' );
			if ( ! widgetType ) { return; }

			$panel
				.off( 'change.wsePresets', '[data-wse-presets-select]' )
				.on(  'change.wsePresets', '[data-wse-presets-select]', function () {
					Panel.updateButtons( $panel );
				} );

			$panel
				.off( 'click.wsePresets', '[data-wse-presets-action]' )
				.on(  'click.wsePresets', '[data-wse-presets-action]', function ( e ) {
					e.preventDefault();
					var action = $( this ).attr( 'data-wse-presets-action' );
					Panel.handleAction( $panel, widgetType, action );
				} );

			$panel
				.off( 'change.wsePresets', '[data-wse-presets-active-select]' )
				.on(  'change.wsePresets', '[data-wse-presets-active-select]', function () {
					var newActive = $( this ).val() || '';
					Panel.setStatus( $panel, I18N.saving || 'Saving…' );
					Storage.setActive( widgetType, newActive ).then(
						function () { Panel.setStatus( $panel, I18N.saved || 'Saved.' ); },
						function ( err ) { Panel.setStatus( $panel, err.message, true ); }
					);
				} );
		},

		handleAction: function ( $panel, widgetType, action ) {
			var $sel    = $panel.find( '[data-wse-presets-select]' );
			var presetId = $sel.val() || '';

			if ( action === 'apply' ) {
				var cached = Storage.getCached( widgetType );
				var preset = cached && ( cached.presets || [] ).find( function ( p ) { return p.id === presetId; } );
				if ( ! preset ) {
					Panel.setStatus( $panel, I18N.preset_not_found || 'Preset not found.', true );
					return;
				}
				if ( ! Panel.applyToCurrent( preset.settings || {} ) ) {
					Panel.setStatus( $panel, I18N.apply_failed || 'Could not apply preset.', true );
					return;
				}
				Panel.setStatus( $panel, ( I18N.applied || 'Applied: ' ) + ( preset.name || '' ) );
				return;
			}

			if ( action === 'save-new' ) {
				var defaultName = ( I18N.new_preset_default || 'My preset' ) +
					' ' + new Date().toLocaleString();
				var name = window.prompt( I18N.prompt_name || 'Preset name:', defaultName );
				if ( ! name ) { return; }
				var settings = Panel.currentSettings();
				if ( ! settings ) {
					Panel.setStatus( $panel, I18N.read_failed || 'Could not read current settings.', true );
					return;
				}
				Panel.setStatus( $panel, I18N.saving || 'Saving…' );
				Storage.save( widgetType, '', name, settings ).then(
					function ( data ) {
						Panel.render( $panel, {
							presets: data.presets || [],
							active:  ( cache[ widgetType ] && cache[ widgetType ].active ) || ''
						} );
						$sel.val( ( data.preset && data.preset.id ) || '' ).trigger( 'change' );
						Panel.setStatus( $panel, I18N.saved || 'Saved.' );
					},
					function ( err ) { Panel.setStatus( $panel, err.message, true ); }
				);
				return;
			}

			if ( action === 'update' ) {
				if ( ! presetId ) { return; }
				if ( ! window.confirm( I18N.confirm_update || 'Overwrite this preset with the current widget settings?' ) ) {
					return;
				}
				var cached2 = Storage.getCached( widgetType );
				var existing = cached2 && ( cached2.presets || [] ).find( function ( p ) { return p.id === presetId; } );
				var name2    = ( existing && existing.name ) || '';
				var settings2 = Panel.currentSettings();
				if ( ! settings2 ) {
					Panel.setStatus( $panel, I18N.read_failed || 'Could not read current settings.', true );
					return;
				}
				Panel.setStatus( $panel, I18N.saving || 'Saving…' );
				Storage.save( widgetType, presetId, name2, settings2 ).then(
					function ( data ) {
						Panel.render( $panel, {
							presets: data.presets || [],
							active:  ( cache[ widgetType ] && cache[ widgetType ].active ) || ''
						} );
						$sel.val( presetId ).trigger( 'change' );
						Panel.setStatus( $panel, I18N.saved || 'Saved.' );
					},
					function ( err ) { Panel.setStatus( $panel, err.message, true ); }
				);
				return;
			}

			if ( action === 'delete' ) {
				if ( ! presetId ) { return; }
				if ( ! window.confirm( I18N.confirm_delete || 'Delete this preset?' ) ) {
					return;
				}
				Panel.setStatus( $panel, I18N.deleting || 'Deleting…' );
				Storage.remove( widgetType, presetId ).then(
					function ( data ) {
						Panel.render( $panel, {
							presets: data.presets || [],
							active:  data.active  || ''
						} );
						Panel.setStatus( $panel, I18N.deleted || 'Deleted.' );
					},
					function ( err ) { Panel.setStatus( $panel, err.message, true ); }
				);
				return;
			}
		},

		// Refresh the panel's data from the server and bind events. Called
		// every time the user opens a widget editor (handled by the editor
		// hook below).
		refresh: function () {
			var $panel = Panel.findPanel();
			if ( ! $panel ) { return; }

			var widgetType = $panel.attr( 'data-wse-presets-widget-type' );
			if ( ! widgetType ) { return; }

			Panel.bind( $panel );
			Panel.setStatus( $panel, I18N.loading || 'Loading…' );

			Storage.list( widgetType ).then(
				function ( data ) {
					Panel.render( $panel, data );
					Panel.setStatus( $panel, '' );
				},
				function ( err ) { Panel.setStatus( $panel, err.message, true ); }
			);
		}
	};

	// ─────────────────────────────────────────────────────────────────────
	// AutoApply — listen for fresh widget inserts on the canvas
	//
	// Elementor 3.x exposes `$e.commands` with a documented event system
	// that fires `document/elements/create:after` immediately after any
	// element (widget, section, column) is created in the editor. This is
	// the cleanest signal of a "fresh insert" and won't fire on duplicate /
	// move / load-from-template operations (those are different commands:
	// document/elements/copy, document/elements/move, etc.).
	//
	// We register two listeners:
	//   1. PRIMARY  — `$e.commands.on( 'run:after' )` filtered to the
	//                 document/elements/create command, which is the modern
	//                 (Elementor 3+) path and what mainstream extensions use.
	//   2. FALLBACK — `elementor.on( 'preview:loaded' )` + Backbone collection
	//                 `add` event listener for older Elementor builds where
	//                 $e.commands isn't yet on the page.
	//
	// Both paths funnel into maybeApply(), which is idempotent (seen-id
	// dedupe) so double-firing across the two paths is harmless.
	// ─────────────────────────────────────────────────────────────────────

	var AutoApply = {

		// Track widget _ids we've already processed so duplicate "create"
		// events (Elementor occasionally bubbles the same model through
		// several listeners during nested element creation) only run apply
		// once per id.
		seen: {},

		init: function () {
			AutoApply.bindCommandListener();   // primary
			AutoApply.bindLegacyListener();    // fallback
		},

		// Modern Elementor 3.x path — listens on the global $e command bus.
		bindCommandListener: function () {
			if ( ! window.$e || ! window.$e.commands || ! window.$e.commands.on ) {
				return; // older Elementor, fallback will handle it
			}
			try {
				window.$e.commands.on( 'run:after', function ( command, args ) {
					if ( 'document/elements/create' !== command ) {
						return;
					}
					// args.container is the newly-created element's Container.
					var container = args && args.container;
					var model = container && container.model;
					AutoApply.maybeApply( model );
				} );
			} catch ( e ) { /* noop */ }
		},

		// Legacy Backbone path — listens on the document elements collection
		// for Elementor builds without the $e commands bus.
		bindLegacyListener: function () {
			if ( ! window.elementor || ! window.elementor.on ) { return; }
			window.elementor.on( 'preview:loaded', function () {
				try {
					var doc = window.elementor.documents && window.elementor.documents.getCurrent
						? window.elementor.documents.getCurrent()
						: null;
					var children = doc && doc.container && doc.container.children;
					if ( ! children || ! children.on ) { return; }

					children.on( 'add', function ( newModel ) {
						AutoApply.maybeApply( newModel );
					} );
					// Also bind to nested collections that get added later.
					children.each && children.each( function ( child ) {
						AutoApply.recurseBind( child );
					} );
				} catch ( e ) { /* noop */ }
			} );
		},

		recurseBind: function ( model ) {
			if ( ! model || ! model.get ) { return; }
			if ( model.get( 'elType' ) === 'widget' ) { return; }   // leaf
			var children = model.get( 'elements' );
			if ( children && children.on ) {
				children.on( 'add', function ( newModel ) {
					AutoApply.maybeApply( newModel );
					AutoApply.recurseBind( newModel );
				} );
				children.each && children.each( function ( c ) { AutoApply.recurseBind( c ); } );
			}
		},

		// Decide whether `model` is one of our widgets and, if so, apply
		// the active preset for its type. Idempotent — safe to call from
		// multiple listeners for the same model.
		maybeApply: function ( model ) {
			if ( ! model || ! model.get ) { return; }
			if ( model.get( 'elType' ) !== 'widget' ) { return; }
			var widgetType = model.get( 'widgetType' );
			if ( SUPPORTED.indexOf( widgetType ) === -1 ) { return; }

			var id = model.get( 'id' ) || model.cid || '';
			if ( ! id || AutoApply.seen[ id ] ) { return; }
			AutoApply.seen[ id ] = true;

			var apply = function ( payload ) {
				var activeId = payload && payload.active;
				if ( ! activeId ) { return; }   // auto-apply off
				var preset = ( payload.presets || [] ).find( function ( p ) {
					return p.id === activeId;
				} );
				if ( ! preset ) { return; }
				Panel.applyToModel( model, preset.settings || {} );
			};

			var cached = Storage.getCached( widgetType );
			if ( cached ) {
				apply( cached );
			} else {
				Storage.list( widgetType ).then( apply, function () { /* silently ignore */ } );
			}
		}
	};

	// ─────────────────────────────────────────────────────────────────────
	// Bootstrap — refresh the panel when an editor opens; init AutoApply once.
	// ─────────────────────────────────────────────────────────────────────

	function bootstrap() {
		if ( ! window.elementor ) { return; }

		AutoApply.init();

		// Re-render the panel each time a widget editor is opened.
		// `editor:open` fires on the editor channel for any element type;
		// we filter inside refresh() by checking for our [data-wse-presets-panel]
		// node in the current DOM.
		try {
			window.elementor.channels.editor.on( 'editor:open', function () {
				// Tiny defer so Elementor finishes rendering the panel DOM.
				setTimeout( Panel.refresh, 50 );
			} );
		} catch ( e ) { /* fallback below */ }

		// Also listen to the universal panel/editor change as a safety net
		// for older Elementor versions.
		$( window ).on( 'elementor/init', function () {
			setTimeout( Panel.refresh, 200 );
		} );
	}

	$( window ).on( 'elementor:init', bootstrap );
	if ( window.elementor ) { bootstrap(); }

} )( jQuery, window.WSEPresets || null );
