<?php
/**
 * Elementor Widget 3 — ZYMARG Price (v1.2.0)
 *
 * Renders a product's price as a fully configurable, standalone Elementor
 * widget that stays in sync with Widget 1's swatch selection on variable
 * products via the canonical form's variation events.
 *
 * Behaviour summary:
 *   • Simple product            — renders price always; sale-aware.
 *   • Variable product, no var. — renders the LOWEST active price across
 *                                 all variations. If any variation on the
 *                                 product is on sale, also renders the
 *                                 lowest regular price as a strikethrough
 *                                 subscript and shows a Sale badge
 *                                 (option (ii) per ZYMARG product spec).
 *   • Variable product, var.    — when the customer picks a variation via
 *     selected                    Widget 1, price.js subscribes to the
 *                                 canonical form's `found_variation` event
 *                                 and re-renders this widget with the
 *                                 specific variation's price. `reset_data`
 *                                 restores the lowest-price baseline.
 *
 * Widget identity:
 *   Slug   : zymarg-price
 *   Title  : ZYMARG Price
 *   Author : ZYMARG
 *   Version: 1.2.0 (introduced)
 *
 * Cross-widget sync:
 *   Widget 3 attaches to the canonical form Widget 2 already creates by
 *   matching on data-product-id / data-form-id. Widget 3 does NOT itself
 *   instantiate any form or variation engine — it is a pure consumer of
 *   the events `wc-add-to-cart-variation.js` already fires on the form.
 *   Multiple Widget 3 instances on the same page (e.g. main + sticky bar)
 *   all listen to the same form and update simultaneously.
 *
 * Gap 21 — get_script_depends() / get_style_depends()
 * Gap 22 — Dynamic tags on product_id control
 * Gap 26 — Custom 'woo-swatches-elementor' category
 * Gap 31 — has_widget_inner_wrapper() + is_dynamic_content()
 * Gap 34 — PHP 8.2+ explicit property declarations
 * Gap 46 — LFI guard on template includes
 *
 * @package WooSwatchesElementor
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

class WSE_Widget_Price extends \Elementor\Widget_Base {

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected string $widget_slug = 'zymarg-price';

	// ─────────────────────────────────────────────────────────────────────
	// Widget identity
	// ─────────────────────────────────────────────────────────────────────

	public function get_name(): string {
		return $this->widget_slug;
	}

	public function get_title(): string {
		return esc_html__( 'ZYMARG Price', 'woo-swatches-elementor' );
	}

	public function get_icon(): string {
		// eicon-price-table reads visually as "money / pricing" and pairs
		// well with the existing eicon-product-* set used by Widget 1 / 2.
		return 'eicon-price-table';
	}

	/** Gap 26 — custom widget category (shared with Widget 1 and Widget 2). */
	public function get_categories(): array {
		return array( 'woo-swatches-elementor' );
	}

	public function get_keywords(): array {
		return array(
			'zymarg',
			'woo',
			'woocommerce',
			'price',
			'sale',
			'regular',
			'variation',
			'variable',
			'product',
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 21 — Asset dependencies (resolved via WSE_Assets::handle())
	// Elementor will only load these handles on pages where Widget 3 is in
	// the layout, keeping non-product pages free of price.js/css.
	// ─────────────────────────────────────────────────────────────────────

	public function get_script_depends(): array {
		return array( WSE_Assets::handle( 'price_js' ) );
	}

	public function get_style_depends(): array {
		return array( WSE_Assets::handle( 'price_css' ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 31 — Elementor DOM optimisation + dynamic content flag
	// ─────────────────────────────────────────────────────────────────────

	/** Strips the extra .elementor-widget-container wrapper. */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * Prevents Elementor from caching this widget's output.
	 * Critical: a product can go on sale at any time, variations are added
	 * or removed, currency-switching plugins update prices per request —
	 * the rendered HTML must always be live.
	 */
	public function is_dynamic_content(): bool {
		return true;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Controls registration — populated in Phase 2 of the v1.2.0 work.
	// Phase 1 ships only the scaffold so the widget appears in the
	// Elementor panel and the registration plumbing can be smoke-tested.
	// ─────────────────────────────────────────────────────────────────────

	protected function register_controls(): void {

		// ── Product section ───────────────────────────────────────────────
		$this->start_controls_section(
			'section_product',
			array(
				'label' => esc_html__( 'Product', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		/**
		 * Gap 22 — product_id supports Elementor dynamic tags.
		 * On single-product templates the user picks "Post ID" so the
		 * widget resolves to whichever product is currently being viewed.
		 */
		$this->add_control(
			'product_id',
			array(
				'label'        => esc_html__( 'Product', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::TEXT,
				'description'  => esc_html__( 'Product ID. Use Dynamic Tags → Post ID on single-product templates.', 'woo-swatches-elementor' ),
				'dynamic'      => array( 'active' => true ),
				'placeholder'  => esc_html__( 'e.g. 123', 'woo-swatches-elementor' ),
				'default'      => '',
			)
		);

		$this->end_controls_section();

		// Phase 2 will add: Display section, Sale Badge section, plus the
		// full Style tab (Current Price typography/colour, Regular Price
		// typography/colour, Sale Badge styling, Spacing, Alignment).
	}

	// ─────────────────────────────────────────────────────────────────────
	// Render — populated in Phase 2.
	// Phase 1 ships a placeholder so the widget renders something visible
	// in the Elementor preview without errors.
	// ─────────────────────────────────────────────────────────────────────

	protected function render(): void {
		?>
		<div class="zymarg-price zymarg-price--scaffold">
			<em class="zymarg-price-scaffold-note">
				<?php esc_html_e( 'ZYMARG Price — full render lands in v1.2.0 Phase 2.', 'woo-swatches-elementor' ); ?>
			</em>
		</div>
		<?php
	}
}
