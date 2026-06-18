<?php
/**
 * REST API Extension
 *
 * Appends a read-only `zymarg_swatches` field to WooCommerce's native REST
 * responses for products and product variations using register_rest_field —
 * no custom route needed.
 *
 * Products   → GET /wp-json/wc/v3/products/{id}
 * Variations → GET /wp-json/wc/v3/products/{id}/variations/{id}
 *
 * Delegates all attribute-type lookups to WSE_Attribute_Types and all
 * term-meta reads to WSE_Term_Meta — no duplicate logic.
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Rest_Extension {

	// ─────────────────────────────────────────────────────────────────────
	// Init
	// ─────────────────────────────────────────────────────────────────────

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_fields' ) );
		// v1.1.0 (B21) — extend the WC Store API too so headless storefronts
		// and the modern Cart & Checkout blocks see swatch metadata.
		add_action( 'woocommerce_init', array( $this, 'register_store_api_data' ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Field registration
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Register the zymarg_swatches field on both WC product types.
	 */
	public function register_fields(): void {

		$schema = array(
			'description' => esc_html__( 'ZYMARG Variation Swatches data.', 'woo-swatches-elementor' ),
			'type'        => 'object',
			'context'     => array( 'view', 'edit' ),
			'readonly'    => true,
		);

		register_rest_field(
			'product',
			'zymarg_swatches',
			array(
				'get_callback'    => array( $this, 'get_product_swatches' ),
				'update_callback' => null,
				'schema'          => $schema,
			)
		);

		register_rest_field(
			'product_variation',
			'zymarg_swatches',
			array(
				'get_callback'    => array( $this, 'get_variation_swatches' ),
				'update_callback' => null,
				'schema'          => $schema,
			)
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Callbacks
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Build swatch data for a variable product: all swatch-type attributes
	 * with their complete term lists.
	 *
	 * @param  array            $object  REST object data.
	 * @param  string           $field   Field name (unused).
	 * @param  WP_REST_Request  $request Current REST request.
	 * @return array<string, mixed>      Keyed by taxonomy slug.
	 */
	public function get_product_swatches( array $object, string $field, WP_REST_Request $request ): array {

		$product = wc_get_product( $object['id'] );

		if ( ! $product instanceof WC_Product_Variable ) {
			return array();
		}

		$result = array();

		foreach ( $product->get_attributes() as $attribute ) {
			/** @var WC_Product_Attribute $attribute */
			if ( ! $attribute->is_taxonomy() ) {
				continue;
			}

			$taxonomy = $attribute->get_name();
			$type     = WSE_Attribute_Types::get_attribute_type( $taxonomy );

			if ( ! WSE_Attribute_Types::is_swatch_type( $taxonomy ) ) {
				continue;
			}

			$terms = array();
			foreach ( $attribute->get_terms() as $term ) {
				$terms[] = $this->term_to_array( $term, $type );
			}

			$result[ $taxonomy ] = array(
				'type'  => $type,
				'label' => wc_attribute_label( $taxonomy, $product ),
				'terms' => $terms,
			);
		}

		return $result;
	}

	/**
	 * Build swatch data for a single variation: one entry per swatch
	 * attribute reflecting the specific term used by this variation.
	 *
	 * @param  array            $object  REST object data.
	 * @param  string           $field   Field name (unused).
	 * @param  WP_REST_Request  $request Current REST request.
	 * @return array<string, mixed>      Keyed by taxonomy slug.
	 */
	public function get_variation_swatches( array $object, string $field, WP_REST_Request $request ): array {

		$variation = wc_get_product( $object['id'] );

		if ( ! $variation instanceof WC_Product_Variation ) {
			return array();
		}

		$result = array();

		foreach ( $variation->get_attributes() as $taxonomy => $slug ) {
			if ( empty( $slug ) ) {
				continue; // "Any" slot — no specific term assigned.
			}

			$type = WSE_Attribute_Types::get_attribute_type( $taxonomy );

			if ( ! WSE_Attribute_Types::is_swatch_type( $taxonomy ) ) {
				continue;
			}

			$term = get_term_by( 'slug', $slug, $taxonomy );

			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$result[ $taxonomy ] = $this->term_to_array( $term, $type );
		}

		return $result;
	}

	// ─────────────────────────────────────────────────────────────────────
	// v1.1.0 (B21) — Store API extension
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Registers the same `zymarg_swatches` field on the public WC Store API
	 * (/wc/store/v1/products) so headless storefronts and the modern Cart
	 * & Checkout blocks can read swatch metadata without falling back to
	 * the authenticated /wc/v3/* routes.
	 *
	 * Uses woocommerce_store_api_register_endpoint_data() which is the
	 * official extension API for the Store API.
	 */
	public function register_store_api_data(): void {

		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'product',
				'namespace'       => 'zymarg/swatches',
				'data_callback'   => array( $this, 'store_api_data_callback' ),
				'schema_callback' => array( $this, 'store_api_schema_callback' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Returns swatch data for a Store API product response.
	 *
	 * @param  mixed $product Product object passed by the Store API extension API.
	 * @return array
	 */
	public function store_api_data_callback( $product ): array {

		if ( ! $product instanceof WC_Product_Variable ) {
			return array();
		}

		return $this->get_product_swatches(
			array( 'id' => $product->get_id() ),
			'zymarg_swatches',
			new WP_REST_Request()
		);
	}

	/**
	 * Schema definition for the Store API zymarg/swatches namespace.
	 *
	 * @return array
	 */
	public function store_api_schema_callback(): array {
		return array(
			'description' => esc_html__( 'ZYMARG Variation Swatches data.', 'woo-swatches-elementor' ),
			'type'        => array( 'object', 'array' ),
			'context'     => array( 'view', 'edit' ),
			'readonly'    => true,
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Normalise a WP_Term into a REST-safe swatch data array using the
	 * existing WSE_Term_Meta static accessors.
	 *
	 * @param  WP_Term $term
	 * @param  string  $type Swatch type returned by WSE_Attribute_Types.
	 * @return array<string, mixed>
	 */
	private function term_to_array( WP_Term $term, string $type ): array {

		$image_url = WSE_Term_Meta::get_image_url( $term->term_id, 'wse_swatch' );

		return array(
			'term_id'   => $term->term_id,
			'slug'      => $term->slug,
			'name'      => $term->name,
			'type'      => $type,
			'color'     => WSE_Term_Meta::get_color( $term->term_id ),
			'image_url' => $image_url,
			'label'     => $term->name,
		);
	}
}
