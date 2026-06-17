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
