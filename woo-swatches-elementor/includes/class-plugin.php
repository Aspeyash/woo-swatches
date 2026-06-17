<?php
/**
 * Core plugin class — singleton bootstrap.
 *
 * Mirrors Emran Ahmed's battle-tested pattern exactly:
 * constants() → includes() → hooks() → do_action('wse_loaded')
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Plugin {

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations (no dynamic props)
	// ─────────────────────────────────────────────────────────────────────
	protected static ?WSE_Plugin $instance = null;

	// Child class hooks (populated phase by phase)
	protected ?object $assets          = null;
	protected ?object $attribute_types = null;
	protected ?object $term_meta       = null;
	protected ?object $local_attrs     = null;
	protected ?object $swatch_renderer = null;
	protected ?object $archive_swatches = null;
	protected ?object $cache           = null;
	protected ?object $thumb_generator = null;

	// ─────────────────────────────────────────────────────────────────────
	// Singleton accessor
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the single plugin instance.
	 * Creates it on first call.
	 */
	public static function instance(): static {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/** Prevent direct instantiation outside instance() */
	private function __construct() {
		$this->includes();
		$this->hooks();

		// Gap 1 — Extensibility action so other plugins/themes can hook in
		// Mirrors: do_action('woo_variation_swatches_loaded', $this)
		do_action( 'wse_loaded', $this );
	}

	/** Prevent cloning */
	private function __clone() {}

	/** Prevent unserialising */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}

	// ─────────────────────────────────────────────────────────────────────
	// includes() — require class files
	// Populated phase by phase. Stubs left as comments for all future phases.
	// ─────────────────────────────────────────────────────────────────────

	private function includes(): void {
		// Phase 1: activator already loaded in bootstrap before singleton init

		// Phase 2  — Attribute types (adds Color/Image/Label to WC type selector)
		require_once WSE_PATH . 'includes/class-attribute-types.php';
		$this->attribute_types = WSE_Attribute_Types::instance();

		// Phase 3  — Global attribute term meta (color picker + image uploader)
		require_once WSE_PATH . 'includes/class-term-meta.php';
		$this->term_meta = WSE_Term_Meta::instance();

		// Phase 4  — Local (per-product) attribute swatch UI
		require_once WSE_PATH . 'includes/class-local-attributes.php';
		$this->local_attrs = WSE_Local_Attributes::instance();

		// Phase 5  — Transient caching + invalidation
		require_once WSE_PATH . 'includes/class-cache.php';
		$this->cache = WSE_Cache::instance();

		// Phase 6  — Core swatch renderer + locate_template()
		require_once WSE_PATH . 'includes/class-swatch-renderer.php';
		$this->swatch_renderer = WSE_Swatch_Renderer::instance();

		// Phase 7  — swatches.js + form-field-dependency.js
		// (assets registered in Phase 8 class-assets.php)

		// Phase 8  — Asset registration (conditional loading, nonce, RTL)
		require_once WSE_PATH . 'includes/class-assets.php';
		$this->assets = WSE_Assets::instance();

		// Phase 9  — Archive/shop loop swatches (Gap 43)
		require_once WSE_PATH . 'includes/class-archive-swatches.php';
		$this->archive_swatches = WSE_Archive_Swatches::instance();

		// Phase 10 — Widget 1 loaded inside register_elementor_widgets()

		// Phase 11 — Add to cart templates (4 product types)
		// (PHP templates only, no class file)

		// Phase 12.0 — WooCommerce Settings Tab
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'load_settings_page' ) );

		// Phase 12 — Widget 2 loaded inside register_elementor_widgets()

		// Phase 13 — REST API, AJAX compat, Blocks compat, Thumbnail generator
		require_once WSE_PATH . 'includes/class-rest-extension.php';
		new WSE_Rest_Extension();
		require_once WSE_PATH . 'includes/class-ajax-compat.php';
		new WSE_Ajax_Compat();
		require_once WSE_PATH . 'includes/class-blocks-compat.php';
		new WSE_Blocks_Compat();
		require_once WSE_PATH . 'includes/class-thumbnail-generator.php';
		new WSE_Thumbnail_Generator();
	}

	// ─────────────────────────────────────────────────────────────────────
	// hooks() — register all WordPress / WooCommerce / Elementor hooks
	// ─────────────────────────────────────────────────────────────────────

	private function hooks(): void {

		// Gap 16 — Load plugin text domain for i18n
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Gap 54 — Register custom swatch image size (80×80 hard crop)
		// after_setup_theme is the correct hook for add_image_size()
		add_action( 'after_setup_theme', array( $this, 'register_image_sizes' ) );

		// Gap 3  — Body class state machine (drives ALL CSS states declaratively)
		// Mirrors Emran Ahmed's exact woo_variation_swatches_stylesheet_* pattern
		add_filter( 'body_class', array( $this, 'body_classes' ) );

		// Gap 26 — Elementor category + widget hooks.
		// These fire during Elementor's init, which is always after plugins_loaded,
		// so registering them here is safe regardless of plugin load order.
		// Widget PHP files are require_once'd only inside register_elementor_widgets()
		// where \Elementor\Widget_Base is guaranteed to exist — never in includes().
		if ( did_action( 'elementor/loaded' ) ) {
			// Elementor already loaded before our plugin (rare edge case) — bind now.
			$this->register_elementor_hooks();
		} else {
			add_action( 'elementor/loaded', array( $this, 'register_elementor_hooks' ) );
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// Public hook callbacks
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Gap 16 — Load plugin translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'woo-swatches-elementor',
			false,
			dirname( WSE_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Gap 54 — Register dedicated swatch image crop size.
	 *
	 * 80×80 hard-crop so browsers never download an oversized image
	 * just to display a 44px swatch circle/square.
	 * Store owners must run Regenerate Swatch Images after first activation
	 * (built-in tool added in Phase 14 — no third-party plugin needed).
	 */
	public function register_image_sizes(): void {
		add_image_size( 'wse_swatch', 80, 80, true );
	}

	/**
	 * Gap 3 + Gap 51 — Body class state machine.
	 *
	 * Mirrors Emran Ahmed's exact approach:
	 *   woo-variation-swatches-stylesheet-enabled / disabled
	 *   woo-variation-swatches-tooltip-enabled / disabled
	 *   woo-variation-swatches-style-{style}
	 *
	 * All CSS visual rules are written against these body classes —
	 * zero inline styles generated from PHP.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public function body_classes( array $classes ): array {

		// Base class — always present when plugin is active
		$classes[] = 'wse-swatches';

		// Shape: rounded (default 6px) | circle (50%) | square (0)
		$classes[] = 'wse-shape-' . sanitize_html_class(
			get_option( 'wse_shape', 'rounded' )
		);

		// Tooltip — mirrors Emran's enabled/disabled suffix exactly
		$classes[] = 'wse-tooltip-' . (
			'yes' === get_option( 'wse_tooltip', 'yes' ) ? 'enabled' : 'disabled'
		);

		// Gap 51 — Stylesheet body class (Emran Ahmed pattern exactly)
		// wse-stylesheet-enabled  → plugin CSS applies all visual rules
		// wse-stylesheet-disabled → theme/dev writes their own CSS entirely
		$classes[] = 'wse-stylesheet-' . (
			'yes' === get_option( 'wse_stylesheet', 'yes' ) ? 'enabled' : 'disabled'
		);

		// Out-of-stock behaviour: blur | cross | hide
		$classes[] = 'wse-oos-' . sanitize_html_class(
			get_option( 'wse_oos_behavior', 'blur' )
		);

		// Style variant (extensible for future style presets)
		$classes[] = 'wse-style-' . sanitize_html_class(
			get_option( 'wse_style', 'default' )
		);

		return $classes;
	}

	/**
	 * Fired on elementor/loaded — registers all Elementor-dependent hooks.
	 * Guaranteed to run only when Elementor is fully loaded, preventing the
	 * "Class Elementor\Widget_Base not found" fatal that occurs when widget
	 * files are required before Elementor initialises.
	 */
	public function register_elementor_hooks(): void {
		// Guard each hook individually — if the action already fired (e.g. when
		// the plugin is activated mid-request via WP-CLI or a redirect loop),
		// call the callback directly so widgets still register.
		if ( did_action( 'elementor/elements/categories_registered' ) ) {
			$this->register_elementor_category(
				\Elementor\Plugin::$instance->elements_manager
			);
		} else {
			add_action(
				'elementor/elements/categories_registered',
				array( $this, 'register_elementor_category' )
			);
		}

		if ( did_action( 'elementor/widgets/register' ) ) {
			$this->register_elementor_widgets(
				\Elementor\Plugin::$instance->widgets_manager
			);
		} else {
			add_action(
				'elementor/widgets/register',
				array( $this, 'register_elementor_widgets' )
			);
		}
	}

	/**
	 * Gap 26 — Register the WooSwatches widget category in Elementor.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor manager.
	 */
	public function register_elementor_category(
		\Elementor\Elements_Manager $elements_manager
	): void {
		$elements_manager->add_category(
			'woo-swatches-elementor',
			array(
				'title' => esc_html__( 'WooSwatches', 'woo-swatches-elementor' ),
				'icon'  => 'eicon-woocommerce',
			)
		);
	}

	/**
	 * Register Elementor widgets.
	 * Populated in Phase 10 and Phase 12.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor manager.
	 */
	public function register_elementor_widgets(
		\Elementor\Widgets_Manager $widgets_manager
	): void {
		// Load widget files here — Elementor\Widget_Base is available only
		// after Elementor fires elementor/widgets/register. Loading in
		// includes() / __construct() causes a fatal at plugins_loaded time.
		require_once WSE_PATH . 'widgets/class-widget-swatches.php';
		require_once WSE_PATH . 'widgets/class-widget-add-to-cart.php';

		$widgets_manager->register( new WSE_Widget_Swatches() );
		$widgets_manager->register( new WSE_Widget_Add_To_Cart() );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Public getters — other classes access plugin instances via these
	// ─────────────────────────────────────────────────────────────────────

	/** @return string Plugin version string. */
	public function get_version(): string {
		return WSE_VERSION;
	}
	public function load_settings_page( array $pages ): array {
		require_once WSE_PATH . 'includes/class-settings.php';
		$pages[] = new WSE_Settings();
		return $pages;
	}

}
