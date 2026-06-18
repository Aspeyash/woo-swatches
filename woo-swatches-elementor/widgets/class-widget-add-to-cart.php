<?php
/**
 * Elementor Widget 2 — ZYMARG Quantity Stepper + Add to Cart
 *
 * Renders a WooCommerce add-to-cart form as a fully configurable Elementor
 * widget. Supports all four WooCommerce product types by routing to dedicated
 * PHP templates in templates/add-to-cart/.
 *
 * Variable product cross-widget sync:
 *   When used alongside Widget 1 (ZYMARG Variation Swatches), add-to-cart.js
 *   listens for the wse:swatchSelected event and mirrors each swatch selection
 *   into this widget's hidden <select> elements, which wc-add-to-cart-
 *   variation.js then uses to find the variation, update price/stock, and
 *   enable the button.
 *
 * Widget identity:
 *   Slug   : zymarg-add-to-cart
 *   Title  : ZYMARG Add to Cart
 *   Author : ZYMARG
 *   Version: 1.0.0
 *
 * Gap 20  — content_template() live editor preview
 * Gap 21  — get_script_depends() / get_style_depends()
 * Gap 22  — Dynamic tags on product_id control
 * Gap 26  — Custom 'woo-swatches-elementor' category
 * Gap 31  — has_widget_inner_wrapper() + is_dynamic_content()
 * Gap 34  — PHP 8.2+ explicit property declarations
 * Gap 37  — Product type routing (simple / variable / grouped / external)
 * Gap 46  — LFI guard on template includes
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Widget_Add_To_Cart extends \Elementor\Widget_Base {

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected string $widget_slug = 'zymarg-add-to-cart';

	/** @var string[] Allowed product types that have a template file. */
	private array $supported_types = array( 'simple', 'variable', 'grouped', 'external' );

	// ─────────────────────────────────────────────────────────────────────
	// Widget identity
	// ─────────────────────────────────────────────────────────────────────

	public function get_name(): string {
		return $this->widget_slug;
	}

	public function get_title(): string {
		return esc_html__( 'ZYMARG Add to Cart', 'woo-swatches-elementor' );
	}

	public function get_icon(): string {
		return 'eicon-woocommerce-add-to-cart';
	}

	/** Gap 26 — custom widget category */
	public function get_categories(): array {
		return array( 'woo-swatches-elementor' );
	}

	public function get_keywords(): array {
		return array( 'zymarg', 'woo', 'woocommerce', 'add to cart', 'quantity', 'stepper', 'button' );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 21 — Asset dependencies
	// ─────────────────────────────────────────────────────────────────────

	public function get_script_depends(): array {
		return array( WSE_Assets::handle( 'add_to_cart_js' ) );
	}

	public function get_style_depends(): array {
		return array( WSE_Assets::handle( 'add_to_cart_css' ) );
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
	// Controls
	// ─────────────────────────────────────────────────────────────────────

	protected function register_controls(): void {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	// ── Content ──────────────────────────────────────────────────────────

	private function register_content_controls(): void {

		// ── Product section ───────────────────────────────────────────────
		$this->start_controls_section(
			'section_product',
			array(
				'label' => esc_html__( 'Product', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		/**
		 * Gap 22 — product_id supports dynamic tags (e.g. Elementor's
		 * Post ID tag so this widget auto-resolves on single product pages).
		 */
		$this->add_control(
			'product_id',
			array(
				'label'          => esc_html__( 'Product', 'woo-swatches-elementor' ),
				'type'           => \Elementor\Controls_Manager::NUMBER,
				'min'            => 1,
				'placeholder'    => esc_html__( 'Enter product ID or leave empty to use current post', 'woo-swatches-elementor' ),
				'dynamic'        => array( 'active' => true ),
				'description'    => esc_html__( 'Leave empty on WooCommerce single product pages — the widget will use the current product automatically.', 'woo-swatches-elementor' ),
			)
		);

		$this->end_controls_section();

		// ── v1.1.0 — Behavior section (Sticky toggles + Presenter Mode) ──
		// v1.1.1: sticky toggles always available so single-widget setups
		// (one canonical Add to Cart, sticky-on-mobile) just work. Presenter
		// Mode lives further down for advanced multi-widget setups.
		$this->start_controls_section(
			'section_behavior',
			array(
				'label' => esc_html__( 'Behavior', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'sticky_heading',
			array(
				'label' => esc_html__( 'Sticky Behavior (per device)', 'woo-swatches-elementor' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'sticky_intro',
			array(
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => '<div style="font-size:11px;color:#475569;margin-bottom:6px;">' . esc_html__( 'When enabled for a device, this widget pins to the bottom of the viewport on that breakpoint. The Add to Cart button stays functional.', 'woo-swatches-elementor' ) . '</div>',
			)
		);

		$this->add_control(
			'sticky_desktop',
			array(
				'label'        => esc_html__( 'Sticky on Desktop (≥ 1025px)', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On',  'woo-swatches-elementor' ),
				'label_off'    => esc_html__( 'Off', 'woo-swatches-elementor' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'sticky_tablet',
			array(
				'label'        => esc_html__( 'Sticky on Tablet (768–1024px)', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On',  'woo-swatches-elementor' ),
				'label_off'    => esc_html__( 'Off', 'woo-swatches-elementor' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'sticky_mobile',
			array(
				'label'        => esc_html__( 'Sticky on Mobile (≤ 767px)', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On',  'woo-swatches-elementor' ),
				'label_off'    => esc_html__( 'Off', 'woo-swatches-elementor' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		// v1.1.3 — Per-widget "View Cart" link toggle.
		$this->add_control(
			'view_cart_link_heading',
			array(
				'label'     => esc_html__( '"View Cart" Link', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'show_view_cart_link',
			array(
				'label'       => esc_html__( 'Show "View Cart" Link', 'woo-swatches-elementor' ),
				'description' => esc_html__( 'Controls the "View cart" link that appears next to the Add to Cart button after a successful add, plus the link in the bottom-right toast and the WooCommerce mini-cart fragment.', 'woo-swatches-elementor' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array(
					'inherit' => esc_html__( 'Inherit from settings', 'woo-swatches-elementor' ),
					'yes'     => esc_html__( 'Yes — show the link',    'woo-swatches-elementor' ),
					'no'      => esc_html__( 'No — hide everywhere',   'woo-swatches-elementor' ),
				),
				'default'     => 'inherit',
			)
		);

		$this->add_control(
			'advanced_heading',
			array(
				'label'     => esc_html__( 'Advanced', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'presenter_mode',
			array(
				'label'        => esc_html__( 'Presenter Mode', 'woo-swatches-elementor' ),
				'description'  => esc_html__( 'Only enable when this is a SECONDARY Add to Cart widget on a page that already has a primary one (e.g. main button + a separate sticky bar widget). Leave OFF for single-widget setups — turn the Sticky toggles above on instead.', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On',  'woo-swatches-elementor' ),
				'label_off'    => esc_html__( 'Off', 'woo-swatches-elementor' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'presenter_warning',
			array(
				'type'      => \Elementor\Controls_Manager::RAW_HTML,
				'raw'       => '<div style="background:#fff3cd;border:1px solid #ffe69c;padding:10px 12px;border-radius:4px;font-size:11px;color:#664d03;line-height:1.45;">' .
					'<strong>' . esc_html__( 'Heads up:', 'woo-swatches-elementor' ) . '</strong> ' .
					esc_html__( 'Presenter Mode requires another Add to Cart widget (with Presenter Mode = Off) elsewhere on the same page to act as the canonical form. If only this single widget exists, the plugin will auto-synthesize a hidden form so the cart still works — but for most use cases turn this OFF and use the Sticky toggles above instead.', 'woo-swatches-elementor' ) .
					'</div>',
				'condition' => array( 'presenter_mode' => 'yes' ),
			)
		);

		$this->end_controls_section();

		// ── Quantity section ──────────────────────────────────────────────
		$this->start_controls_section(
			'section_quantity',
			array(
				'label' => esc_html__( 'Quantity', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_quantity',
			array(
				'label'        => esc_html__( 'Show Quantity Input', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'woo-swatches-elementor' ),
				'label_off'    => esc_html__( 'Hide', 'woo-swatches-elementor' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'default_quantity',
			array(
				'label'     => esc_html__( 'Default Quantity', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 1,
				'step'      => 1,
				'default'   => 1,
				'condition' => array( 'show_quantity' => 'yes' ),
			)
		);

		// v1.2.0 — Quantity stepper buttons.
		$this->add_control(
			'show_qty_stepper_buttons',
			array(
				'label'        => esc_html__( 'Show +/- Stepper Buttons', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'woo-swatches-elementor' ),
				'label_off'    => esc_html__( 'No',  'woo-swatches-elementor' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => esc_html__(
					'Wrap the quantity field with [-] and [+] buttons for touch-friendly increment/decrement. Manual numeric entry stays available.',
					'woo-swatches-elementor'
				),
				'condition'    => array( 'show_quantity' => 'yes' ),
			)
		);

		$this->add_control(
			'decrease_icon',
			array(
				'label'       => esc_html__( 'Decrease icon', 'woo-swatches-elementor' ),
				'type'        => \Elementor\Controls_Manager::ICONS,
				'default'     => array(
					'value'   => 'eicon-minus',
					'library' => 'eicons',
				),
				'recommended' => array(
					'eicons'         => array( 'minus', 'minus-circle', 'arrow-left', 'chevron-left' ),
					'fa-solid'       => array( 'minus', 'minus-circle' ),
				),
				'condition'   => array(
					'show_quantity'             => 'yes',
					'show_qty_stepper_buttons'  => 'yes',
				),
			)
		);

		$this->add_control(
			'increase_icon',
			array(
				'label'       => esc_html__( 'Increase icon', 'woo-swatches-elementor' ),
				'type'        => \Elementor\Controls_Manager::ICONS,
				'default'     => array(
					'value'   => 'eicon-plus',
					'library' => 'eicons',
				),
				'recommended' => array(
					'eicons'         => array( 'plus', 'plus-circle', 'arrow-right', 'chevron-right' ),
					'fa-solid'       => array( 'plus', 'plus-circle' ),
				),
				'condition'   => array(
					'show_quantity'             => 'yes',
					'show_qty_stepper_buttons'  => 'yes',
				),
			)
		);

		$this->end_controls_section();

		// ── Button section ────────────────────────────────────────────────
		$this->start_controls_section(
			'section_button',
			array(
				'label' => esc_html__( 'Button', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => esc_html__( 'Button Text', 'woo-swatches-elementor' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Add to Cart (uses product default if empty)', 'woo-swatches-elementor' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'button_full_width',
			array(
				'label'        => esc_html__( 'Full Width Button', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->end_controls_section();
	}

	// ── Style ─────────────────────────────────────────────────────────────

	private function register_style_controls(): void {

		// ── Button style ──────────────────────────────────────────────────
		$this->start_controls_section(
			'section_style_button',
			array(
				'label' => esc_html__( 'Button', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .wse-atc-button',
			)
		);

		$this->start_controls_tabs( 'tabs_button_style' );

		// Normal state
		$this->start_controls_tab(
			'tab_button_normal',
			array( 'label' => esc_html__( 'Normal', 'woo-swatches-elementor' ) )
		);
		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-atc-button' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-atc-button' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		// Hover state
		$this->start_controls_tab(
			'tab_button_hover',
			array( 'label' => esc_html__( 'Hover', 'woo-swatches-elementor' ) )
		);
		$this->add_control(
			'button_hover_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-atc-button:hover' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_hover_background_color',
			array(
				'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-atc-button:hover' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border',
				'selector' => '{{WRAPPER}} .wse-atc-button',
			)
		);

		$this->add_responsive_control(
			'button_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-atc-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => esc_html__( 'Padding', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-atc-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// ── Quantity input style ───────────────────────────────────────────
		$this->start_controls_section(
			'section_style_quantity',
			array(
				'label'     => esc_html__( 'Quantity Input', 'woo-swatches-elementor' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_quantity' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'quantity_width',
			array(
				'label'      => esc_html__( 'Width', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 40, 'max' => 160 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .wse-qty-wrap .qty' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'quantity_border',
				'selector' => '{{WRAPPER}} .wse-qty-wrap .qty',
			)
		);

		$this->add_responsive_control(
			'quantity_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-qty-wrap .qty' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// ── v1.2.0 — Quantity Stepper Buttons style ───────────────────────
		$this->start_controls_section(
			'section_style_qty_stepper',
			array(
				'label'     => esc_html__( 'Quantity Stepper Buttons', 'woo-swatches-elementor' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_quantity'             => 'yes',
					'show_qty_stepper_buttons'  => 'yes',
				),
			)
		);

		// ── Sizing ───────────────────────────────────────────────────────
		$this->add_responsive_control(
			'qty_stepper_total_width',
			array(
				'label'      => esc_html__( 'Total stepper width', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 80,  'max' => 320 ),
					'%'  => array( 'min' => 20,  'max' => 100 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .wse-qty-stepper' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'qty_stepper_btn_size',
			array(
				'label'      => esc_html__( 'Button size', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 64 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 36 ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-qty-stepper' => '--wse-qty-btn-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'qty_stepper_icon_size',
			array(
				'label'      => esc_html__( 'Icon size', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 32 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 14 ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-qty-stepper' => '--wse-qty-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'qty_stepper_gap',
			array(
				'label'      => esc_html__( 'Gap between elements', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 0 ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-qty-stepper' => '--wse-qty-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		// ── Tabs: Normal / Hover / Disabled ──────────────────────────────
		$this->start_controls_tabs( 'tabs_qty_stepper_btn_state' );

		// Normal
		$this->start_controls_tab(
			'tab_qty_btn_normal',
			array( 'label' => esc_html__( 'Normal', 'woo-swatches-elementor' ) )
		);

		$this->add_control(
			'qty_btn_color_normal',
			array(
				'label'     => esc_html__( 'Icon / text color', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-qty-btn' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'qty_btn_bg_normal',
			array(
				'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-qty-btn' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		// Hover
		$this->start_controls_tab(
			'tab_qty_btn_hover',
			array( 'label' => esc_html__( 'Hover', 'woo-swatches-elementor' ) )
		);

		$this->add_control(
			'qty_btn_color_hover',
			array(
				'label'     => esc_html__( 'Icon / text color', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-qty-btn:hover:not(:disabled)' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'qty_btn_bg_hover',
			array(
				'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-qty-btn:hover:not(:disabled)' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		// Disabled
		$this->start_controls_tab(
			'tab_qty_btn_disabled',
			array( 'label' => esc_html__( 'Disabled', 'woo-swatches-elementor' ) )
		);

		$this->add_control(
			'qty_btn_disabled_opacity',
			array(
				'label'      => esc_html__( 'Opacity', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 0.4 ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-qty-btn:disabled' => 'opacity: {{SIZE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		// ── Border + radius ──────────────────────────────────────────────
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'qty_btn_border',
				'selector' => '{{WRAPPER}} .wse-qty-btn',
			)
		);

		$this->add_responsive_control(
			'qty_btn_border_radius',
			array(
				'label'      => esc_html__( 'Border radius', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-qty-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Render (PHP → frontend HTML)
	//
	// v1.1.0 (B3) — For variable products, claims canonical or presenter
	// status from WSE_Form_Registry. Canonical instances render the full
	// variable.php template (single <form id="wse-form-{P}"> with all
	// hidden selects + variation JSON). Presenter instances render
	// variable-presenter.php (button + quantity only, attached via the
	// HTML5 form= attribute).
	//
	// v1.1.0 (Feature B) — For presenter mode, applies sticky CSS classes
	// based on the per-device toggles (sticky_desktop/tablet/mobile).
	// ─────────────────────────────────────────────────────────────────────

	protected function render(): void {

		$settings = $this->get_settings_for_display();

		// ── Resolve product ───────────────────────────────────────────────
		$product_id = ! empty( $settings['product_id'] )
			? absint( $settings['product_id'] )
			: get_the_ID();

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				?>
				<div class="wse-editor-placeholder">
					<p><?php esc_html_e( 'Select a WooCommerce product to display the Add to Cart widget.', 'woo-swatches-elementor' ); ?></p>
				</div>
				<?php
			}
			return;
		}

		// ── Gap 37 — Route to appropriate template ────────────────────────
		$type = $product->get_type();
		if ( ! in_array( $type, $this->supported_types, true ) ) {
			$type = 'simple';
		}

		// ── v1.1.0 (B3) — Canonical/presenter coordination ───────────────
		// Logic:
		//   • presenter_mode = yes   → always presenter, no canonical claim
		//                              (button targets another instance's form
		//                              via the HTML5 form= attribute)
		//   • presenter_mode = no    → try to claim canonical. If another
		//                              Widget 2 already claimed for this
		//                              product, graceful-degrade to presenter.
		$registry           = WSE_Form_Registry::instance();
		$widget_id          = (string) $this->get_id();
		$presenter_setting  = ( $settings['presenter_mode'] ?? 'no' ) === 'yes';
		$is_presenter       = false;
		$canonical_form_id  = $registry->get_form_id( (int) $product_id );

		if ( 'variable' === $type ) {
			if ( $presenter_setting ) {
				$is_presenter = true;
			} else {
				$status       = $registry->claim_canonical( (int) $product_id, $widget_id );
				$is_presenter = ( WSE_Form_Registry::STATUS_PRESENTER === $status );
			}
		}

		$template_key = ( 'variable' === $type && $is_presenter ) ? 'variable-presenter' : $type;
		$template     = $this->locate_template( $template_key );

		if ( ! $template ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				printf(
					'<p class="wse-editor-placeholder">%s</p>',
					esc_html__( 'Template not found.', 'woo-swatches-elementor' )
				);
			}
			return;
		}

		// ── Wrapper class assembly ────────────────────────────────────────
		$wrapper_class = 'wse-widget-add-to-cart';
		if ( 'yes' === ( $settings['button_full_width'] ?? 'no' ) ) {
			$wrapper_class .= ' wse-atc-full-width';
		}
		if ( $is_presenter ) {
			$wrapper_class .= ' wse-presenter';
		}

		// Feature B (v1.1.0) + v1.1.1 — sticky classes apply to BOTH presenter
		// AND canonical wrappers. v1.1.0 originally scoped these to presenter
		// only; v1.1.1 lets a single-widget canonical setup go sticky directly.
		if ( 'yes' === ( $settings['sticky_desktop'] ?? 'no' ) ) {
			$wrapper_class .= ' wse-sticky-desktop';
		}
		if ( 'yes' === ( $settings['sticky_tablet']  ?? 'no' ) ) {
			$wrapper_class .= ' wse-sticky-tablet';
		}
		if ( 'yes' === ( $settings['sticky_mobile']  ?? 'no' ) ) {
			$wrapper_class .= ' wse-sticky-mobile';
		}

		$this->add_render_attribute( 'wrapper', array(
			'class'               => $wrapper_class,
			'data-product-id'     => absint( $product_id ),
			'data-form-id'        => $canonical_form_id,
			// v1.1.3 — per-widget "View Cart" link control. Read by JS in
			// shouldShowViewCart() to decide whether to strip wc-forward
			// links from fragments, the toast, and pre-existing notices.
			'data-show-view-cart' => sanitize_key( (string) ( $settings['show_view_cart_link'] ?? 'inherit' ) ),
		) );

		// Expose state to the template via in-scope variables.
		$presenter_mode = $is_presenter; // template var alias
		$form_id        = $canonical_form_id;
		?>
		<div <?php echo $this->get_render_attribute_string( 'wrapper' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php
			// Gap 46 — path already validated in locate_template(); safe to include.
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			include $template;
			?>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 46 — LFI-safe template resolution
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Resolves the full filesystem path for an add-to-cart template.
	 *
	 * Resolution order (mirrors WSE_Swatch_Renderer — Gap 4):
	 *   1. Child  theme: {child-theme}/woo-swatches-elementor/add-to-cart/{type}.php
	 *   2. Parent theme: {parent-theme}/woo-swatches-elementor/add-to-cart/{type}.php
	 *   3. Plugin:       {plugin}/templates/add-to-cart/{type}.php
	 *
	 * The resolved path is validated against allowed base directories so
	 * user input (product type) can never traverse outside these directories.
	 *
	 * @param  string $type Product type slug: 'simple'|'variable'|'grouped'|'external'.
	 * @return string|false Absolute path to the template file, or false on failure.
	 */
	private function locate_template( string $type ): string|false {

		$relative = 'woo-swatches-elementor/add-to-cart/' . $type . '.php';

		// ── 1 & 2. Theme (child → parent) ────────────────────────────────
		$theme_file = locate_template( $relative );
		if ( $theme_file ) {
			$real = realpath( $theme_file );
			foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $base ) {
				$real_base = realpath( $base );
				if ( $real && $real_base && str_starts_with( $real, $real_base ) ) {
					return $theme_file;
				}
			}
		}

		// ── 3. Plugin templates directory ────────────────────────────────
		$plugin_file = WSE_PATH . 'templates/add-to-cart/' . $type . '.php';
		$real        = realpath( $plugin_file );
		$allowed     = realpath( WSE_PATH . 'templates/add-to-cart' );

		if ( $real && $allowed && str_starts_with( $real, $allowed ) ) {
			return $plugin_file;
		}

		return false;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 20 — Elementor live editor content template
	// ─────────────────────────────────────────────────────────────────────

	protected function content_template(): void {
		?>
		<#
		var showQty   = settings.show_quantity === 'yes';
		var btnText   = settings.button_text || '<?php echo esc_js( __( 'Add to cart', 'woo-swatches-elementor' ) ); ?>';
		var fullW     = settings.button_full_width === 'yes';
		var qty       = settings.default_quantity || 1;
		var presenter = settings.presenter_mode === 'yes';
		var stickyD   = settings.sticky_desktop === 'yes';
		var stickyT   = settings.sticky_tablet  === 'yes';
		var stickyM   = settings.sticky_mobile  === 'yes';
		var classes   = 'wse-widget-add-to-cart wse-editor-preview';
		if ( fullW )     { classes += ' wse-atc-full-width'; }
		if ( presenter ) { classes += ' wse-presenter'; }
		if ( presenter && stickyD ) { classes += ' wse-sticky-desktop'; }
		if ( presenter && stickyT ) { classes += ' wse-sticky-tablet'; }
		if ( presenter && stickyM ) { classes += ' wse-sticky-mobile'; }
		#>
		<div class="{{ classes }}">
			<# if ( presenter ) { #>
			<div class="wse-editor-presenter-note" style="font-size:11px;color:#534152;background:#eaedff;padding:6px 10px;border-radius:4px;margin-bottom:8px">
				<?php esc_html_e( 'Presenter Mode — shares form with primary Add-to-Cart widget. Sticky:', 'woo-swatches-elementor' ); ?>
				<# if ( !stickyD && !stickyT && !stickyM ) { #><?php esc_html_e( 'Off on all devices', 'woo-swatches-elementor' ); ?><# } #>
				<# if ( stickyD ) { #>Desktop <# } #>
				<# if ( stickyT ) { #>Tablet <# } #>
				<# if ( stickyM ) { #>Mobile<# } #>
			</div>
			<# } #>
			<div class="wse-qty-atc-row">
				<# if ( showQty ) { #>
				<div class="wse-qty-wrap">
					<input type="number"
					       class="qty input-text"
					       value="{{ qty }}"
					       min="1"
					       step="1"
					       style="width:64px;padding:8px;border:1px solid #d0d0d8;border-radius:4px;text-align:center;"
					/>
				</div>
				<# } #>
				<button type="button"
				        class="wse-atc-button button alt single_add_to_cart_button"
				        style="{{ fullW ? 'width:100%;' : '' }}">
					{{{ btnText }}}
				</button>
			</div>
		</div>
		<?php
	}
}
