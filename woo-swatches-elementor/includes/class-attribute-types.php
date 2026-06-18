<?php
/**
 * WooCommerce attribute type registration and detection.
 *
 * Registers Color, Image, Label, and Button as native WooCommerce
 * attribute types so they appear in the WC attribute type dropdown at
 * Products → Attributes → Add/Edit Attribute.
 *
 * Also provides the central get_attribute_type() static method used by
 * every other class that needs to know what kind of swatch to render.
 *
 * Gap 5  — product_attributes_type_selector filter
 * Gap 7  — wp_woocommerce_attribute_taxonomies DB read
 * Gap 42 — $wpdb->prepare() on all direct DB queries
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Attribute_Types {

	// ─────────────────────────────────────────────────────────────────────
	// Constants
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * All swatch types this plugin supports.
	 * Used by the renderer to decide whether to replace a WC <select> or
	 * leave it untouched.
	 */
	const SUPPORTED_TYPES = array( 'color', 'image', 'label', 'button' );

	/**
	 * Fallback type when an attribute has no type set or is unrecognised.
	 * 'select' means: leave WooCommerce's native dropdown in place.
	 */
	const FALLBACK_TYPE = 'select';

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected static ?WSE_Attribute_Types $instance = null;

	/**
	 * In-memory cache for attribute type lookups.
	 * Keyed by sanitised attribute name (without pa_ prefix).
	 * Lives for the duration of the request — avoids repeated DB queries
	 * when multiple swatches render on the same page.
	 *
	 * @var array<string, string>
	 */
	private array $type_cache = array();

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
		/**
		 * Gap 5 — Register our custom types in WooCommerce's attribute
		 * type selector.
		 *
		 * Without this, the Products → Attributes screen only shows
		 * "Select" and "Text" in the Type dropdown. This adds:
		 *   Color | Image | Label | Button
		 *
		 * @see woocommerce/includes/admin/meta-boxes/class-wc-meta-box-product-data.php
		 */
		add_filter(
			'product_attributes_type_selector',
			array( $this, 'register_types' )
		);

		/**
		 * Clear the in-memory type cache when a WC attribute taxonomy is
		 * saved — so the next request reads fresh data from the DB.
		 * This covers both "Add Attribute" and "Edit Attribute" in admin.
		 */
		add_action( 'woocommerce_attribute_added',   array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_attribute_updated', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_attribute_deleted', array( $this, 'clear_cache' ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 5 — Register types
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Appends our swatch types to WooCommerce's built-in type list.
	 *
	 * @param array<string, string> $types Existing type key → label pairs.
	 * @return array<string, string>
	 */
	public function register_types( array $types ): array {
		$types['color']  = esc_html__( 'Color',  'woo-swatches-elementor' );
		$types['image']  = esc_html__( 'Image',  'woo-swatches-elementor' );
		$types['label']  = esc_html__( 'Label',  'woo-swatches-elementor' );
		$types['button'] = esc_html__( 'Button', 'woo-swatches-elementor' );
		return $types;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 7 + Gap 42 — Attribute type detection
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the swatch type for a given attribute.
	 *
	 * Lookup priority:
	 *   1. In-memory cache (request-scoped, no DB hit)
	 *   2. wp_woocommerce_attribute_taxonomies DB table (global attributes)
	 *   3. 'wse_attribute_type' developer filter (allows runtime override)
	 *   4. FALLBACK_TYPE ('select') if nothing is found
	 *
	 * For local (per-product) attributes the caller should pass the type
	 * directly — this method only handles global WC taxonomies.
	 *
	 * @param string $attribute_name Raw attribute name, e.g. 'pa_color' or 'color'.
	 * @return string One of: 'color' | 'image' | 'label' | 'button' | 'select' | 'text'
	 */
	public static function get_attribute_type( string $attribute_name ): string {
		$self = static::instance();

		// Normalise: strip 'pa_' prefix, sanitise
		$name = sanitize_key( str_replace( 'pa_', '', $attribute_name ) );

		if ( empty( $name ) ) {
			return self::FALLBACK_TYPE;
		}

		// ── 1. In-memory cache ────────────────────────────────────────────
		if ( isset( $self->type_cache[ $name ] ) ) {
			return $self->type_cache[ $name ];
		}

		// ── 2. DB lookup — Gap 7 + Gap 42 ────────────────────────────────
		global $wpdb;

		// Gap 42 — always use $wpdb->prepare() for raw queries
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$db_type = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT attribute_type
				 FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
				 WHERE attribute_name = %s
				 LIMIT 1",
				$name
			)
		);

		// Treat empty string and null as fallback
		$type = ! empty( $db_type ) ? $db_type : self::FALLBACK_TYPE;

		// ── 3. Developer filter — allows runtime override ─────────────────
		// Usage: add_filter('wse_attribute_type', function($type, $attr) {
		//            return ($attr === 'pa_finish') ? 'color' : $type;
		//        }, 10, 2);
		$type = (string) apply_filters( 'wse_attribute_type', $type, $attribute_name );

		// ── 4. Cache and return ───────────────────────────────────────────
		$self->type_cache[ $name ] = $type;

		return $type;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Helper methods
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns true if the attribute should render as a swatch
	 * (i.e. its type is one of our supported types, not 'select' or 'text').
	 *
	 * Used by WSE_Swatch_Renderer to decide whether to intercept the
	 * woocommerce_dropdown_variation_attribute_options_html filter output.
	 *
	 * @param string $attribute_name Raw attribute name, e.g. 'pa_color'.
	 * @return bool
	 */
	public static function is_swatch_type( string $attribute_name ): bool {
		return in_array(
			static::get_attribute_type( $attribute_name ),
			self::SUPPORTED_TYPES,
			true
		);
	}

	/**
	 * Returns true for a specific type match.
	 *
	 * @param string $attribute_name Raw attribute name.
	 * @param string $type           Type to test against: 'color'|'image'|'label'|'button'.
	 * @return bool
	 */
	public static function is_type( string $attribute_name, string $type ): bool {
		return static::get_attribute_type( $attribute_name ) === $type;
	}

	/**
	 * Returns all supported type keys.
	 *
	 * @return string[]
	 */
	public static function get_supported_types(): array {
		return self::SUPPORTED_TYPES;
	}

	/**
	 * Returns all supported types as a key → label array
	 * suitable for Elementor select controls or admin dropdowns.
	 *
	 * @return array<string, string>
	 */
	public static function get_types_for_select(): array {
		return array(
			'color'  => esc_html__( 'Color',    'woo-swatches-elementor' ),
			'image'  => esc_html__( 'Image',    'woo-swatches-elementor' ),
			'label'  => esc_html__( 'Label',    'woo-swatches-elementor' ),
			'button' => esc_html__( 'Button',   'woo-swatches-elementor' ),
			'select' => esc_html__( 'Dropdown', 'woo-swatches-elementor' ),
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Cache management
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Clears the in-memory type cache.
	 * Called when any WC attribute taxonomy is added, updated, or deleted.
	 * Also available as a public method for use by WSE_Cache::flush_all().
	 */
	public function clear_cache(): void {
		$this->type_cache = array();
	}

	/**
	 * Removes a single attribute from the in-memory cache by name.
	 *
	 * @param string $attribute_name Raw attribute name, e.g. 'pa_color'.
	 */
	public function clear_cache_for( string $attribute_name ): void {
		$name = sanitize_key( str_replace( 'pa_', '', $attribute_name ) );
		unset( $this->type_cache[ $name ] );
	}
}
