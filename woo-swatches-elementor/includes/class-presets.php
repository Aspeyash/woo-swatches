<?php
/**
 * ZYMARG widget presets — storage + AJAX (v1.7.0).
 *
 * Lets the merchant save a set of named presets per widget type, apply
 * them to existing widgets, and (optionally) auto-apply one preset to
 * any new widget of that type that gets dropped onto the canvas.
 *
 * Scope (v1.7.0):
 *   • Multi-named presets per widget type.
 *   • Manual Save / Apply / Update / Delete via the new "ZYMARG Presets"
 *     section that lives at the top of every widget's Style tab.
 *   • Optional auto-apply on insert — picked from the same panel and
 *     persisted as the active preset id; admin-presets.js listens for
 *     fresh widget inserts on the Elementor canvas and applies it.
 *
 * Storage (one option pair per widget type, no DB schema change):
 *
 *   wse_presets_{widget_type}        — array of named presets:
 *                                      [ { id, name, settings, created, updated }, … ]
 *   wse_preset_active_{widget_type}  — preset id to auto-apply on insert
 *                                      (empty string = auto-apply off)
 *
 * Supported widget types (limited set so AJAX never persists arbitrary
 * keys — every saved preset is a known plugin widget):
 *   wse-swatches | wse-add-to-cart | wse-price | wse-variation-image-gallery
 *
 * Capability + nonce: every AJAX endpoint requires
 *   - a valid `wse_presets` nonce in $_POST['security']
 *   - the current user has manage_options
 *
 * No frontend output happens here — the widgets continue to render with
 * whatever settings Elementor stores per-instance. Presets only affect
 * the editor experience: applying a preset writes the preset's settings
 * onto the current widget instance via Elementor's model API on the JS
 * side, then the user saves the page as usual.
 *
 * @package WooSwatchesElementor
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

class WSE_Presets {

	/**
	 * Allowlist of widget types that may have presets.
	 *
	 * Anything posted via AJAX is checked against this list before any DB
	 * write — prevents drive-by writers from poking arbitrary option names
	 * by sending unexpected widget_type values.
	 */
	const SUPPORTED_WIDGETS = array(
		'wse-swatches',
		'wse-add-to-cart',
		'wse-price',
		'wse-variation-image-gallery',
	);

	/** Hard cap on presets per widget type — keeps storage bounded. */
	const MAX_PRESETS_PER_WIDGET = 25;

	/** Nonce action used by every AJAX endpoint here + the editor JS. */
	const NONCE_ACTION = 'wse_presets';

	protected static ?WSE_Presets $instance = null;

	public static function instance(): static {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {
		$this->hooks();
	}

	private function __clone() {}

	private function hooks(): void {
		add_action( 'wp_ajax_wse_presets_list',     array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_wse_preset_get',       array( $this, 'ajax_get' ) );
		add_action( 'wp_ajax_wse_preset_save',      array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_wse_preset_delete',    array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_wse_preset_set_active',array( $this, 'ajax_set_active' ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// PHP API — used by AJAX and (in v1.7.x+) by import/export tooling.
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns all saved presets for a widget type, oldest-first by created.
	 *
	 * @param string $widget_type One of SUPPORTED_WIDGETS.
	 * @return array<int, array{id:string,name:string,settings:array,created:int,updated:int}>
	 */
	public static function get_presets( string $widget_type ): array {
		if ( ! self::is_supported( $widget_type ) ) {
			return array();
		}
		$raw = get_option( self::option_name_presets( $widget_type ), array() );
		return is_array( $raw ) ? array_values( $raw ) : array();
	}

	/**
	 * Returns a single preset by id, or null if not found.
	 *
	 * @param string $widget_type
	 * @param string $id
	 * @return array{id:string,name:string,settings:array,created:int,updated:int}|null
	 */
	public static function get_preset( string $widget_type, string $id ): ?array {
		foreach ( self::get_presets( $widget_type ) as $preset ) {
			if ( isset( $preset['id'] ) && $preset['id'] === $id ) {
				return $preset;
			}
		}
		return null;
	}

	/**
	 * Creates or updates a preset.
	 *
	 * If an id is provided AND a preset with that id exists, it's updated.
	 * Otherwise a new preset is created (with a generated id).
	 *
	 * @param string $widget_type
	 * @param string $id          '' to create new; existing id to update.
	 * @param string $name
	 * @param array  $settings    Raw Elementor settings — sanitised here.
	 * @return string|null        The preset id on success, null on failure.
	 */
	public static function save_preset(
		string $widget_type,
		string $id,
		string $name,
		array $settings
	): ?string {

		if ( ! self::is_supported( $widget_type ) ) {
			return null;
		}

		$name = trim( $name );
		if ( '' === $name ) {
			$name = __( 'Untitled preset', 'woo-swatches-elementor' );
		}
		// Cap name length so the dropdown stays sane.
		if ( strlen( $name ) > 80 ) {
			$name = substr( $name, 0, 80 );
		}

		$clean = self::sanitize_settings( $settings );

		$presets = self::get_presets( $widget_type );
		$now     = time();
		$found   = false;

		// Update existing.
		if ( '' !== $id ) {
			foreach ( $presets as $idx => $preset ) {
				if ( isset( $preset['id'] ) && $preset['id'] === $id ) {
					$presets[ $idx ]['name']     = $name;
					$presets[ $idx ]['settings'] = $clean;
					$presets[ $idx ]['updated']  = $now;
					$found = true;
					break;
				}
			}
		}

		// Create new.
		if ( ! $found ) {
			if ( count( $presets ) >= self::MAX_PRESETS_PER_WIDGET ) {
				return null; // hit the per-widget cap
			}
			$id = self::generate_id();
			$presets[] = array(
				'id'       => $id,
				'name'     => $name,
				'settings' => $clean,
				'created'  => $now,
				'updated'  => $now,
			);
		}

		update_option( self::option_name_presets( $widget_type ), array_values( $presets ), false );
		return $id;
	}

	/**
	 * Removes a preset. If it was the active one, also clears the active marker.
	 *
	 * @return bool True when something was removed.
	 */
	public static function delete_preset( string $widget_type, string $id ): bool {
		if ( ! self::is_supported( $widget_type ) || '' === $id ) {
			return false;
		}

		$presets = self::get_presets( $widget_type );
		$kept    = array();
		$removed = false;

		foreach ( $presets as $preset ) {
			if ( isset( $preset['id'] ) && $preset['id'] === $id ) {
				$removed = true;
				continue;
			}
			$kept[] = $preset;
		}

		if ( ! $removed ) {
			return false;
		}

		update_option( self::option_name_presets( $widget_type ), array_values( $kept ), false );

		// If the deleted preset was the active one, clear the marker.
		if ( self::get_active_preset_id( $widget_type ) === $id ) {
			self::set_active_preset_id( $widget_type, '' );
		}

		return true;
	}

	/**
	 * Returns the preset id flagged as "auto-apply on insert" for this
	 * widget type, or '' when auto-apply is off.
	 */
	public static function get_active_preset_id( string $widget_type ): string {
		if ( ! self::is_supported( $widget_type ) ) {
			return '';
		}
		return (string) get_option( self::option_name_active( $widget_type ), '' );
	}

	/**
	 * Sets (or clears) the auto-apply preset for a widget type.
	 *
	 * @param string $widget_type
	 * @param string $id          '' to disable auto-apply; otherwise must be an existing preset id.
	 * @return bool
	 */
	public static function set_active_preset_id( string $widget_type, string $id ): bool {
		if ( ! self::is_supported( $widget_type ) ) {
			return false;
		}
		if ( '' !== $id && ! self::get_preset( $widget_type, $id ) ) {
			return false; // unknown preset id
		}
		update_option( self::option_name_active( $widget_type ), $id, false );
		return true;
	}

	// ─────────────────────────────────────────────────────────────────────
	// AJAX endpoints
	// All four enforce the same gate: nonce + manage_options.
	// ─────────────────────────────────────────────────────────────────────

	public function ajax_list(): void {
		$widget_type = $this->guard_and_get_widget_type();
		wp_send_json_success( array(
			'presets' => self::get_presets( $widget_type ),
			'active'  => self::get_active_preset_id( $widget_type ),
		) );
	}

	public function ajax_get(): void {
		$widget_type = $this->guard_and_get_widget_type();
		$id          = isset( $_POST['preset_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['preset_id'] ) ) : '';
		$preset      = self::get_preset( $widget_type, $id );

		if ( ! $preset ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Preset not found.', 'woo-swatches-elementor' ) ), 404 );
		}
		wp_send_json_success( array( 'preset' => $preset ) );
	}

	public function ajax_save(): void {
		$widget_type = $this->guard_and_get_widget_type();
		$id          = isset( $_POST['preset_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['preset_id'] ) ) : '';
		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';

		// Settings come in as a JSON-encoded string from the editor JS so
		// the payload survives wp_unslash + sanitize without losing nesting.
		$raw = isset( $_POST['settings'] ) ? wp_unslash( (string) $_POST['settings'] ) : '';
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid settings payload.', 'woo-swatches-elementor' ) ), 400 );
		}

		$saved_id = self::save_preset( $widget_type, $id, $name, $decoded );
		if ( null === $saved_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Could not save preset (cap reached or invalid widget).', 'woo-swatches-elementor' ) ), 400 );
		}

		wp_send_json_success( array(
			'preset'  => self::get_preset( $widget_type, $saved_id ),
			'presets' => self::get_presets( $widget_type ),
		) );
	}

	public function ajax_delete(): void {
		$widget_type = $this->guard_and_get_widget_type();
		$id          = isset( $_POST['preset_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['preset_id'] ) ) : '';

		$ok = self::delete_preset( $widget_type, $id );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Preset not found.', 'woo-swatches-elementor' ) ), 404 );
		}

		wp_send_json_success( array(
			'presets' => self::get_presets( $widget_type ),
			'active'  => self::get_active_preset_id( $widget_type ),
		) );
	}

	public function ajax_set_active(): void {
		$widget_type = $this->guard_and_get_widget_type();
		$id          = isset( $_POST['preset_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['preset_id'] ) ) : '';

		$ok = self::set_active_preset_id( $widget_type, $id );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Preset not found.', 'woo-swatches-elementor' ) ), 400 );
		}

		wp_send_json_success( array(
			'active'  => self::get_active_preset_id( $widget_type ),
			'presets' => self::get_presets( $widget_type ),
		) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Internal helpers
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Single point where every AJAX endpoint enforces nonce + capability +
	 * widget allowlist. Any failure terminates the request via
	 * wp_send_json_error so callers can assume success when this returns.
	 *
	 * @return string The validated widget_type.
	 */
	private function guard_and_get_widget_type(): string {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Insufficient permissions.', 'woo-swatches-elementor' ) ),
				403
			);
		}

		$widget_type = isset( $_POST['widget_type'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['widget_type'] ) )
			: '';

		if ( ! self::is_supported( $widget_type ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Unsupported widget type.', 'woo-swatches-elementor' ) ),
				400
			);
		}

		return $widget_type;
	}

	private static function is_supported( string $widget_type ): bool {
		return in_array( $widget_type, self::SUPPORTED_WIDGETS, true );
	}

	private static function option_name_presets( string $widget_type ): string {
		// Replace the hyphenated widget type so option names stay readable
		// and match the conventional WooSwatches `wse_*_*` snake_case style.
		$slug = str_replace( '-', '_', $widget_type );
		return 'wse_presets_' . $slug;
	}

	private static function option_name_active( string $widget_type ): string {
		$slug = str_replace( '-', '_', $widget_type );
		return 'wse_preset_active_' . $slug;
	}

	private static function generate_id(): string {
		// 12-char hex — collision-free for our cap of 25 per widget. wp_generate_uuid4
		// would be overkill and harder to read in admin URLs / logs.
		return substr( str_replace( '.', '', uniqid( 'p', true ) ), -12 );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Editor — register the "ZYMARG Presets" Style-tab section on a widget.
	//
	// Each widget calls this from its register_controls() to mount a
	// uniform RAW_HTML panel at the top of the Style tab. The panel renders
	// as plain markup with stable selectors that admin-presets.js finds
	// and decorates: the dropdown is populated, buttons are wired to
	// AJAX, and the active-preset state is reflected. We deliberately keep
	// this server-side template minimal — no live preset data is rendered
	// here because Elementor caches control HTML across editor sessions;
	// the JS fetches fresh data on demand.
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Mount the Presets section on a widget's Style tab.
	 *
	 * @param \Elementor\Widget_Base $widget The widget calling this from
	 *                                       inside its register_controls().
	 * @return void
	 */
	public static function register_widget_section( \Elementor\Widget_Base $widget ): void {

		// Only mount for our own widget types. Belt-and-braces in case a
		// 3rd-party widget extends one of our classes.
		$widget_type = $widget->get_name();
		if ( ! self::is_supported( $widget_type ) ) {
			return;
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName
		$widget->start_controls_section(
			'wse_section_presets',
			array(
				'label' => esc_html__( 'ZYMARG Presets', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		// phpcs:enable

		// One self-contained markup block. The data-wse-presets-* attrs
		// are the JS contract — admin-presets.js queries by them.
		$intro = esc_html__(
			'Save the current settings as a named preset, apply a saved preset to this widget, or pick one to auto-apply when a new widget of this type is added.',
			'woo-swatches-elementor'
		);

		$lbl_apply        = esc_html__( 'Apply selected preset',                'woo-swatches-elementor' );
		$lbl_save_new     = esc_html__( 'Save current settings as new preset…', 'woo-swatches-elementor' );
		$lbl_update       = esc_html__( 'Update current preset',                'woo-swatches-elementor' );
		$lbl_delete       = esc_html__( 'Delete selected preset',               'woo-swatches-elementor' );
		$lbl_active       = esc_html__( 'Auto-apply on new widget',             'woo-swatches-elementor' );
		$lbl_none         = esc_html__( '— None (off) —',                       'woo-swatches-elementor' );
		$lbl_loading      = esc_html__( 'Loading…',                             'woo-swatches-elementor' );

		ob_start();
		?>
		<div class="wse-presets-panel"
			data-wse-presets-panel
			data-wse-presets-widget-type="<?php echo esc_attr( $widget_type ); ?>">

			<p class="wse-presets-intro" style="font-size:11px;color:#475569;line-height:1.5;margin:0 0 10px;">
				<?php echo esc_html( $intro ); ?>
			</p>

			<label class="wse-presets-label" style="display:block;font-size:11px;font-weight:600;margin-bottom:4px;color:#1e293b;">
				<?php esc_html_e( 'Saved presets', 'woo-swatches-elementor' ); ?>
			</label>
			<select class="wse-presets-select" data-wse-presets-select style="width:100%;margin-bottom:8px;">
				<option value=""><?php echo esc_html( $lbl_loading ); ?></option>
			</select>

			<div class="wse-presets-actions" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
				<button type="button"
					class="wse-presets-btn wse-presets-btn--apply"
					data-wse-presets-action="apply"
					disabled>
					<?php echo esc_html( $lbl_apply ); ?>
				</button>

				<button type="button"
					class="wse-presets-btn wse-presets-btn--save-new"
					data-wse-presets-action="save-new">
					<?php echo esc_html( $lbl_save_new ); ?>
				</button>

				<button type="button"
					class="wse-presets-btn wse-presets-btn--update"
					data-wse-presets-action="update"
					disabled>
					<?php echo esc_html( $lbl_update ); ?>
				</button>

				<button type="button"
					class="wse-presets-btn wse-presets-btn--delete"
					data-wse-presets-action="delete"
					disabled>
					<?php echo esc_html( $lbl_delete ); ?>
				</button>
			</div>

			<label class="wse-presets-label" style="display:block;font-size:11px;font-weight:600;margin-bottom:4px;color:#1e293b;">
				<?php echo esc_html( $lbl_active ); ?>
			</label>
			<select class="wse-presets-active-select" data-wse-presets-active-select style="width:100%;">
				<option value=""><?php echo esc_html( $lbl_none ); ?></option>
			</select>

			<p class="wse-presets-status"
				data-wse-presets-status
				role="status"
				aria-live="polite"
				style="font-size:11px;color:#475569;margin:8px 0 0;min-height:14px;"></p>
		</div>
		<?php
		$markup = (string) ob_get_clean();

		// phpcs:disable WordPress.NamingConventions.ValidVariableName
		$widget->add_control(
			'wse_presets_panel',
			array(
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => $markup,
			)
		);

		$widget->end_controls_section();
		// phpcs:enable
	}

	/**
	 * Recursively cleans an Elementor settings array before persistence.
	 *
	 * Elementor settings are nested arrays of:
	 *   strings (color codes, text), numbers (sizes), bools (toggles),
	 *   small associative arrays (DIMENSIONS controls: top/right/bottom/left/unit),
	 *   group controls (e.g. typography_typography → bool, typography_font_family → string).
	 *
	 * This sanitizer:
	 *   • Drops anything that isn't scalar or array (objects, resources).
	 *   • Caps recursion depth at 6 to bound memory in pathological inputs.
	 *   • Caps string length at 1KB per leaf — generous for legitimate values
	 *     (CSS gradients, font lists) and a hard ceiling against bloat attacks.
	 *   • Caps array length at 200 per level — far above any real Elementor
	 *     control size; protects against caller-supplied bombs.
	 *   • Strips any HTML tags that may have slipped into a string leaf
	 *     (no Elementor control legitimately stores tags in a setting value).
	 *
	 * @param mixed $raw
	 * @param int   $depth
	 * @return array
	 */
	public static function sanitize_settings( $raw, int $depth = 0 ): array {
		if ( ! is_array( $raw ) || $depth > 6 ) {
			return array();
		}
		$out   = array();
		$count = 0;

		foreach ( $raw as $key => $val ) {
			if ( $count++ >= 200 ) {
				break;
			}
			$key = is_string( $key ) ? sanitize_key( $key ) : (int) $key;
			if ( '' === $key && 0 !== $key ) {
				continue;
			}

			if ( is_array( $val ) ) {
				$out[ $key ] = self::sanitize_settings( $val, $depth + 1 );
			} elseif ( is_bool( $val ) ) {
				$out[ $key ] = $val;
			} elseif ( is_int( $val ) || is_float( $val ) ) {
				$out[ $key ] = $val;
			} elseif ( is_string( $val ) ) {
				$s = wp_strip_all_tags( $val );
				if ( strlen( $s ) > 1024 ) {
					$s = substr( $s, 0, 1024 );
				}
				$out[ $key ] = $s;
			} elseif ( is_null( $val ) ) {
				$out[ $key ] = '';
			}
			// Anything else (objects, resources) silently dropped.
		}

		return $out;
	}
}
