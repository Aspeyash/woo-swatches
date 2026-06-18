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
		);
		return $map[ $asset ] ?? '';
	}
}
