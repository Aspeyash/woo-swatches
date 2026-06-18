<?php
/**
 * Elementor Widget 3 — ZYMARG Price (v1.2.0)
 *
 * Renders a product's price as a fully configurable, standalone Elementor
 * widget that stays in sync with Widget 1's swatch selection on variable
 * products via the canonical form's variation events.
 *
 * Behaviour summary (v1.2.0):
 *   • Simple product            — renders price always; sale-aware.
 *   • Variable product, no var. — renders the LOWEST active price across
 *                                 all variations. If any variation on the
 *                                 product is on sale, also renders the
 *                                 LOWEST regular price as a strikethrough
 *                                 subscript and shows a Sale badge
 *                                 (option (ii) per ZYMARG product spec).
 *   • Variable product, var.    — when the customer picks a variation via
 *     selected                    Widget 1, price.js subscribes to the
 *                                 canonical form's `found_variation` event
 *                                 and re-renders this widget with the
 *                                 specific variation's price. `reset_data`
 *                                 restores the lowest-price baseline.
 *
 * Cross-widget sync:
 *   Widget 3 attaches to the canonical form Widget 2 already creates by
 *   matching on data-product-id / data-form-id. Widget 3 does NOT itself
 *   instantiate any form or variation engine — it is a pure consumer of
 *   the events `wc-add-to-cart-variation.js` already fires on the form.
 *   Multiple Widget 3 instances on the same page (e.g. main + sticky bar)
 *   all listen to the same form and update simultaneously.
 *
 * Widget identity:
 *   Slug   : zymarg-price
 *   Title  : ZYMARG Price
 *   Author : ZYMARG
 *   Version: 1.2.0 (introduced)
 *
 * Gap 21 — get_script_depends() / get_style_depends()
 * Gap 22 — Dynamic tags on product_id control
 * Gap 26 — Custom 'woo-swatches-elementor' category
 * Gap 31 — has_widget_inner_wrapper() + is_dynamic_content()
 * Gap 34 — PHP 8.2+ explicit property declarations
 * Gap 41 — add_responsive_control() for spacing/alignment
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

	/**
	 * Request-scoped cache for validated safe template paths.
	 * Mirrors the LFI-guard cache in WSE_Swatch_Renderer.
	 *
	 * @var array<string, string>
	 */
	private static array $template_path_cache = array();

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
		return 'eicon-price-table';
	}

	/** Gap 26 — custom widget category (shared with Widget 1 and Widget 2). */
	public function get_categories(): array {
		return array( 'woo-swatches-elementor' );
	}

	public function get_keywords(): array {
		return array(
			'zymarg', 'woo', 'woocommerce', 'price', 'sale',
			'regular', 'variation', 'variable', 'product',
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 21 — Asset dependencies (resolved via WSE_Assets::handle())
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

	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	public function is_dynamic_content(): bool {
		return true;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Controls registration
	// ─────────────────────────────────────────────────────────────────────

	protected function register_controls(): void {
		$this->register_content_controls();
		$this->register_style_current_price();
		$this->register_style_regular_price();
		$this->register_style_sale_badge();
		$this->register_style_layout();
	}

	// ── CONTENT TAB ──────────────────────────────────────────────────────

	private function register_content_controls(): void {

		// ── Product ───────────────────────────────────────────────────────
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
				'description'  => esc_html__(
					'Product ID. Use Dynamic Tags → Post ID on single-product templates.',
					'woo-swatches-elementor'
				),
				'dynamic'      => array( 'active' => true ),
				'placeholder'  => esc_html__( 'e.g. 123', 'woo-swatches-elementor' ),
				'default'      => '',
			)
		);

		$this->end_controls_section();

		// ── Display ───────────────────────────────────────────────────────
		$this->start_controls_section(
			'section_display',
			array(
				'label' => esc_html__( 'Display', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'        => esc_html__( 'Show price', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'woo-swatches-elementor' ),
				'label_off'    => esc_html__( 'No',  'woo-swatches-elementor' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'default_display_style',
			array(
				'label'   => esc_html__( 'Default display (variable products)', 'woo-swatches-elementor' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'lowest',
				'options' => array(
					'lowest'           => esc_html__( 'Lowest price only',            'woo-swatches-elementor' ),
					'lowest_with_from' => esc_html__( 'Lowest with "From" prefix',    'woo-swatches-elementor' ),
					'range'            => esc_html__( 'Price range (low – high)',     'woo-swatches-elementor' ),
				),
				'description' => esc_html__(
					'How the price displays on a variable product BEFORE the customer picks a variation. Once a variation is selected, the widget always shows that variation\'s exact price.',
					'woo-swatches-elementor'
				),
				'condition' => array( 'show_price' => 'yes' ),
			)
		);

		$this->add_control(
			'from_prefix_text',
			array(
				'label'       => esc_html__( '"From" prefix text', 'woo-swatches-elementor' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => esc_html__( 'From', 'woo-swatches-elementor' ),
				'condition'   => array(
					'show_price'             => 'yes',
					'default_display_style'  => 'lowest_with_from',
				),
			)
		);

		$this->add_control(
			'regular_price_position',
			array(
				'label'   => esc_html__( 'Regular price (when on sale)', 'woo-swatches-elementor' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'subscript',
				'options' => array(
					'subscript' => esc_html__( 'Inline as subscript (small, struck)', 'woo-swatches-elementor' ),
					'beside'    => esc_html__( 'Inline beside (same baseline)',        'woo-swatches-elementor' ),
					'below'     => esc_html__( 'Below the sale price',                 'woo-swatches-elementor' ),
					'hide'      => esc_html__( 'Hide regular price entirely',          'woo-swatches-elementor' ),
				),
				'condition' => array( 'show_price' => 'yes' ),
			)
		);

		$this->add_control(
			'show_sale_badge',
			array(
				'label'        => esc_html__( 'Show sale badge', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'woo-swatches-elementor' ),
				'label_off'    => esc_html__( 'No',  'woo-swatches-elementor' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'show_price' => 'yes' ),
			)
		);

		$this->add_control(
			'sale_badge_text',
			array(
				'label'     => esc_html__( 'Sale badge text', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => esc_html__( 'Sale', 'woo-swatches-elementor' ),
				'condition' => array(
					'show_price'      => 'yes',
					'show_sale_badge' => 'yes',
				),
			)
		);

		$this->end_controls_section();
	}

	// ── STYLE TAB — Current Price ────────────────────────────────────────

	private function register_style_current_price(): void {

		$this->start_controls_section(
			'section_style_current',
			array(
				'label' => esc_html__( 'Current Price', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'current_typography',
				'label'    => esc_html__( 'Typography', 'woo-swatches-elementor' ),
				'selector' => '{{WRAPPER}} .zymarg-price-current',
			)
		);

		$this->add_control(
			'current_color',
			array(
				'label'     => esc_html__( 'Color (regular)', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-price' => '--zymarg-price-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'current_color_on_sale',
			array(
				'label'     => esc_html__( 'Color (when on sale)', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-price' => '--zymarg-price-color-on-sale: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	// ── STYLE TAB — Regular Price (struck) ───────────────────────────────

	private function register_style_regular_price(): void {

		$this->start_controls_section(
			'section_style_regular',
			array(
				'label' => esc_html__( 'Regular Price (struck)', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'regular_typography',
				'label'    => esc_html__( 'Typography', 'woo-swatches-elementor' ),
				'selector' => '{{WRAPPER}} .zymarg-price-was',
			)
		);

		$this->add_control(
			'regular_color',
			array(
				'label'     => esc_html__( 'Color', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-price' => '--zymarg-price-was-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'regular_opacity',
			array(
				'label'      => esc_html__( 'Opacity', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-price' => '--zymarg-price-was-opacity: {{SIZE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	// ── STYLE TAB — Sale Badge ───────────────────────────────────────────

	private function register_style_sale_badge(): void {

		$this->start_controls_section(
			'section_style_sale_badge',
			array(
				'label'     => esc_html__( 'Sale Badge', 'woo-swatches-elementor' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_sale_badge' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'sale_badge_typography',
				'label'    => esc_html__( 'Typography', 'woo-swatches-elementor' ),
				'selector' => '{{WRAPPER}} .zymarg-sale-badge',
			)
		);

		$this->add_control(
			'sale_badge_color',
			array(
				'label'     => esc_html__( 'Text color', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-price' => '--zymarg-sale-badge-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'sale_badge_bg',
			array(
				'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .zymarg-price' => '--zymarg-sale-badge-bg: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'sale_badge_padding',
			array(
				'label'      => esc_html__( 'Padding', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-sale-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'sale_badge_radius',
			array(
				'label'      => esc_html__( 'Border radius', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 50 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-price' => '--zymarg-sale-badge-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	// ── STYLE TAB — Layout / Spacing / Alignment ─────────────────────────

	private function register_style_layout(): void {

		$this->start_controls_section(
			'section_style_layout',
			array(
				'label' => esc_html__( 'Layout', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'price_gap',
			array(
				'label'      => esc_html__( 'Gap between elements', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-price' => '--zymarg-price-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'text_align',
			array(
				'label'   => esc_html__( 'Alignment', 'woo-swatches-elementor' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => array(
					'flex-start' => array(
						'title' => esc_html__( 'Left', 'woo-swatches-elementor' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => esc_html__( 'Center', 'woo-swatches-elementor' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => esc_html__( 'Right', 'woo-swatches-elementor' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .zymarg-price' => 'justify-content: {{VALUE}};',
				),
				'selectors_dictionary' => array(),
			)
		);

		$this->end_controls_section();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Render
	// ─────────────────────────────────────────────────────────────────────

	protected function render(): void {

		$settings   = $this->get_settings_for_display();
		$show_price = ( $settings['show_price'] ?? 'yes' ) === 'yes';

		if ( ! $show_price ) {
			return;
		}

		$product_id = $this->resolve_product_id( $settings );
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product instanceof \WC_Product ) {
			$this->render_no_product_placeholder();
			return;
		}

		// ── Build price data ──────────────────────────────────────────────
		$price_data = $this->build_price_data( $product );

		// ── Choose template ──────────────────────────────────────────────
		$template = $product->is_type( 'variable' ) ? 'variable.php' : 'simple.php';

		$template_args = array(
			'product'           => $product,
			'price_data'        => $price_data,
			'settings'          => $settings,
			'show_sale_badge'   => 'yes' === ( $settings['show_sale_badge'] ?? 'yes' ),
			'sale_badge_text'   => (string) ( $settings['sale_badge_text'] ?? __( 'Sale', 'woo-swatches-elementor' ) ),
			'regular_position'  => sanitize_key( $settings['regular_price_position'] ?? 'subscript' ),
			'default_style'     => sanitize_key( $settings['default_display_style'] ?? 'lowest' ),
			'from_prefix'       => (string) ( $settings['from_prefix_text'] ?? __( 'From', 'woo-swatches-elementor' ) ),
			'form_id'           => 'wse-form-' . $product->get_id(),
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_template( $template, $template_args );
	}

	/**
	 * Resolves the product ID from the widget settings.
	 *
	 * Falls back to the queried object on a single-product page so the
	 * widget renders correctly without explicit configuration on Elementor
	 * single-product templates.
	 *
	 * @param array<string, mixed> $settings
	 * @return int 0 if no product can be resolved.
	 */
	private function resolve_product_id( array $settings ): int {

		$id = absint( $settings['product_id'] ?? 0 );

		if ( $id > 0 ) {
			return $id;
		}

		// Fallback: queried object on a single-product page
		if ( function_exists( 'is_product' ) && is_product() ) {
			$queried = get_queried_object_id();
			if ( $queried ) {
				return absint( $queried );
			}
		}

		return 0;
	}

	/**
	 * Builds the price data array consumed by the price templates.
	 *
	 * Variable-product logic (option (ii) per ZYMARG product spec):
	 *   • current  = lowest display_price across all available variations.
	 *   • regular  = lowest display_regular_price across all available variations.
	 *   • has_sale = true when the product overall is on sale (any variation).
	 *
	 * Simple-product logic:
	 *   • current  = $product->get_price().
	 *   • regular  = $product->get_regular_price().
	 *   • has_sale = regular > current.
	 *
	 * Both numeric values are also formatted server-side via the same
	 * helper price.js uses, so the initial render and any later JS update
	 * produce visually identical output.
	 *
	 * @param \WC_Product $product
	 * @return array<string, mixed>
	 */
	private function build_price_data( \WC_Product $product ): array {

		$data = array(
			'current'         => 0.0,
			'regular'         => 0.0,
			'range_low'       => 0.0,
			'range_high'      => 0.0,
			'has_sale'        => false,
			'current_html'    => '',
			'regular_html'    => '',
			'range_low_html'  => '',
			'range_high_html' => '',
		);

		if ( $product->is_type( 'variable' ) ) {

			/** @var \WC_Product_Variable $product */
			$variations = $product->get_available_variations();

			if ( ! empty( $variations ) ) {

				$prices   = array();
				$regulars = array();

				foreach ( $variations as $v ) {
					$prices[]   = (float) ( $v['display_price']         ?? 0 );
					$regulars[] = (float) ( $v['display_regular_price'] ?? 0 );
				}

				$data['range_low']  = min( $prices );
				$data['range_high'] = max( $prices );
				$data['current']    = $data['range_low'];
				$data['regular']    = ! empty( $regulars ) ? min( $regulars ) : 0.0;
				$data['has_sale']   = $product->is_on_sale();
			}
		} else {
			// Simple, External, Grouped — fall back to standard WC accessors.
			$data['current']  = (float) $product->get_price();
			$data['regular']  = (float) $product->get_regular_price();
			$data['has_sale'] = $data['regular'] > $data['current'] && $data['current'] > 0;
		}

		// ── Format ───────────────────────────────────────────────────────
		$data['current_html']    = self::format_price( $data['current'] );
		$data['regular_html']    = self::format_price( $data['regular'] );
		$data['range_low_html']  = self::format_price( $data['range_low']  );
		$data['range_high_html'] = self::format_price( $data['range_high'] );

		return $data;
	}

	/**
	 * Server-side currency formatter that mirrors price.js's formatPrice()
	 * exactly, ensuring server-render and client-update produce identical
	 * strings for the same value.
	 *
	 * Pulls separators / decimals / symbol / position straight from
	 * WooCommerce's own option store, so any third-party currency switcher
	 * that hooks `get_woocommerce_currency_symbol` etc. also affects this
	 * widget.
	 *
	 * @param float $amount
	 * @return string
	 */
	public static function format_price( float $amount ): string {

		$decimals      = wc_get_price_decimals();
		$decimal_sep   = wc_get_price_decimal_separator();
		$thousand_sep  = wc_get_price_thousand_separator();
		$symbol        = get_woocommerce_currency_symbol();
		$position      = get_option( 'woocommerce_currency_pos', 'left' );

		$formatted = number_format( $amount, max( 0, (int) $decimals ), $decimal_sep, $thousand_sep );

		switch ( $position ) {
			case 'right':       return $formatted . $symbol;
			case 'left_space':  return $symbol . ' ' . $formatted;
			case 'right_space': return $formatted . ' ' . $symbol;
			case 'left':
			default:            return $symbol . $formatted;
		}
	}

	/**
	 * Renders the "no product selected" placeholder in the Elementor
	 * editor. On the frontend, prints nothing (silent failure rather than
	 * a visible error).
	 */
	private function render_no_product_placeholder(): void {

		$is_editor = class_exists( '\Elementor\Plugin' )
			&& method_exists( \Elementor\Plugin::$instance->editor, 'is_edit_mode' )
			&& \Elementor\Plugin::$instance->editor->is_edit_mode();

		if ( ! $is_editor ) {
			return;
		}

		?>
		<div class="zymarg-price zymarg-price--no-product">
			<em>
				<?php esc_html_e(
					'ZYMARG Price — set a Product ID (or use Dynamic Tags → Post ID on a single-product template).',
					'woo-swatches-elementor'
				); ?>
			</em>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────
	// Template system — Gap 4 + Gap 46 (mirrors WSE_Swatch_Renderer pattern)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Locates a price template, supporting child-theme overrides at
	 * {child-theme}/woo-swatches-elementor/price/{file}.
	 *
	 * @param  string $template_name e.g. 'simple.php', 'parts/price-current.php'.
	 * @return string                Absolute path or empty string if not found.
	 */
	public static function locate_template( string $template_name ): string {

		// Sanitise — allow path segments only inside parts/ subdir.
		$template_name = ltrim( $template_name, '/\\' );
		if ( '' === $template_name || ! str_ends_with( $template_name, '.php' ) ) {
			return '';
		}
		// Block traversal explicitly.
		if ( str_contains( $template_name, '..' ) ) {
			return '';
		}

		if ( isset( self::$template_path_cache[ $template_name ] ) ) {
			return self::$template_path_cache[ $template_name ];
		}

		$sub_dir   = 'woo-swatches-elementor/price/';
		$locations = array(
			get_stylesheet_directory() . '/' . $sub_dir . $template_name,
			get_template_directory()   . '/' . $sub_dir . $template_name,
			WSE_PATH . 'templates/price/' . $template_name,
		);

		$found = '';
		foreach ( $locations as $path ) {
			if ( file_exists( $path ) ) {
				$found = $path;
				break;
			}
		}

		self::$template_path_cache[ $template_name ] = $found;
		return $found;
	}

	/**
	 * Locates, validates, and includes a price template, returning its
	 * output. Gap 46 — LFI guard via realpath() allow-list.
	 *
	 * @param  string               $template_name
	 * @param  array<string, mixed> $args
	 * @return string
	 */
	public function render_template( string $template_name, array $args = array() ): string {

		$path = self::locate_template( $template_name );
		if ( '' === $path ) {
			return '';
		}

		if ( ! self::is_safe_template_path( $path ) ) {
			return '';
		}

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $args, EXTR_SKIP );
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * Static helper used by parent templates (simple.php / variable.php)
	 * to include a price part (price-current.php / price-was.php) without
	 * needing access to a widget instance.
	 *
	 * @param string               $part_name e.g. 'price-current.php'
	 * @param array<string, mixed> $args
	 */
	public static function include_part( string $part_name, array $args = array() ): void {

		$path = self::locate_template( 'parts/' . $part_name );
		if ( '' === $path ) {
			return;
		}

		if ( ! self::is_safe_template_path( $path ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $args, EXTR_SKIP );
		include $path;
	}

	/**
	 * Validates that a template path sits within an allowed directory.
	 * Mirrors WSE_Swatch_Renderer's identical guard.
	 *
	 * @param  string $path Absolute path to validate.
	 * @return bool
	 */
	private static function is_safe_template_path( string $path ): bool {

		$real_path = realpath( $path );
		if ( false === $real_path ) {
			return false;
		}

		$allowed = array(
			realpath( WSE_PATH . 'templates' ),
			realpath( get_stylesheet_directory() . '/woo-swatches-elementor' ),
			realpath( get_template_directory()   . '/woo-swatches-elementor' ),
		);

		foreach ( $allowed as $dir ) {
			if ( $dir && str_starts_with( $real_path, $dir . DIRECTORY_SEPARATOR ) ) {
				return true;
			}
		}

		return false;
	}
}
