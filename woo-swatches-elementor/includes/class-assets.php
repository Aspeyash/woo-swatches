<?php
/**
 * Frontend and admin asset registration + conditional enqueuing.
 *
 * Design principles:
 *   - Register ALL handles early (priority 5) so Elementor's
 *     get_script_depends() / get_style_depends() can resolve them
 *     before the page renders (Gap 21).
 *   - Enqueue frontend assets at priority 10 only on pages where
 *     swatches are actually needed (Gap 19).
 *   - Respect add_theme_support('woo-swatches-elementor') overrides (Gap 29).
 *   - Apply RTL stylesheet swap via wp_style_add_data (Gap 18).
 *   - Pass nonce + i18n to JS via wp_localize_script (Gap 13).
 *   - Use WSE_SUFFIX (.min / '') for production/debug toggle (Gap 49).
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Assets {

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected static ?WSE_Assets $instance = null;

	// ─────────────────────────────────────────────────────────────────────
	// Singleton
	// ─────────────────────────────────────────────────────────────────────

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

	// ─────────────────────────────────────────────────────────────────────
	// Hooks
	// ─────────────────────────────────────────────────────────────────────

	private function hooks(): void {

		// Gap 21 — Register EARLY so Elementor widget get_script_depends()
		// can resolve handles before the page is rendered.
		// Priority 5 = before WooCommerce's own scripts at priority 10.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_all' ), 5 );

		// Conditional enqueue — priority 10 (after registration)
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ), 10 );

		// Admin: pass cache-flush nonce to any admin page that has our UI
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );

		// v1.7.0 — Enqueue the presets editor JS only inside the Elementor editor.
		// `elementor/editor/before_enqueue_scripts` fires only when Elementor
		// is loading its own editor UI, so this hook is the cleanest gate
		// (no need to inspect $_GET / current_screen ourselves).
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_editor' ) );

		// v1.2.1 (F5) — Inject responsive per-type swatch width CSS in <head>
		// driven by the global WSE_Settings → Swatch Sizes options. Runs at
		// priority 100 so theme/plugin styles register first; our inline
		// rules then take precedence on specificity-equal targets.
		add_action( 'wp_head', array( $this, 'print_swatch_sizes_inline_css' ), 100 );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Registration — runs on every frontend page (priority 5)
	// Elementor widgets reference these handles in get_script_depends()
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Registers all plugin script and style handles.
	 * Registration is cheap and side-effect-free — nothing is loaded until
	 * wp_enqueue_script/style or get_script/style_depends() fires.
	 */
	public function register_all(): void {
		$this->register_scripts();
		$this->register_styles();
	}

	/**
	 * Registers all plugin JS handles.
	 * Gap 49 — WSE_SUFFIX switches between '' (debug) and '.min' (production).
	 */
	private function register_scripts(): void {

		$s = WSE_SUFFIX;
		$v = WSE_VERSION;
		$u = WSE_URL . 'assets/js/';

		// Core swatch interaction engine (Phase 7)
		// Depends on jQuery and WC's variation JS being loaded
		wp_register_script(
			'wse-swatches',
			$u . 'swatches' . $s . '.js',
			array( 'jquery', 'wc-add-to-cart-variation' ),
			$v,
			true // load in footer
		);

		// Attribute dependency/availability sync (Phase 7 — Gap 50)
		// Depends on wse-swatches for the custom events it fires
		wp_register_script(
			'wse-form-field-dependency',
			$u . 'form-field-dependency' . $s . '.js',
			array( 'jquery', 'wse-swatches' ),
			$v,
			true
		);

		// Add to cart AJAX handler (Phase 12)
		// Registered here so Elementor Widget 2 can declare it in get_script_depends()
		wp_register_script(
			'wse-add-to-cart',
			$u . 'add-to-cart' . $s . '.js',
			array( 'jquery', 'wc-add-to-cart-variation', 'wse-swatches' ),
			$v,
			true
		);

		// v1.2.0 — Widget 3 (ZYMARG Price) variation sync.
		// Listens to the canonical form's `found_variation` / `reset_data`
		// events fired by wc-add-to-cart-variation.js and re-renders any
		// .zymarg-price[data-form-id] on the page. Loaded only when Widget 3
		// is in the layout (declared via get_script_depends()).
		wp_register_script(
			'wse-price',
			$u . 'price' . $s . '.js',
			array( 'jquery', 'wc-add-to-cart-variation' ),
			$v,
			true
		);

		// v1.3.0 — Widget 4 (ZYMARG Variation Image Gallery) variation sync,
		// thumbnail clicks, hover-zoom lens, lightbox, and mobile swipe
		// carousel. Loaded only when Widget 4 is in the layout.
		wp_register_script(
			'wse-gallery',
			$u . 'gallery' . $s . '.js',
			array( 'jquery', 'wc-add-to-cart-variation' ),
			$v,
			true
		);
	}

	/**
	 * Registers all plugin CSS handles + RTL data.
	 * Gap 18 — wp_style_add_data('rtl','replace') tells WordPress to swap
	 *          in swatches-rtl.css automatically for RTL locales.
	 */
	private function register_styles(): void {

		$v = WSE_VERSION;
		$u = WSE_URL . 'assets/css/';

		// Main swatch stylesheet (Phase 13)
		// Gap 3 + Gap 51 — visual rules are gated by body classes,
		// so the file always loads but CSS only applies when body has
		// .wse-stylesheet-enabled (Emran Ahmed's pattern exactly).
		wp_register_style(
			'wse-swatches',
			$u . 'swatches.css',
			array(),
			$v
		);

		// Gap 18 — RTL support: WordPress loads swatches-rtl.css
		// automatically when is_rtl() is true
		wp_style_add_data( 'wse-swatches', 'rtl', 'replace' );

		// Tooltip CSS — separate file loaded conditionally (Phase 13)
		wp_register_style(
			'wse-swatches-tooltip',
			$u . 'swatches-tooltip.css',
			array( 'wse-swatches' ), // depends on main swatches CSS
			$v
		);

		// Add to cart stylesheet (Phase 13)
		wp_register_style(
			'wse-add-to-cart',
			$u . 'add-to-cart.css',
			array(),
			$v
		);

		// v1.2.0 — Widget 3 (ZYMARG Price) stylesheet.
		// Loaded only when Widget 3 is in the page layout (declared via
		// get_style_depends() on the widget). Built on the same body-class
		// gating pattern as wse-swatches: visual rules apply only when
		// .wse-stylesheet-enabled is on the body.
		wp_register_style(
			'wse-price',
			$u . 'price.css',
			array(),
			$v
		);

		// v1.3.0 — Widget 4 (ZYMARG Variation Image Gallery) stylesheet.
		// Two separate handles: gallery.css for the always-loaded base
		// (layout, thumbs, main image) and gallery-lightbox.css for the
		// lightbox UI. The lightbox sheet is registered separately so the
		// base CSS stays light on pages that don't use the lightbox feature.
		wp_register_style(
			'wse-gallery',
			$u . 'gallery.css',
			array(),
			$v
		);
		wp_register_style(
			'wse-gallery-lightbox',
			$u . 'gallery-lightbox.css',
			array( 'wse-gallery' ),
			$v
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Frontend enqueue — conditional (Gap 19)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Enqueues frontend assets only on pages where swatches are needed.
	 *
	 * Pages included:
	 *   is_product()          — single product page
	 *   is_shop()             — WooCommerce shop page
	 *   is_product_category() — WC category archive
	 *   is_product_tag()      — WC tag archive
	 *   is_product_taxonomy() — any custom WC product taxonomy
	 *   Elementor preview     — editor preview iframe
	 *
	 * Pages excluded (no swatches needed):
	 *   Cart, Checkout, My Account, static pages without WC widgets
	 */
	public function enqueue_frontend(): void {

		// Gap 19 — bail if swatches are not needed on this page
		if ( ! $this->should_load() ) {
			return;
		}

		// Gap 29 — respect add_theme_support() overrides from the active theme
		// Theme developers can suppress stylesheet or tooltip via:
		//   add_theme_support( 'woo-swatches-elementor', [
		//       'enable_stylesheet' => false,
		//       'enable_tooltip'    => false,
		//   ] );
		$theme_support = get_theme_support( 'woo-swatches-elementor' );
		$theme_opts    = wp_parse_args(
			is_array( $theme_support ) ? (array) $theme_support[0] : array(),
			array( 'enable_stylesheet' => true, 'enable_tooltip' => true )
		);

		// ── JS ────────────────────────────────────────────────────────────
		wp_enqueue_script( 'wse-swatches' );
		wp_enqueue_script( 'wse-form-field-dependency' );

		// wse-add-to-cart is declared by Widget 2 via get_script_depends().
		// We also enqueue it here so it loads on non-Elementor product pages
		// (e.g. standard WooCommerce single product template).
		wp_enqueue_script( 'wse-add-to-cart' );

		// Gap 13 — Pass PHP data to all frontend JS via WSEParams.
		// Attached to wse-swatches (first script in the dependency chain).
		// v1.1.0 — runs through wse_frontend_params so other classes
		// (plugin core, future extensions) can append payload data without
		// re-localizing on a different handle.
		$params = array(
			'ajax_url'    => esc_url( admin_url( 'admin-ajax.php' ) ),
			'wc_ajax_url' => esc_url( WC_AJAX::get_endpoint( '%%endpoint%%' ) ),
			'nonce'       => wp_create_nonce( 'wse_nonce' ),
			'i18n'        => array(
				'added'      => esc_html__( 'Added to cart',         'woo-swatches-elementor' ),
				'adding'     => esc_html__( 'Adding…',                'woo-swatches-elementor' ),
				'error'      => esc_html__( 'Something went wrong.',  'woo-swatches-elementor' ),
				'select_opt' => esc_html__( 'Please select an option before adding this product to your cart.', 'woo-swatches-elementor' ),
			),
			'cart_url'    => esc_url( wc_get_cart_url() ),
		);
		$params = (array) apply_filters( 'wse_frontend_params', $params );
		wp_localize_script( 'wse-swatches', 'WSEParams', $params );

		// ── CSS ───────────────────────────────────────────────────────────

		// Main swatch CSS — always enqueue when stylesheet is enabled.
		// Gap 3 + Gap 51: body class .wse-stylesheet-enabled / disabled
		// gates all visual rules inside the file — Emran Ahmed's exact approach.
		$stylesheet_enabled = 'yes' === get_option( 'wse_stylesheet', 'yes' );
		if ( $stylesheet_enabled && $theme_opts['enable_stylesheet'] ) {
			wp_enqueue_style( 'wse-swatches' );
			// Gap 18 — RTL data already set in register_styles() via wp_style_add_data
		}

		// Tooltip CSS — separate, conditional file (Phase 13)
		$tooltip_enabled = 'yes' === get_option( 'wse_tooltip', 'yes' );
		if ( $tooltip_enabled && $theme_opts['enable_tooltip'] && $stylesheet_enabled ) {
			wp_enqueue_style( 'wse-swatches-tooltip' );
		}

		// Add to cart CSS (declared by Widget 2 via get_style_depends() too)
		wp_enqueue_style( 'wse-add-to-cart' );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Conditional load check (Gap 19)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns true if frontend swatch assets should be loaded.
	 *
	 * @return bool
	 */
	private function should_load(): bool {

		// Always load in Elementor editor preview iframe
		if ( $this->is_elementor_preview() ) {
			return true;
		}

		// WooCommerce product pages and shop/archive pages
		return is_product()
			|| is_shop()
			|| is_product_category()
			|| is_product_tag()
			|| is_product_taxonomy();
	}

	/**
	 * Returns true when the current request is an Elementor editor preview.
	 *
	 * @return bool
	 */
	private function is_elementor_preview(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		// Elementor Pro: preview iframe
		if ( isset( \Elementor\Plugin::$instance->preview )
			 && method_exists( \Elementor\Plugin::$instance->preview, 'is_preview_mode' )
			 && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return true;
		}
		return false;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Admin asset enqueue
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Enqueues admin-only assets and passes the cache-flush nonce
	 * to any admin page that contains the WooSwatches settings UI.
	 *
	 * v1.1.0 (B18) — Localization moved off the global 'jquery' handle
	 * onto a dedicated 'wse-admin' script so other plugins / themes
	 * never collide with the WSEAdmin object on jquery's namespace.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin( string $hook ): void {

		// Only on our own plugin admin pages
		if ( strpos( $hook, 'woo-swatches' ) === false
			 && strpos( $hook, 'wse-' ) === false
			 && ! $this->is_wse_settings_screen() ) {
			return;
		}

		// Register a no-op handle so wp_localize_script has a stable target.
		// Loading an empty inline script is the simplest way to provide a
		// per-page handle without shipping a real .js file.
		if ( ! wp_script_is( 'wse-admin', 'registered' ) ) {
			wp_register_script(
				'wse-admin',
				'',
				array( 'jquery' ),
				WSE_VERSION,
				true
			);
		}
		wp_enqueue_script( 'wse-admin' );

		wp_localize_script(
			'wse-admin',
			'WSEAdmin',
			array(
				'ajax_url'    => esc_url( admin_url( 'admin-ajax.php' ) ),
				'flush_nonce' => wp_create_nonce( 'wse_flush_cache' ),
				'i18n'        => array(
					'flushing'    => esc_html__( 'Clearing cache…',          'woo-swatches-elementor' ),
					'flushed'     => esc_html__( 'Cache cleared.',           'woo-swatches-elementor' ),
					'error'       => esc_html__( 'Error clearing cache.',    'woo-swatches-elementor' ),
					'regen_start' => esc_html__( 'Generating…',              'woo-swatches-elementor' ),
					'regen_done'  => esc_html__( 'Done!',                    'woo-swatches-elementor' ),
					'regen_error' => esc_html__( 'Error during generation.', 'woo-swatches-elementor' ),
				),
			)
		);
	}

	/**
	 * v1.7.0 — Enqueues the ZYMARG presets JS inside the Elementor editor.
	 *
	 * Hooked from `elementor/editor/before_enqueue_scripts`, which only
	 * fires inside the editor view (NOT on the editor preview iframe and
	 * NOT on regular admin pages), so we don't need extra screen guards.
	 *
	 * The script consumes `WSEPresets` (localized below): nonce + AJAX URL
	 * + the supported widget-types list + i18n strings. It also requires
	 * jquery (Elementor's editor depends on it anyway).
	 */
	public function enqueue_editor(): void {

		$handle = 'wse-admin-presets';
		$src    = WSE_URL . 'admin/admin-presets.js';

		wp_register_script(
			$handle,
			$src,
			array( 'jquery' ),
			WSE_VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'WSEPresets',
			array(
				'ajax_url'  => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'     => wp_create_nonce( WSE_Presets::NONCE_ACTION ),
				'supported' => WSE_Presets::SUPPORTED_WIDGETS,
				'i18n'      => array(
					'choose_preset'      => esc_html__( '— Select a preset —',                                    'woo-swatches-elementor' ),
					'no_presets'         => esc_html__( 'No saved presets yet',                                   'woo-swatches-elementor' ),
					'auto_apply_off'     => esc_html__( '— None (off) —',                                         'woo-swatches-elementor' ),
					'loading'            => esc_html__( 'Loading…',                                               'woo-swatches-elementor' ),
					'saving'             => esc_html__( 'Saving…',                                                'woo-swatches-elementor' ),
					'saved'              => esc_html__( 'Saved.',                                                 'woo-swatches-elementor' ),
					'deleting'           => esc_html__( 'Deleting…',                                              'woo-swatches-elementor' ),
					'deleted'            => esc_html__( 'Deleted.',                                               'woo-swatches-elementor' ),
					'applied'            => esc_html__( 'Applied: ',                                              'woo-swatches-elementor' ),
					'apply_failed'       => esc_html__( 'Could not apply preset.',                                'woo-swatches-elementor' ),
					'read_failed'        => esc_html__( 'Could not read current settings.',                       'woo-swatches-elementor' ),
					'preset_not_found'   => esc_html__( 'Preset not found.',                                      'woo-swatches-elementor' ),
					'prompt_name'        => esc_html__( 'Preset name:',                                           'woo-swatches-elementor' ),
					'new_preset_default' => esc_html__( 'My preset',                                              'woo-swatches-elementor' ),
					'confirm_update'     => esc_html__( 'Overwrite this preset with the current widget settings?','woo-swatches-elementor' ),
					'confirm_delete'     => esc_html__( 'Delete this preset?',                                    'woo-swatches-elementor' ),
					'generic_error'      => esc_html__( 'Something went wrong.',                                  'woo-swatches-elementor' ),
					'network_error'      => esc_html__( 'Network error.',                                         'woo-swatches-elementor' ),
				),
			)
		);

		wp_enqueue_script( $handle );

		// v1.7.0 — Editor-only stylesheet for the preset panel + any other
		// admin UI shown in the editor sidebar. The selectors are all
		// .wse-* / .term-wse-* scoped so they cannot leak outside our DOM.
		$css_handle = 'wse-admin-css';
		wp_register_style(
			$css_handle,
			WSE_URL . 'admin/admin.css',
			array(),
			WSE_VERSION
		);
		wp_enqueue_style( $css_handle );
	}

	/**
	 * Returns true on the WSE WC settings tab.
	 */
	private function is_wse_settings_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'woocommerce_page_wc-settings' !== $screen->id ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['tab'] ) && 'woo_swatches' === $_GET['tab'];
	}

	// ─────────────────────────────────────────────────────────────────────
	// Public helpers — used by Elementor widgets (Phase 10, Phase 12)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the registered handle name for a given asset type.
	 * Widgets reference these in get_script_depends() / get_style_depends().
	 *
	 * @param  string $asset One of: swatches_js, swatches_css, tooltip_css,
	 *                       add_to_cart_js, add_to_cart_css,
	 *                       form_field_dependency_js
	 * @return string        Registered handle or empty string if unknown.
	 */
	public static function handle( string $asset ): string {
		$map = array(
			'swatches_js'               => 'wse-swatches',
			'swatches_css'              => 'wse-swatches',
			'tooltip_css'               => 'wse-swatches-tooltip',
			'add_to_cart_js'            => 'wse-add-to-cart',
			'add_to_cart_css'           => 'wse-add-to-cart',
			'form_field_dependency_js'  => 'wse-form-field-dependency',
			// v1.2.0 — Widget 3 (ZYMARG Price)
			'price_js'                  => 'wse-price',
			'price_css'                 => 'wse-price',
			// v1.3.0 — Widget 4 (ZYMARG Variation Image Gallery)
			'gallery_js'                => 'wse-gallery',
			'gallery_css'               => 'wse-gallery',
			'gallery_lightbox_css'      => 'wse-gallery-lightbox',
		);
		return $map[ $asset ] ?? '';
	}

	/**
	 * v1.2.1 (F5) — Inline CSS for responsive per-type swatch widths.
	 *
	 * Reads the 12 global options from WC → Settings → WooSwatches →
	 * Swatch Sizes, sanitises each as a positive integer, and emits a
	 * <style> tag in <head> that drives CSS custom properties on
	 * .wse-swatch-{type} per breakpoint (desktop > 1024 px, tablet
	 * 769–1024 px, mobile ≤ 768 px).
	 *
	 * Why custom properties (not hard-coded width):
	 *   - Elementor Style controls on Widget 1 can override any custom
	 *     property via {{WRAPPER}} selectors per-instance.
	 *   - Themes with their own variable systems can override at any
	 *     specificity level without !important wars.
	 *
	 * Skipped on admin pages (not needed in /wp-admin) and when the
	 * plugin stylesheet is disabled (the body class wse-stylesheet-enabled
	 * is the gate for ALL of our visual rules).
	 *
	 * @return void
	 */
	public function print_swatch_sizes_inline_css(): void {

		if ( is_admin() ) {
			return;
		}
		if ( 'yes' !== get_option( 'wse_stylesheet', 'yes' ) ) {
			return;
		}

		// v1.3.5 (F4) — Read all 12 widths as CSS-length strings supporting
		// px / % / em / rem. Legacy integer values (pre-1.3.5) get treated
		// as px via wse_sanitize_css_length(). Invalid values fall back to
		// the default for that field.
		$sizes = array(
			'color'  => array(
				'd' => self::wse_sanitize_css_length( get_option( 'wse_color_w_d',  32 ), '32px' ),
				't' => self::wse_sanitize_css_length( get_option( 'wse_color_w_t',  32 ), '32px' ),
				'm' => self::wse_sanitize_css_length( get_option( 'wse_color_w_m',  28 ), '28px' ),
			),
			'image'  => array(
				'd' => self::wse_sanitize_css_length( get_option( 'wse_image_w_d',  56 ), '56px' ),
				't' => self::wse_sanitize_css_length( get_option( 'wse_image_w_t',  48 ), '48px' ),
				'm' => self::wse_sanitize_css_length( get_option( 'wse_image_w_m',  44 ), '44px' ),
			),
			'label'  => array(
				'd' => self::wse_sanitize_css_length( get_option( 'wse_label_w_d',  32 ), '32px' ),
				't' => self::wse_sanitize_css_length( get_option( 'wse_label_w_t',  32 ), '32px' ),
				'm' => self::wse_sanitize_css_length( get_option( 'wse_label_w_m',  28 ), '28px' ),
			),
			'button' => array(
				'd' => self::wse_sanitize_css_length( get_option( 'wse_button_w_d', 48 ), '48px' ),
				't' => self::wse_sanitize_css_length( get_option( 'wse_button_w_t', 44 ), '44px' ),
				'm' => self::wse_sanitize_css_length( get_option( 'wse_button_w_m', 40 ), '40px' ),
			),
		);

		// Build the inline stylesheet. We intentionally write to
		// .wse-swatch-{type} directly so the values cascade to the
		// existing --wse-swatch-size etc. used in swatches.css.
		// v1.2.3 (Issue 5) — !important on the structural width/height
		// rules. v1.2.1 shipped without !important assuming our 0,2,1
		// specificity would beat the cascade, but Elementor's per-widget
		// Swatch Size control (added in v1.2.0 via {{WRAPPER}} selectors)
		// resolves at 0,5,0 specificity and beat F5 cleanly. Same root
		// cause as v1.2.2's Issue 4 image-label fix. The !important here
		// makes the global Swatch Sizes section the source of truth for
		// per-type widths; per-widget overrides via Elementor's Custom
		// CSS still work but require !important on the user side too.
		$css = '';

		// Desktop (always applies as base; media queries below override).
		// v1.3.5 (F4) — values now carry their own CSS unit (px / % / em /
		// rem) so the literal 'px' suffix is dropped from the templates.
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-color{--wse-swatch-size:' . $sizes['color']['d'] . ';width:' . $sizes['color']['d'] . '!important;height:' . $sizes['color']['d'] . '!important;}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-image{--wse-swatch-size:' . $sizes['image']['d'] . ';}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-image .wse-swatch-img{width:' . $sizes['image']['d'] . '!important;height:' . $sizes['image']['d'] . '!important;}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-label{min-width:' . $sizes['label']['d'] . '!important;}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-button{min-width:' . $sizes['button']['d'] . '!important;}';

		// Tablet
		$css .= '@media (max-width:1024px){';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-color{--wse-swatch-size:' . $sizes['color']['t'] . ';width:' . $sizes['color']['t'] . '!important;height:' . $sizes['color']['t'] . '!important;}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-image{--wse-swatch-size:' . $sizes['image']['t'] . ';}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-image .wse-swatch-img{width:' . $sizes['image']['t'] . '!important;height:' . $sizes['image']['t'] . '!important;}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-label{min-width:' . $sizes['label']['t'] . '!important;}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-button{min-width:' . $sizes['button']['t'] . '!important;}';
		$css .= '}';

		// Mobile
		$css .= '@media (max-width:768px){';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-color{--wse-swatch-size:' . $sizes['color']['m'] . ';width:' . $sizes['color']['m'] . '!important;height:' . $sizes['color']['m'] . '!important;}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-image{--wse-swatch-size:' . $sizes['image']['m'] . ';}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-image .wse-swatch-img{width:' . $sizes['image']['m'] . '!important;height:' . $sizes['image']['m'] . '!important;}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-label{min-width:' . $sizes['label']['m'] . '!important;}';
		$css .= 'body.wse-stylesheet-enabled .wse-swatch-button{min-width:' . $sizes['button']['m'] . '!important;}';
		$css .= '}';

		echo "\n<style id=\"wse-swatch-sizes-inline\">" . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * v1.3.5 (F4) — Sanitize a CSS length string from a WC settings value.
	 *
	 * Accepts:
	 *   - Plain integers (legacy pre-1.3.5 values) → treated as px
	 *   - "32px", "10%", "2em", "1.5rem" — passed through if valid
	 *   - Anything else → fall back to $default
	 *
	 * @param mixed  $raw     Stored option value.
	 * @param string $default CSS length to use on invalid input (e.g. "32px").
	 * @return string A safe CSS length suitable for direct inclusion in a CSS rule.
	 */
	public static function wse_sanitize_css_length( $raw, string $default ): string {
		$raw = is_string( $raw ) ? trim( $raw ) : (string) $raw;
		if ( '' === $raw ) {
			return $default;
		}

		// Legacy integer (no unit) — treat as px.
		if ( ctype_digit( $raw ) ) {
			$n = (int) $raw;
			return ( $n > 0 ? $n : 1 ) . 'px';
		}

		// Float without unit (uncommon but defensive) — treat as px.
		if ( is_numeric( $raw ) ) {
			$f = (float) $raw;
			return ( $f > 0 ? $f : 1 ) . 'px';
		}

		// Validated: number (int or float) followed by px / % / em / rem.
		if ( preg_match( '/^(\d+(?:\.\d+)?)(px|%|em|rem)$/i', $raw, $m ) ) {
			$num  = (float) $m[1];
			$unit = strtolower( $m[2] );
			if ( $num <= 0 ) {
				return $default;
			}
			// Normalise: drop trailing zero from float (32.0px → 32px).
			$num_str = ( floor( $num ) === $num ) ? (string) (int) $num : (string) $num;
			return $num_str . $unit;
		}

		return $default;
	}
}
