<?php
/**
 * WooCommerce Blocks Compatibility
 *
 * WC's block-based product grids bypass the classic shop-loop action hooks
 * entirely. This class re-attaches archive swatch rendering through the
 * filters available in WC Blocks.
 *
 * Coverage:
 *   A) All Products block  (WC Blocks < 9.0 / legacy)
 *      Filter: woocommerce_blocks_product_grid_item_html
 *
 *   B) Product Collection block (WC Blocks 9.0+ / WC 8.0+)
 *      Filter: render_block — scoped to woocommerce/product-collection
 *
 * Both paths delegate HTML generation to WSE_Archive_Swatches so rendering
 * logic is never duplicated.
 *
 * Respects the wse_archive_swatches global option — if archive swatches are
 * disabled in WooCommerce → Settings → WooSwatches, neither path fires.
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Blocks_Compat {

	// ─────────────────────────────────────────────────────────────────────
	// Init
	// ─────────────────────────────────────────────────────────────────────

	public function __construct() {

		if ( ! $this->is_blocks_active() ) {
			return;
		}

		// A) Legacy All Products block.
		add_filter(
			'woocommerce_blocks_product_grid_item_html',
			array( $this, 'inject_into_all_products_block' ),
			10,
			3
		);

		// B) Modern Product Collection block.
		add_filter( 'render_block', array( $this, 'inject_into_product_collection_block' ), 10, 2 );
	}

	// ─────────────────────────────────────────────────────────────────────
	// A) All Products block (WC Blocks < 9.0)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Appends swatch HTML to each product card rendered by the All Products block.
	 *
	 * @param  string      $html    Existing card HTML.
	 * @param  array       $data    Template data (unused).
	 * @param  WC_Product  $product The product.
	 * @return string
	 */
	public function inject_into_all_products_block( string $html, array $data, WC_Product $product ): string {

		if ( ! $this->should_inject( $product ) ) {
			return $html;
		}

		$swatch_html = $this->render_swatches( $product );

		return $swatch_html
			? str_replace( '</li>', $swatch_html . '</li>', $html )
			: $html;
	}

	// ─────────────────────────────────────────────────────────────────────
	// B) Product Collection block (WC Blocks 9.0+)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Post-processes Product Collection block output and splices swatch HTML
	 * into each product card.
	 *
	 * The filter fires for every block on every page; we exit early for
	 * non-matching block names to keep overhead minimal.
	 *
	 * @param  string $html       Rendered block HTML.
	 * @param  array  $block      Parsed block data (includes blockName).
	 * @return string
	 */
	public function inject_into_product_collection_block( string $html, array $block ): string {

		if ( 'woocommerce/product-collection' !== ( $block['blockName'] ?? '' ) ) {
			return $html;
		}

		if ( 'yes' !== get_option( 'wse_archive_swatches', 'yes' ) ) {
			return $html;
		}

		// Each product card carries a data-product_id attribute on its <li>.
		return (string) preg_replace_callback(
			'/(<li[^>]+data-product_id="(\d+)"[^>]*>)(.*?)(<\/li>)/is',
			function ( array $m ): string {
				$product_id = (int) $m[2];
				$product    = wc_get_product( $product_id );

				if ( ! $this->should_inject( $product ) ) {
					return $m[0];
				}

				$swatch_html = $this->render_swatches( $product );

				return $swatch_html
					? $m[1] . $m[3] . $swatch_html . $m[4]
					: $m[0];
			},
			$html
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Delegates archive swatch HTML generation to WSE_Archive_Swatches.
	 *
	 * v1.1.0 (B2) — render_for_product() now exists on WSE_Archive_Swatches
	 * (added in this release), so this code path actually emits markup.
	 * In v1.0.5 this method's method_exists() guard silently returned ''
	 * for every call, making the entire WC Blocks integration inert.
	 *
	 * @param  WC_Product $product
	 * @return string HTML or empty string.
	 */
	private function render_swatches( WC_Product $product ): string {

		if ( ! class_exists( 'WSE_Archive_Swatches' ) ) {
			return '';
		}

		$instance = WSE_Archive_Swatches::instance();

		if ( ! method_exists( $instance, 'render_for_product' ) ) {
			return '';
		}

		ob_start();
		$instance->render_for_product( $product );
		return (string) ob_get_clean();
	}

	/**
	 * Whether swatches should be injected for a given product.
	 *
	 * @param  WC_Product|false $product
	 * @return bool
	 */
	private function should_inject( $product ): bool {
		return $product instanceof WC_Product_Variable
			&& 'yes' === get_option( 'wse_archive_swatches', 'yes' );
	}

	/**
	 * Whether WooCommerce Blocks is present in any form.
	 *
	 * @return bool
	 */
	private function is_blocks_active(): bool {
		return class_exists( '\Automattic\WooCommerce\Blocks\Package' )
			|| function_exists( 'woocommerce_store_api_register_endpoint_data' );
	}
}
