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

		// v1.1.0 (B3) — Per-page form registry coordinating canonical/presenter ownership
		require_once WSE_PATH . 'includes/class-form-registry.php';
		WSE_Form_Registry::instance();

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

		// v1.4.5 — Buy Now (AJAX handler + isolated checkout session)
		require_once WSE_PATH . 'includes/class-buy-now.php';
		WSE_Buy_Now::instance();
	}

	// ─────────────────────────────────────────────────────────────────────
	// hooks() — register all WordPress / WooCommerce / Elementor hooks
	// ─────────────────────────────────────────────────────────────────────

	private function hooks(): void {

		// Gap 16 — Load plugin text domain for i18n
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// v1.1.2 — Defensive coercion of $_POST['variation_id'] to int.
		// Dokan Pro's Order Min Max module declares its validate_add_to_cart()
		// callback with a strict `int $variation_id` parameter. When WC's
		// native form handler passes an empty string (variable product
		// submitted without a variation selected), PHP throws a fatal
		// TypeError before our AJAX path even has a chance to run.
		// Casting at priority 1 ensures the value is always an int from
		// the moment any add-to-cart validator runs.
		add_action( 'init', array( $this, 'coerce_variation_id_post' ), 1 );

		// Gap 54 — Register custom swatch image size (80×80 hard crop)
		add_action( 'after_setup_theme', array( $this, 'register_image_sizes' ) );

		// Gap 3  — Body class state machine
		add_filter( 'body_class', array( $this, 'body_classes' ) );

		// v1.1.5 — Body class for hiding the View Cart link.
		// CSS rules in assets/css/add-to-cart.css use the wse-hide-view-cart
		// body class to suppress WooCommerce's client-side
		// `<a class="added_to_cart wc-forward">` link injection (which my
		// reactive JS strip lost the race against in v1.1.4). The CSS rule
		// applies before WC's <script> runs, so there is no timing window
		// where the link is visible.
		add_filter( 'body_class', array( $this, 'maybe_add_hide_view_cart_class' ) );

		// v1.1.0 (Feature A) — "View Cart" link toggle.
		add_filter( 'wc_add_to_cart_message_html',         array( $this, 'maybe_strip_view_cart_link' ), 20, 2 );
		add_filter( 'woocommerce_add_to_cart_fragments',   array( $this, 'maybe_strip_view_cart_in_fragments' ), 20 );

		// v1.1.0 (Feature A) — propagate the toggle into WSEParams JS payload.
		add_filter( 'wse_frontend_params',                 array( $this, 'inject_view_cart_param' ), 10, 1 );

		// v1.1.0 — Hard-cut migration notice for stale template overrides.
		add_action( 'admin_notices',                       array( $this, 'maybe_render_template_migration_notice' ) );
		add_action( 'admin_post_wse_dismiss_template_notice', array( $this, 'handle_dismiss_template_notice' ) );

		// Gap 26 — Elementor category + widget hooks.
		if ( did_action( 'elementor/loaded' ) ) {
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
	 * v1.1.2 — Coerce $_POST['variation_id'] to int.
	 *
	 * Defensive workaround for Dokan Pro's Order Min Max module (and any
	 * other third-party validator that declares a strict-typed
	 * `int $variation_id` parameter on the woocommerce_add_to_cart_validation
	 * filter). WooCommerce core passes an empty string when no variation
	 * is selected, which would otherwise trigger a fatal TypeError in the
	 * strict validator before our JS-level guard has a chance to act.
	 *
	 * Runs at `init` priority 1 — before WC_Form_Handler::add_to_cart_action
	 * (which runs at `init` priority 10 by default) processes the request.
	 * Skipped on AJAX/REST requests where WC takes the wc-ajax path.
	 */
	public function coerce_variation_id_post(): void {

		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['variation_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['variation_id'] = (int) wp_unslash( $_POST['variation_id'] );
		}
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

		// v1.2.0 — Widget 3 (ZYMARG Price). Owns price rendering on simple
		// and variable products and stays in sync with Widget 1 via the
		// canonical form's variation events. See widgets/class-widget-price.php.
		require_once WSE_PATH . 'widgets/class-widget-price.php';

		// v1.3.0 — Widget 4 (ZYMARG Variation Image Gallery). Renders the
		// product gallery with 5 desktop layout options, mobile swipe
		// carousel, hover-zoom, lightbox, and per-variation image swap
		// driven by the canonical form's found_variation events. See
		// widgets/class-widget-variation-image-gallery.php.
		require_once WSE_PATH . 'widgets/class-widget-variation-image-gallery.php';

		$widgets_manager->register( new WSE_Widget_Swatches() );
		$widgets_manager->register( new WSE_Widget_Add_To_Cart() );
		$widgets_manager->register( new WSE_Widget_Price() );
		$widgets_manager->register( new WSE_Widget_Variation_Image_Gallery() );
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

	/**
	 * v1.1.5 — Adds the `wse-hide-view-cart` body class when the global
	 * "Show 'View Cart' Link" toggle is off so the CSS rule in
	 * assets/css/add-to-cart.css can hide the inline injected link before
	 * WooCommerce's frontend JS even runs. Per-widget overrides ("No") add
	 * the same class via JS at DOMReady (see add-to-cart.js).
	 *
	 * @param string[] $classes
	 * @return string[]
	 */
	public function maybe_add_hide_view_cart_class( array $classes ): array {
		if ( 'no' === get_option( 'wse_show_view_cart_link', 'yes' ) ) {
			$classes[] = 'wse-hide-view-cart';
		}
		return $classes;
	}

	// ─────────────────────────────────────────────────────────────────────
	// v1.1.0 (Feature A) — "View Cart" link toggle filter callbacks
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Strips the View Cart anchor (<a class="wc-forward">) from
	 * wc_add_to_cart_message_html when the user has disabled the toggle.
	 *
	 * @param string|mixed $message HTML message produced by WC.
	 * @param array|int    $products Products map (unused).
	 * @return string|mixed
	 */
	public function maybe_strip_view_cart_link( $message, $products = array() ) {
		if ( ! is_string( $message ) ) {
			return $message;
		}
		if ( 'yes' === get_option( 'wse_show_view_cart_link', 'yes' ) ) {
			return $message;
		}
		return preg_replace(
			'/<a[^>]*class="[^"]*\bwc-forward\b[^"]*"[^>]*>[\s\S]*?<\/a>/i',
			'',
			$message
		);
	}

	/**
	 * Strips the same anchor from any string fragment that ships an
	 * embedded View Cart button (mini-cart notice fragment, etc).
	 *
	 * @param array $fragments WC fragment map.
	 * @return array
	 */
	public function maybe_strip_view_cart_in_fragments( $fragments ): array {
		if ( ! is_array( $fragments ) ) {
			return (array) $fragments;
		}
		if ( 'yes' === get_option( 'wse_show_view_cart_link', 'yes' ) ) {
			return $fragments;
		}
		foreach ( $fragments as $key => $value ) {
			if ( is_string( $value ) ) {
				$fragments[ $key ] = preg_replace(
					'/<a[^>]*class="[^"]*\bwc-forward\b[^"]*"[^>]*>[\s\S]*?<\/a>/i',
					'',
					$value
				);
			}
		}
		return $fragments;
	}

	/**
	 * Adds show_view_cart_link to the WSEParams payload localized to JS,
	 * so add-to-cart.js can also strip on the client when stale page-cache
	 * fragments arrive.
	 *
	 * v1.1.1 — also propagates multivendor compat + added-to-cart toast +
	 * the i18n string for the toast.
	 *
	 * @param array $params Existing WSEParams payload.
	 * @return array
	 */
	public function inject_view_cart_param( array $params ): array {
		$params['show_view_cart_link'] = 'yes' === get_option( 'wse_show_view_cart_link', 'yes' );

		// v1.1.1 — Multi-vendor compatibility mode.
		$mvc_setting = (string) get_option( 'wse_multivendor_compat', 'auto' );
		$mvc_active  = false;
		if ( 'on' === $mvc_setting ) {
			$mvc_active = true;
		} elseif ( 'auto' === $mvc_setting ) {
			$mvc_active = $this->detect_multivendor_plugin();
		}
		$params['multivendor_compat'] = $mvc_active;

		// v1.1.1 — Added-to-cart toast.
		$params['show_added_toast'] = 'yes' === get_option( 'wse_show_added_toast', 'yes' );
		$params['cart_url']         = $params['cart_url'] ?? esc_url( wc_get_cart_url() );

		// i18n strings for the toast.
		$params['i18n']               = (array) ( $params['i18n'] ?? array() );
		$params['i18n']['toast_added'] = esc_html__( 'Added to cart', 'woo-swatches-elementor' );
		$params['i18n']['view_cart']   = esc_html__( 'View cart', 'woo-swatches-elementor' );

		// v1.2.0 — Currency / decimal / separator settings consumed by
		// price.js's formatPrice() helper. Pulled straight from WC's option
		// store so any third-party currency switcher that filters the
		// underlying functions also affects Widget 3 in real time. Kept
		// under the 'price' sub-key so future price-related params land in
		// a single namespace without polluting the top-level WSEParams.
		$params['price'] = array(
			'currency_symbol' => html_entity_decode(
				get_woocommerce_currency_symbol(),
				ENT_QUOTES,
				'UTF-8'
			),
			'currency_pos'    => (string) get_option( 'woocommerce_currency_pos', 'left' ),
			'decimals'        => (int) wc_get_price_decimals(),
			'decimal_sep'     => (string) wc_get_price_decimal_separator(),
			'thousand_sep'    => (string) wc_get_price_thousand_separator(),
		);

		return $params;
	}

	/**
	 * v1.1.1 — Returns true when Dokan / WCFM / WC Vendors is active.
	 *
	 * Used to decide whether the AJAX add-to-cart cart-hash verification
	 * should kick in when WC returns {error: true}. These plugins are known
	 * to filter `woocommerce_add_to_cart_validation` to false on AJAX
	 * requests for vendor products even though the cart has been updated
	 * by a parallel server-side mechanism.
	 */
	private function detect_multivendor_plugin(): bool {
		$markers = array(
			// Dokan free + Pro
			'WeDevs_Dokan',
			'Dokan_Pro',
			// WCFM Marketplace
			'WCFM',
			'WCFMmp',
			// WC Vendors
			'WC_Vendors',
			'WCVendors_Pro',
			// MultiVendorX (formerly WC Marketplace)
			'WCMp',
			'MultiVendorX',
		);
		foreach ( $markers as $class ) {
			if ( class_exists( $class ) ) {
				return true;
			}
		}
		return false;
	}

	// ─────────────────────────────────────────────────────────────────────
	// v1.1.0 — Hard-cut template-override migration notice
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * v1.1.0 templates have meaningfully different markup vs v1.0.5.
	 * Stores carrying child-theme overrides will likely render incorrectly
	 * until the overrides are re-synced. This admin notice surfaces the
	 * affected files exactly once per site (dismiss persists in the
	 * wse_template_override_acks option).
	 */
	public function maybe_render_template_migration_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$ack_key  = 'wse_v1_1_template_migration';
		$ack_list = (array) get_option( 'wse_template_override_acks', array() );
		if ( in_array( $ack_key, $ack_list, true ) ) {
			return;
		}

		$overrides = $this->detect_stale_template_overrides();
		if ( empty( $overrides ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wse_dismiss_template_notice' ),
			'wse_dismiss_template_notice'
		);
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'ZYMARG Variation Swatches v1.1.0 — Template override action required', 'woo-swatches-elementor' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'The following child-theme overrides were written for an earlier template structure (v1.0.5) and will not work correctly until updated to match the v1.1.0 templates:', 'woo-swatches-elementor' ); ?>
			</p>
			<ul style="list-style:disc;margin-left:24px">
				<?php foreach ( $overrides as $rel ) : ?>
					<li><code><?php echo esc_html( $rel ); ?></code></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button button-secondary">
					<?php esc_html_e( 'I have re-synced these — dismiss this notice', 'woo-swatches-elementor' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Returns the relative paths of any plugin templates that have a
	 * theme/child-theme override on disk. We don't try to inspect the
	 * override contents — the notice is a "hey, double-check these"
	 * reminder, not an automated diff.
	 *
	 * @return array<int, string>
	 */
	private function detect_stale_template_overrides(): array {
		$relatives = array(
			'woo-swatches-elementor/add-to-cart/variable.php',
			'woo-swatches-elementor/swatches/wrapper.php',
			'woo-swatches-elementor/swatches/color.php',
			'woo-swatches-elementor/swatches/image.php',
			'woo-swatches-elementor/swatches/label.php',
		);

		$bases = array(
			get_stylesheet_directory(),
			get_template_directory(),
		);

		$found = array();
		foreach ( $bases as $base ) {
			foreach ( $relatives as $rel ) {
				$path = $base . '/' . $rel;
				if ( file_exists( $path ) ) {
					$found[] = str_replace( ABSPATH, '', $path );
				}
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * Records the v1.1.0 template-migration notice as acknowledged.
	 */
	public function handle_dismiss_template_notice(): void {
		check_admin_referer( 'wse_dismiss_template_notice' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'woo-swatches-elementor' ), '', 403 );
		}

		$ack_list   = (array) get_option( 'wse_template_override_acks', array() );
		$ack_list[] = 'wse_v1_1_template_migration';
		update_option( 'wse_template_override_acks', array_values( array_unique( $ack_list ) ) );

		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

}
