<?php
/**
 * Elementor Widget 1 — ZYMARG Variation Swatches
 *
 * Renders WooCommerce product variation swatches as a fully configurable
 * Elementor widget. All swatch rendering delegates to WSE_Swatch_Renderer
 * via the woocommerce_dropdown_variation_attribute_options_html filter —
 * no duplicate logic.
 *
 * Widget identity:
 *   Slug   : zymarg-variation-swatches
 *   Title  : ZYMARG Variation Swatches
 *   Author : ZYMARG
 *   Version: 1.0.0
 *
 * Gap 20  — content_template() live editor preview
 * Gap 21  — get_script_depends() / get_style_depends()
 * Gap 22  — Dynamic tags on product_id control
 * Gap 26  — Custom 'woo-swatches-elementor' category
 * Gap 31  — has_widget_inner_wrapper() + is_dynamic_content()
 * Gap 34  — PHP 8.2+ explicit property declarations
 * Gap 41  — add_responsive_control() for all dimensional controls
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Widget_Swatches extends \Elementor\Widget_Base {

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected string $widget_slug = 'zymarg-variation-swatches';

	// ─────────────────────────────────────────────────────────────────────
	// Widget identity
	// ─────────────────────────────────────────────────────────────────────

	public function get_name(): string {
		return $this->widget_slug;
	}

	public function get_title(): string {
		return esc_html__( 'ZYMARG Variation Swatches', 'woo-swatches-elementor' );
	}

	public function get_icon(): string {
		return 'eicon-product-breadcrumbs';
	}

	/** Gap 26 — custom widget category */
	public function get_categories(): array {
		return array( 'woo-swatches-elementor' );
	}

	public function get_keywords(): array {
		return array( 'zymarg', 'woo', 'woocommerce', 'swatches', 'variation', 'color', 'size' );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 21 — Asset dependencies (resolved via WSE_Assets::handle())
	// Elementor only loads these handles when this widget is on the page.
	// ─────────────────────────────────────────────────────────────────────

	public function get_script_depends(): array {
		return array(
			WSE_Assets::handle( 'swatches_js' ),
			WSE_Assets::handle( 'form_field_dependency_js' ),
		);
	}

	public function get_style_depends(): array {
		return array( WSE_Assets::handle( 'swatches_css' ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 31 — Elementor DOM optimisation + dynamic content flag
	// ─────────────────────────────────────────────────────────────────────

	/** Removes the extra .elementor-widget-container wrapper div */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * Prevents Elementor from caching this widget's output.
	 * Critical: product prices, stock status, and variation state must
	 * always be fresh — never served from a stale render cache.
	 *
	 * v1.1.0 (B11) — public for visibility consistency with Widget 2 and
	 * for compatibility with Elementor 3.20+ direct method invocation.
	 */
	public function is_dynamic_content(): bool {
		return true;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Controls registration
	// ─────────────────────────────────────────────────────────────────────

	protected function register_controls(): void {
		$this->register_content_controls();
		$this->register_style_swatches();
		$this->register_style_active_state();
		$this->register_style_hover();
		$this->register_style_oos();
		$this->register_style_tooltip();
		$this->register_style_typography();
		$this->register_style_label_swatch();
	}

	// ── CONTENT TAB ──────────────────────────────────────────────────────

	private function register_content_controls(): void {

		// ── Product ───────────────────────────────────────────────────────
		$this->start_controls_section( 'section_product', array(
			'label' => esc_html__( 'Product', 'woo-swatches-elementor' ),
		) );

		// Gap 22 — dynamic tag support
		$this->add_control( 'product_id', array(
			'label'       => esc_html__( 'Product', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'dynamic'     => array( 'active' => true ),
			'description' => esc_html__( 'Leave empty to use the current product (theme builder templates).', 'woo-swatches-elementor' ),
		) );

		$this->end_controls_section();

		// ── Swatches display ──────────────────────────────────────────────
		$this->start_controls_section( 'section_swatches', array(
			'label' => esc_html__( 'Swatches', 'woo-swatches-elementor' ),
		) );

		$this->add_control( 'show_label', array(
			'label'        => esc_html__( 'Show Attribute Label', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'woo-swatches-elementor' ),
			'label_off'    => esc_html__( 'No',  'woo-swatches-elementor' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'label_position', array(
			'label'     => esc_html__( 'Label Position', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'options'   => array(
				'above'  => esc_html__( 'Above swatches',  'woo-swatches-elementor' ),
				'beside' => esc_html__( 'Beside swatches', 'woo-swatches-elementor' ),
				// v1.3.5 (F3) — Third option to hide the entire label row
				// (attribute name + selected value) without toggling the
				// global show_label switcher.
				'hidden' => esc_html__( 'Hidden',          'woo-swatches-elementor' ),
			),
			'default'   => 'above',
			'condition' => array( 'show_label' => 'yes' ),
		) );

		$this->add_control( 'show_selected_value', array(
			'label'        => esc_html__( 'Show Selected Value', 'woo-swatches-elementor' ),
			'description'  => esc_html__( 'Displays the selected option name next to the label e.g. "Color: Red".', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'woo-swatches-elementor' ),
			'label_off'    => esc_html__( 'No',  'woo-swatches-elementor' ),
			'return_value' => 'yes',
			'default'      => 'yes',
			'condition'    => array( 'show_label' => 'yes' ),
		) );

		// v1.2.1 (F3) — Per-type attribute-name visibility.
		// By default, the attribute name row above swatches is shown
		// only for color-type swatches and hidden for image / label /
		// button / dropdown types (per ZYMARG product spec). Power users
		// can flip this toggle on to keep the label visible for ALL
		// swatch types.
		$this->add_control( 'show_label_non_color', array(
			'label'        => esc_html__( 'Show Label for non-color types', 'woo-swatches-elementor' ),
			'description'  => esc_html__( 'By default the attribute name above swatches is shown only for Color types. Turn ON to also show it for Image / Label / Button / Dropdown.', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'woo-swatches-elementor' ),
			'label_off'    => esc_html__( 'No',  'woo-swatches-elementor' ),
			'return_value' => 'yes',
			'default'      => 'no',
			'condition'    => array( 'show_label' => 'yes' ),
		) );

		// v1.2.1 (F4) — Image-swatch variation label position.
		// Always rendered into the DOM; position is driven by the parent
		// .wse-attr-block class wse-image-label-pos-{value}. Only applies
		// when the attribute's swatch type is "image".
		$this->add_control( 'image_label_position', array(
			'label'   => esc_html__( 'Image Swatch Label Position', 'woo-swatches-elementor' ),
			'description' => esc_html__( 'Where to display the variation name (e.g. "black", "blue") relative to each image swatch tile.', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array(
				'below'  => esc_html__( 'Below the image (default)',     'woo-swatches-elementor' ),
				'above'  => esc_html__( 'Above the image',               'woo-swatches-elementor' ),
				'hover'  => esc_html__( 'On hover only',                 'woo-swatches-elementor' ),
				'hidden' => esc_html__( 'Hidden',                        'woo-swatches-elementor' ),
			),
			'default' => 'below',
		) );

		// v1.3.5 (F1) — Image swatch horizontal scroll feature.
		// 9 per-device toggles in 3 groups (Enable / Show Scrollbar /
		// Auto-scroll Active Into View), each with desktop / tablet /
		// mobile variants. Defaults match the senior-dev-recommended
		// Amazon/Nike-style behaviour:
		//   Enable Hscroll:        D=OFF  T=ON   M=ON
		//   Show Scrollbar:        D=OFF  T=OFF  M=OFF
		//   Auto-scroll into view: D=OFF  T=OFF  M=OFF
		// Driven by prefix_class on the widget wrapper; descendant CSS
		// selectors apply the scroll layout at the matching breakpoint.
		$this->add_control( 'hscroll_heading', array(
			'label'     => esc_html__( 'Image Swatches — Horizontal Scroll', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		// ── Group A: Enable Horizontal Scroll ─────────────────────────────
		$this->add_control( 'image_hscroll_desktop', array(
			'label'        => esc_html__( 'Enable horizontal scroll — Desktop', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'On',  'woo-swatches-elementor' ),
			'label_off'    => esc_html__( 'Off', 'woo-swatches-elementor' ),
			'return_value' => 'yes',
			'default'      => 'no',
			'prefix_class' => 'wse-img-hscroll-d-',
		) );
		$this->add_control( 'image_hscroll_tablet', array(
			'label'        => esc_html__( 'Enable horizontal scroll — Tablet (≤1024px)', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'prefix_class' => 'wse-img-hscroll-t-',
		) );
		$this->add_control( 'image_hscroll_mobile', array(
			'label'        => esc_html__( 'Enable horizontal scroll — Mobile (≤768px)', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'prefix_class' => 'wse-img-hscroll-m-',
		) );

		// ── Group B: Show Scrollbar ───────────────────────────────────────
		$this->add_control( 'image_hscroll_show_bar_desktop', array(
			'label'        => esc_html__( 'Show scrollbar — Desktop', 'woo-swatches-elementor' ),
			'description'  => esc_html__( 'Hide for a cleaner Amazon/Nike-style swipe; swipe still works either way.', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
			'prefix_class' => 'wse-img-hscroll-bar-d-',
			'condition'    => array( 'image_hscroll_desktop' => 'yes' ),
		) );
		$this->add_control( 'image_hscroll_show_bar_tablet', array(
			'label'        => esc_html__( 'Show scrollbar — Tablet', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
			'prefix_class' => 'wse-img-hscroll-bar-t-',
			'condition'    => array( 'image_hscroll_tablet' => 'yes' ),
		) );
		$this->add_control( 'image_hscroll_show_bar_mobile', array(
			'label'        => esc_html__( 'Show scrollbar — Mobile', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
			'prefix_class' => 'wse-img-hscroll-bar-m-',
			'condition'    => array( 'image_hscroll_mobile' => 'yes' ),
		) );

		// ── Group C: Auto-scroll Active Swatch Into View ──────────────────
		// Per ZYMARG product owner spec — defaults OFF on all devices so
		// the user has to opt-in. JS handler lives in swatches.js and
		// reads the per-breakpoint class to decide whether to scroll the
		// active swatch into view on selection.
		$this->add_control( 'image_hscroll_auto_desktop', array(
			'label'        => esc_html__( 'Auto-scroll active swatch into view — Desktop', 'woo-swatches-elementor' ),
			'description'  => esc_html__( 'When customer picks a swatch off-screen, smooth-scroll the swatch into the visible area.', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
			'prefix_class' => 'wse-img-hscroll-auto-d-',
			'condition'    => array( 'image_hscroll_desktop' => 'yes' ),
		) );
		$this->add_control( 'image_hscroll_auto_tablet', array(
			'label'        => esc_html__( 'Auto-scroll active swatch into view — Tablet', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
			'prefix_class' => 'wse-img-hscroll-auto-t-',
			'condition'    => array( 'image_hscroll_tablet' => 'yes' ),
		) );
		$this->add_control( 'image_hscroll_auto_mobile', array(
			'label'        => esc_html__( 'Auto-scroll active swatch into view — Mobile', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
			'prefix_class' => 'wse-img-hscroll-auto-m-',
			'condition'    => array( 'image_hscroll_mobile' => 'yes' ),
		) );

		$this->add_control( 'show_price', array(
			'label'        => esc_html__( 'Show Price Under Image Swatches', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'woo-swatches-elementor' ),
			'label_off'    => esc_html__( 'No',  'woo-swatches-elementor' ),
			'return_value' => 'yes',
			'default'      => 'no',
		) );

		// v1.5.0 (C2') — Per-swatch savings / percent-off pill.
		// When ON, swatches whose term is on sale show a small "-N%"
		// corner pill (N = the highest discount among that term's in-stock
		// variations). Works for color, image, label, and button swatch
		// types. The pill markup is always rendered server-side when a
		// discount exists; this toggle adds .wse-show-savings-pill to
		// .wse-attr-block, which is what CSS uses to reveal it.
		$this->add_control( 'show_savings_pill', array(
			'label'        => esc_html__( 'Show Savings Pill (% off) on Swatches', 'woo-swatches-elementor' ),
			'description'  => esc_html__( 'Display a small "-N%" discount badge on the corner of each swatch whose variation is on sale.', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'woo-swatches-elementor' ),
			'label_off'    => esc_html__( 'No',  'woo-swatches-elementor' ),
			'return_value' => 'yes',
			'default'      => 'no',
		) );

		$this->add_control( 'oos_behavior', array(
			'label'   => esc_html__( 'Out-of-Stock Display', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array(
				'inherit' => esc_html__( 'Inherit from settings', 'woo-swatches-elementor' ),
				'blur'    => esc_html__( 'Blur',                  'woo-swatches-elementor' ),
				'cross'   => esc_html__( 'Cross out',             'woo-swatches-elementor' ),
				'hide'    => esc_html__( 'Hide',                  'woo-swatches-elementor' ),
			),
			'default' => 'inherit',
		) );

		$this->add_control( 'show_clear', array(
			'label'        => esc_html__( 'Show Clear Button', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'woo-swatches-elementor' ),
			'label_off'    => esc_html__( 'No',  'woo-swatches-elementor' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'swatch_limit', array(
			'label'       => esc_html__( 'Swatch Limit', 'woo-swatches-elementor' ),
			'description' => esc_html__( 'Show first N swatches. 0 = show all.', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'default'     => 0,
			'min'         => 0,
			'max'         => 50,
		) );

		$this->add_control( 'show_more_text', array(
			'label'     => esc_html__( '"Show More" Text', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => esc_html__( '+ more', 'woo-swatches-elementor' ),
			'condition' => array( 'swatch_limit!' => '0' ),
		) );

		// ── v1.2.3 Tier 0 — Editable text overrides ────────────────────────
		// Per the senior-developer "advanced Elementor control over every
		// text" feedback, this batch makes the most-visible hardcoded
		// strings editable per Widget 1 instance. All defaults match the
		// previous hardcoded values so existing widget instances render
		// identically without any user action.
		$this->add_control( 'tier0_heading', array(
			'label'     => esc_html__( 'Text Overrides', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_control( 'clear_text', array(
			'label'       => esc_html__( '"Clear" link text', 'woo-swatches-elementor' ),
			'description' => esc_html__( 'Reset link shown after a swatch is selected. Hide it via the Show Clear Button toggle above.', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => esc_html__( 'Clear', 'woo-swatches-elementor' ),
		) );

		$this->add_control( 'choose_option_placeholder', array(
			'label'       => esc_html__( 'Dropdown placeholder text', 'woo-swatches-elementor' ),
			'description' => esc_html__( 'The "Choose an option" placeholder shown by the WooCommerce native dropdown for unsupported swatch types.', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => esc_html__( 'Choose an option', 'woo-swatches-elementor' ),
		) );

		$this->add_control( 'selected_value_prefix', array(
			'label'       => esc_html__( 'Selected value prefix', 'woo-swatches-elementor' ),
			'description' => esc_html__( 'Optional text before the selected option name. e.g. "Selected: " produces "Selected: Blue". Leave empty to just show the value.', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'condition'   => array(
				'show_label'           => 'yes',
				'show_selected_value'  => 'yes',
			),
		) );

		$this->add_control( 'oos_label_suffix', array(
			'label'       => esc_html__( 'Out-of-stock suffix', 'woo-swatches-elementor' ),
			'description' => esc_html__( 'Text appended to a swatch\'s screen-reader label when the variation is out of stock. e.g. "Blue (unavailable)".', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => esc_html__( '(unavailable)', 'woo-swatches-elementor' ),
		) );

		$this->end_controls_section();
	}

	// ── STYLE TAB — Swatch shape, size, gap ──────────────────────────────

	private function register_style_swatches(): void {

		$this->start_controls_section( 'section_style_swatch', array(
			'label' => esc_html__( 'Swatch', 'woo-swatches-elementor' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'swatch_shape', array(
			'label'     => esc_html__( 'Shape', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'options'   => array(
				'6px' => esc_html__( 'Rounded square (default)', 'woo-swatches-elementor' ),
				'50%' => esc_html__( 'Circle',                   'woo-swatches-elementor' ),
				'0'   => esc_html__( 'Square',                   'woo-swatches-elementor' ),
			),
			'default'   => '6px',
			'selectors' => array(
				'{{WRAPPER}} .wse-swatch, {{WRAPPER}} .wse-swatch-img' => 'border-radius: {{VALUE}};',
			),
		) );

		// Gap 41 — add_responsive_control() for all dimensional controls
		$this->add_responsive_control( 'swatch_size', array(
			'label'          => esc_html__( 'Size', 'woo-swatches-elementor' ),
			'type'           => \Elementor\Controls_Manager::SLIDER,
			'size_units'     => array( 'px' ),
			'range'          => array( 'px' => array( 'min' => 20, 'max' => 120 ) ),
			'default'        => array( 'size' => 36,  'unit' => 'px' ),
			'tablet_default' => array( 'size' => 32,  'unit' => 'px' ),
			'mobile_default' => array( 'size' => 44,  'unit' => 'px' ), // WCAG 2.2 min touch target
			'selectors'      => array(
				'{{WRAPPER}} .wse-swatch.wse-swatch-color,
				 {{WRAPPER}} .wse-swatch.wse-swatch-image' =>
					'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_responsive_control( 'swatch_gap', array(
			'label'          => esc_html__( 'Gap Between Swatches', 'woo-swatches-elementor' ),
			'type'           => \Elementor\Controls_Manager::SLIDER,
			'size_units'     => array( 'px' ),
			'range'          => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'default'        => array( 'size' => 6,  'unit' => 'px' ),
			'tablet_default' => array( 'size' => 6,  'unit' => 'px' ),
			'mobile_default' => array( 'size' => 8,  'unit' => 'px' ),
			'selectors'      => array(
				'{{WRAPPER}} .wse-swatches' => 'gap: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_responsive_control( 'swatch_alignment', array(
			'label'     => esc_html__( 'Alignment', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'flex-start' => array(
					'title' => esc_html__( 'Left',   'woo-swatches-elementor' ),
					'icon'  => 'eicon-text-align-left',
				),
				'center'     => array(
					'title' => esc_html__( 'Center', 'woo-swatches-elementor' ),
					'icon'  => 'eicon-text-align-center',
				),
				'flex-end'   => array(
					'title' => esc_html__( 'Right',  'woo-swatches-elementor' ),
					'icon'  => 'eicon-text-align-right',
				),
			),
			'default'   => 'flex-start',
			'selectors' => array(
				'{{WRAPPER}} .wse-swatches' => 'justify-content: {{VALUE}};',
			),
		) );

		$this->add_responsive_control( 'attr_block_spacing', array(
			'label'      => esc_html__( 'Spacing Between Attributes', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'default'    => array( 'size' => 16, 'unit' => 'px' ),
			'selectors'  => array(
				'{{WRAPPER}} .wse-attr-block + .wse-attr-block' => 'margin-top: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->end_controls_section();
	}

	// ── STYLE TAB — Active state ──────────────────────────────────────────

	private function register_style_active_state(): void {

		$this->start_controls_section( 'section_style_active', array(
			'label' => esc_html__( 'Selected State', 'woo-swatches-elementor' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'active_border_color', array(
			'label'     => esc_html__( 'Border Color', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#0066cc',
			'selectors' => array(
				'{{WRAPPER}} .wse-swatch.selected'     => 'outline-color: {{VALUE}}; border-color: {{VALUE}};',
				'{{WRAPPER}} .wse-swatch-label.selected' => 'border-color: {{VALUE}}; color: {{VALUE}};',
			),
		) );

		$this->add_control( 'active_border_width', array(
			'label'      => esc_html__( 'Border Width', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 1, 'max' => 6 ) ),
			'default'    => array( 'size' => 2, 'unit' => 'px' ),
			'selectors'  => array(
				'{{WRAPPER}} .wse-swatch.wse-swatch-color.selected,
				 {{WRAPPER}} .wse-swatch.wse-swatch-image.selected' =>
					'outline-width: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .wse-swatch-label.selected' => 'border-width: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_control( 'active_border_offset', array(
			'label'      => esc_html__( 'Border Offset (gap from swatch)', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 6 ) ),
			'default'    => array( 'size' => 2, 'unit' => 'px' ),
			'selectors'  => array(
				'{{WRAPPER}} .wse-swatch.wse-swatch-color.selected,
				 {{WRAPPER}} .wse-swatch.wse-swatch-image.selected' =>
					'outline-offset: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_control( 'active_indicator', array(
			'label'     => esc_html__( 'Active Indicator', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'options'   => array(
				'outline'    => esc_html__( 'Border ring (default)', 'woo-swatches-elementor' ),
				'checkmark'  => esc_html__( 'Checkmark overlay',    'woo-swatches-elementor' ),
				'dot'        => esc_html__( 'Dot indicator',        'woo-swatches-elementor' ),
			),
			'default'   => 'outline',
		) );

		$this->end_controls_section();
	}

	// ── STYLE TAB — Hover ────────────────────────────────────────────────

	private function register_style_hover(): void {

		$this->start_controls_section( 'section_style_hover', array(
			'label' => esc_html__( 'Hover', 'woo-swatches-elementor' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'hover_effect', array(
			'label'   => esc_html__( 'Hover Effect', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array(
				'none'  => esc_html__( 'None',        'woo-swatches-elementor' ),
				'grow'  => esc_html__( 'Grow',        'woo-swatches-elementor' ),
				'glow'  => esc_html__( 'Border glow', 'woo-swatches-elementor' ),
				'lift'  => esc_html__( 'Lift',        'woo-swatches-elementor' ),
			),
			'default' => 'grow',
			'selectors' => array(
				// Grow
				'{{WRAPPER}} .wse-swatch:hover[data-hover=grow]' => 'transform: scale(1.1);',
			),
		) );

		$this->add_control( 'transition_speed', array(
			'label'      => esc_html__( 'Transition Speed', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'ms' ),
			'range'      => array( 'ms' => array( 'min' => 0, 'max' => 600 ) ),
			'default'    => array( 'size' => 200, 'unit' => 'ms' ),
			'selectors'  => array(
				'{{WRAPPER}} .wse-swatch' =>
					'transition: all {{SIZE}}{{UNIT}} ease;',
			),
		) );

		$this->end_controls_section();
	}

	// ── STYLE TAB — Out of stock ─────────────────────────────────────────

	private function register_style_oos(): void {

		$this->start_controls_section( 'section_style_oos', array(
			'label' => esc_html__( 'Out of Stock', 'woo-swatches-elementor' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'oos_opacity', array(
			'label'      => esc_html__( 'Blur Opacity', 'woo-swatches-elementor' ),
			'description'=> esc_html__( 'Applied when Out-of-Stock mode is Blur.', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
			'default'    => array( 'size' => 0.35 ),
			'selectors'  => array(
				'{{WRAPPER}} .wse-swatch.disabled' => 'opacity: {{SIZE}};',
			),
		) );

		$this->add_control( 'oos_cross_color', array(
			'label'     => esc_html__( 'Cross Colour', 'woo-swatches-elementor' ),
			'description'=> esc_html__( 'Applied when Out-of-Stock mode is Cross out.', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => 'rgba(0,0,0,0.5)',
			'selectors' => array(
				'{{WRAPPER}} .wse-swatch.disabled.cross-mode::after' =>
					'background: linear-gradient(to bottom right, transparent calc(50% - 1px), {{VALUE}}, transparent calc(50% + 1px));',
			),
		) );

		$this->add_control( 'oos_cross_width', array(
			'label'      => esc_html__( 'Cross Thickness', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 1, 'max' => 4 ) ),
			'default'    => array( 'size' => 1, 'unit' => 'px' ),
			'selectors'  => array(
				'{{WRAPPER}} .wse-swatch.disabled.cross-mode::after' =>
					'background: linear-gradient(to bottom right, transparent calc(50% - {{SIZE}}{{UNIT}}), currentColor, transparent calc(50% + {{SIZE}}{{UNIT}}));',
			),
		) );

		$this->end_controls_section();
	}

	// ── STYLE TAB — Tooltip ───────────────────────────────────────────────

	private function register_style_tooltip(): void {

		$this->start_controls_section( 'section_style_tooltip', array(
			'label' => esc_html__( 'Tooltip', 'woo-swatches-elementor' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'tooltip_position', array(
			'label'   => esc_html__( 'Position', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array(
				'top'    => esc_html__( 'Top',    'woo-swatches-elementor' ),
				'bottom' => esc_html__( 'Bottom', 'woo-swatches-elementor' ),
				'left'   => esc_html__( 'Left',   'woo-swatches-elementor' ),
				'right'  => esc_html__( 'Right',  'woo-swatches-elementor' ),
			),
			'default' => 'top',
		) );

		$this->add_control( 'tooltip_bg_color', array(
			'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#333333',
			'selectors' => array(
				'{{WRAPPER}} .wse-swatch:hover::after' => 'background: {{VALUE}};',
			),
		) );

		$this->add_control( 'tooltip_text_color', array(
			'label'     => esc_html__( 'Text Colour', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => array(
				'{{WRAPPER}} .wse-swatch:hover::after' => 'color: {{VALUE}};',
			),
		) );

		$this->add_control( 'tooltip_border_radius', array(
			'label'      => esc_html__( 'Border Radius', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
			'default'    => array( 'size' => 4, 'unit' => 'px' ),
			'selectors'  => array(
				'{{WRAPPER}} .wse-swatch:hover::after' => 'border-radius: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->end_controls_section();
	}

	// ── STYLE TAB — Attribute label & selected value typography ──────────

	private function register_style_typography(): void {

		$this->start_controls_section( 'section_style_typography', array(
			'label' => esc_html__( 'Attribute Label', 'woo-swatches-elementor' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'attr_label_color', array(
			'label'     => esc_html__( 'Label Colour', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .wse-attr-name' => 'color: {{VALUE}};' ),
		) );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'attr_label_typography',
				'label'    => esc_html__( 'Label Typography', 'woo-swatches-elementor' ),
				'selector' => '{{WRAPPER}} .wse-attr-name',
			)
		);

		$this->add_control( 'selected_value_color', array(
			'label'     => esc_html__( 'Selected Value Colour', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .wse-attr-selected-val' => 'color: {{VALUE}};' ),
		) );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'selected_value_typography',
				'label'    => esc_html__( 'Selected Value Typography', 'woo-swatches-elementor' ),
				'selector' => '{{WRAPPER}} .wse-attr-selected-val',
			)
		);

		$this->end_controls_section();
	}

	// ── STYLE TAB — Label swatch appearance ──────────────────────────────

	private function register_style_label_swatch(): void {

		$this->start_controls_section( 'section_style_label_swatch', array(
			'label' => esc_html__( 'Label / Button Swatch', 'woo-swatches-elementor' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_responsive_control( 'label_padding', array(
			'label'      => esc_html__( 'Padding', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'default'    => array(
				'top'    => '8', 'right' => '16',
				'bottom' => '8', 'left'  => '16',
				'unit'   => 'px', 'isLinked' => false,
			),
			'selectors'  => array(
				'{{WRAPPER}} .wse-swatch-label' =>
					'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->add_control( 'label_border_radius', array(
			'label'      => esc_html__( 'Border Radius', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'default'    => array( 'top' => '6', 'right' => '6', 'bottom' => '6', 'left' => '6', 'unit' => 'px', 'isLinked' => true ),
			'selectors'  => array(
				'{{WRAPPER}} .wse-swatch-label' =>
					'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->start_controls_tabs( 'label_swatch_tabs' );

		$this->start_controls_tab( 'label_tab_normal', array(
			'label' => esc_html__( 'Normal', 'woo-swatches-elementor' ),
		) );

		$this->add_control( 'label_text_color', array(
			'label'     => esc_html__( 'Text Colour', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .wse-swatch-label' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'label_bg_color', array(
			'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .wse-swatch-label' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'label_border',
				'selector' => '{{WRAPPER}} .wse-swatch-label',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'label_tab_selected', array(
			'label' => esc_html__( 'Selected', 'woo-swatches-elementor' ),
		) );

		$this->add_control( 'label_text_color_selected', array(
			'label'     => esc_html__( 'Text Colour', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .wse-swatch-label.selected' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'label_bg_color_selected', array(
			'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .wse-swatch-label.selected' => 'background-color: {{VALUE}};' ),
		) );

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'label'    => esc_html__( 'Typography', 'woo-swatches-elementor' ),
				'selector' => '{{WRAPPER}} .wse-swatch-label .wse-swatch-label-text',
			)
		);

		$this->end_controls_section();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Render (PHP → frontend HTML)
	//
	// v1.1.0 (B3) — Widget 1 no longer emits its own <form class="variations_form">.
	// Instead it registers with WSE_Form_Registry and emits swatch UI only,
	// targeting the canonical form (owned by Widget 2 when present, or
	// synthesised at DOMReady by swatches.js when Widget 1 is alone).
	// ─────────────────────────────────────────────────────────────────────

	protected function render(): void {

		$settings = $this->get_settings_for_display();

		// ── Resolve product ───────────────────────────────────────────────
		$product_id = ! empty( $settings['product_id'] )
			? absint( $settings['product_id'] )
			: get_the_ID();

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product || ! $product->is_type( 'variable' ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				?>
				<div class="wse-editor-placeholder">
					<p><?php esc_html_e( 'Select a variable WooCommerce product to display swatches.', 'woo-swatches-elementor' ); ?></p>
				</div>
				<?php
			}
			return;
		}

		/** @var \WC_Product_Variable $product */

		// ── v1.1.0 (B3) — Register with the form registry ────────────────
		$registry  = WSE_Form_Registry::instance();
		$widget_id = (string) $this->get_id();
		$registry->register_widget1( $product_id, $widget_id );
		$form_id = $registry->get_form_id( $product_id );

		// ── Default attributes for pre-selection (Gap 39) ─────────────────
		$default_attributes = $product->get_default_attributes();

		// ── OOS behaviour override via widget setting ─────────────────────
		$oos_class = '';
		if ( 'inherit' !== $settings['oos_behavior'] ) {
			$oos_class = 'wse-oos-' . sanitize_html_class( $settings['oos_behavior'] );
		}

		// ── Widget wrapper (no <form> — JS attaches to canonical form id) ─
		$this->add_render_attribute( 'widget_wrap', array(
			'class'           => trim( 'wse-widget-swatches wse-swatches-wrap ' . $oos_class ),
			'data-product-id' => (string) $product_id,
			'data-form-id'    => $form_id,
		) );

		// v1.1.0 (B3) — Widget 1 emits swatch UI only (no hidden selects).
		// The canonical form (Widget 2, or JS-wrapped synthetic) owns the selects.
		$emit_select_false = function () { return false; };
		add_filter( 'wse_renderer_emit_select', $emit_select_false, 99 );

		// v1.2.3 Tier 0 — Per-widget text overrides flow to the renderer
		// templates via temporary filters. Each filter is removed at the
		// end of this render() so other Widget 1 instances on the same
		// page (with different settings) get their own overrides clean.
		$tier0_clear_text  = (string) ( $settings['clear_text']                ?? __( 'Clear',             'woo-swatches-elementor' ) );
		$tier0_choose_text = (string) ( $settings['choose_option_placeholder'] ?? __( 'Choose an option',  'woo-swatches-elementor' ) );
		$tier0_oos_suffix  = (string) ( $settings['oos_label_suffix']          ?? __( '(unavailable)',     'woo-swatches-elementor' ) );

		// v1.3.5 (B1) — show_clear toggle wired through to wrapper.php via
		// a new filter `wse_show_clear_button` (matching the tier-0 pattern).
		// Pre-1.3.5 the toggle in the widget UI was being read but never
		// applied — wrapper.php unconditionally rendered the <a class="wse-
		// reset-link"> element. Now wrapper.php checks this filter and
		// skips emitting the element when 'no'.
		$tier0_show_clear  = ( ( $settings['show_clear'] ?? 'yes' ) === 'yes' ) ? 'yes' : 'no';

		$tier0_clear_filter      = static function () use ( $tier0_clear_text )  { return $tier0_clear_text; };
		$tier0_choose_filter     = static function () use ( $tier0_choose_text ) { return $tier0_choose_text; };
		$tier0_oos_filter        = static function () use ( $tier0_oos_suffix )  { return $tier0_oos_suffix; };
		$tier0_show_clear_filter = static function () use ( $tier0_show_clear ) { return $tier0_show_clear; };

		add_filter( 'wse_clear_button_text',          $tier0_clear_filter );
		add_filter( 'wse_choose_option_placeholder',  $tier0_choose_filter );
		add_filter( 'wse_oos_label_suffix',           $tier0_oos_filter );
		add_filter( 'wse_show_clear_button',          $tier0_show_clear_filter );
		?>
		<div <?php echo $this->get_render_attribute_string( 'widget_wrap' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>

			<?php
			// v1.2.1 (F3+F4) — read image-label position once, default below.
			$image_label_pos = sanitize_html_class( $settings['image_label_position'] ?? 'below' );
			if ( ! in_array( $image_label_pos, array( 'above', 'below', 'hover', 'hidden' ), true ) ) {
				$image_label_pos = 'below';
			}

			// v1.2.1 (F3) — control whether the attribute-name row above
			// swatches is forced visible for non-color swatch types.
			$show_label_non_color = ( $settings['show_label_non_color'] ?? 'no' ) === 'yes';

			foreach ( $product->get_variation_attributes() as $attr_name => $options ) :

				$attr_label   = wc_attribute_label( $attr_name, $product );
				$selected_val = $default_attributes[ sanitize_title( $attr_name ) ] ?? '';

				// v1.2.1 (F3) — Resolve the swatch type (color/image/label/button/select)
				// for this attribute so the wrapper carries data-type="…" and the per-type
				// CSS rules can hide the attribute-name label row for non-color types.
				$_product_attrs = $product->get_attributes();
				if ( isset( $_product_attrs[ $attr_name ] ) && ! $_product_attrs[ $attr_name ]->is_taxonomy() ) {
					$swatch_type = WSE_Local_Attributes::get_attribute_type( $product_id, $attr_name );
				} else {
					$swatch_type = WSE_Attribute_Types::get_attribute_type( $attr_name );
				}

				// Per-type wrapper classes.
				$attr_block_classes = array(
					'wse-attr-block',
					'wse-label-' . sanitize_html_class( $settings['label_position'] ),
				);
				// v1.3.5 (B2) — Always add the image-label-pos class to
				// .wse-attr-block regardless of swatch_type detection. The
				// CSS rules only do anything for elements that contain
				// .wse-swatch-image-label (which only image-type swatches
				// have), so it's harmless on non-image attributes. This
				// makes the position dropdown work even when local
				// attribute type detection misfires.
				$attr_block_classes[] = 'wse-image-label-pos-' . $image_label_pos;
				// Pre-1.3.5 path kept for the data-type guard but no longer
				// needed for class application:
				if ( 'image' === $swatch_type ) {
					// (intentionally empty — class already added above)
					$attr_block_classes[] = 'wse-image-attr';
				}
				// v1.2.1 (F3) — opt-in override to keep label row visible for non-color types.
				if ( $show_label_non_color ) {
					$attr_block_classes[] = 'wse-force-show-label';
				}
				// v1.2.3 (Issue 7) — Show price under image swatches (Widget 1 toggle).
				// When ON, .wse-show-image-price on .wse-attr-block reveals the
				// per-swatch price <span> that's always rendered in image.php.
				if ( 'image' === $swatch_type && 'yes' === ( $settings['show_price'] ?? 'no' ) ) {
					$attr_block_classes[] = 'wse-show-image-price';
				}
				// v1.5.0 (C2') — Show savings pill on swatches (all types).
				// When ON, .wse-show-savings-pill on .wse-attr-block reveals
				// the per-swatch "-N%" pill that's always rendered (when the
				// term is on sale) in color.php / image.php / label.php.
				if ( 'yes' === ( $settings['show_savings_pill'] ?? 'no' ) ) {
					$attr_block_classes[] = 'wse-show-savings-pill';
				}
				?>

				<div class="<?php echo esc_attr( implode( ' ', $attr_block_classes ) ); ?>"
				     data-attribute="<?php echo esc_attr( $attr_name ); ?>"
				     data-type="<?php echo esc_attr( $swatch_type ); ?>">

					<?php if ( 'yes' === $settings['show_label'] ) : ?>
					<div class="wse-attr-label-row">
						<span class="wse-attr-name"><?php echo esc_html( $attr_label ); ?></span>

						<?php if ( 'yes' === $settings['show_selected_value'] ) : ?>
						<span class="wse-attr-selected-val"
						      data-attribute="<?php echo esc_attr( $attr_name ); ?>"
						      data-prefix="<?php echo esc_attr( $settings['selected_value_prefix'] ?? '' ); ?>">
							<?php
							if ( $selected_val ) {
								// v1.2.3 Tier 0 — optional prefix before the value.
								$tier0_prefix = (string) ( $settings['selected_value_prefix'] ?? '' );
								echo esc_html( $tier0_prefix . ucwords( str_replace( array( '-', '_' ), ' ', $selected_val ) ) );
							}
							?>
						</span>
						<?php endif; ?>
					</div>
					<?php endif; ?>

					<?php
					/**
					 * Triggers woocommerce_dropdown_variation_attribute_options_html.
					 * WSE_Swatch_Renderer::render() intercepts and outputs ONLY
					 * the swatch <ul> (the wse_renderer_emit_select filter above
					 * suppresses the hidden <select> — that lives on the canonical
					 * form owned by Widget 2 / JS-wrapped form).
					 */
					wc_dropdown_variation_attribute_options( array(
						'options'          => $options,
						'attribute'        => $attr_name,
						'product'          => $product,
						'selected'         => $selected_val,
						'show_option_none' => esc_html__( 'Choose an option', 'woo-swatches-elementor' ),
					) );
					?>

				</div><!-- .wse-attr-block -->

			<?php endforeach; ?>

			<?php
			/**
			 * v1.1.0 (B3) — Variation JSON <script> tag, emitted at most once
			 * per product per page. Used by swatches.js when wrapping a
			 * Widget 1-alone setup in a synthetic canonical form (scenario c)
			 * and by form-field-dependency.js for availability calculations.
			 *
			 * When Widget 2 (canonical) is also present on the page, its form
			 * carries data-product_variations natively — JS reconciliation
			 * prefers the form's attribute and ignores the script tag.
			 */
			if ( $registry->should_emit_json( $product_id ) ) :
				$variations_json = wp_json_encode( $product->get_available_variations() );
				?>
				<script type="application/json"
				        class="wse-variations-json"
				        data-product-id="<?php echo absint( $product_id ); ?>"><?php
					// JSON in a <script type="application/json"> block is treated
					// as data, not script — no XSS surface, no escaping needed
					// beyond wp_json_encode's UTF-8 normalisation.
					echo $variations_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?></script>
				<?php
			endif;
			?>

		</div><!-- .wse-widget-swatches -->
		<?php
		remove_filter( 'wse_renderer_emit_select', $emit_select_false, 99 );

		// v1.2.3 Tier 0 — Remove the per-render text-override filters so
		// other Widget 1 instances on the same page (with different
		// settings) start clean.
		remove_filter( 'wse_clear_button_text',         $tier0_clear_filter );
		remove_filter( 'wse_choose_option_placeholder', $tier0_choose_filter );
		remove_filter( 'wse_oos_label_suffix',          $tier0_oos_filter );
		remove_filter( 'wse_show_clear_button',         $tier0_show_clear_filter );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 20 — Elementor live editor preview template
	// ─────────────────────────────────────────────────────────────────────

	protected function content_template(): void {
		?>
		<#
		var shape   = settings.swatch_shape || '6px';
		var sizePx  = settings.swatch_size && settings.swatch_size.size
		              ? ( settings.swatch_size.size + ( settings.swatch_size.unit || 'px' ) )
		              : '36px';
		var gapPx   = settings.swatch_gap && settings.swatch_gap.size
		              ? ( settings.swatch_gap.size + ( settings.swatch_gap.unit || 'px' ) )
		              : '6px';
		var acColor = settings.active_border_color || '#0066cc';
		var showLbl = settings.show_label === 'yes';
		var showSel = settings.show_selected_value === 'yes';
		#>

		<div class="wse-widget-swatches wse-editor-preview">

			<# if ( showLbl ) { #>
			<div class="wse-attr-label-row" style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
				<span class="wse-attr-name" style="font-weight:600;font-size:13px">Color</span>
				<# if ( showSel ) { #>
				<span class="wse-attr-selected-val" style="font-size:13px;color:#555">Navy Blue</span>
				<# } #>
			</div>
			<# } #>

			<ul class="wse-swatches wse-swatches-image"
			    style="display:flex;flex-wrap:wrap;gap:{{ gapPx }};list-style:none;padding:0;margin:0 0 14px">
				<li class="wse-swatch wse-swatch-image selected"
				    style="width:{{ sizePx }};height:{{ sizePx }};border-radius:{{ shape }};background:linear-gradient(135deg,#1a3a6b,#2c5aa0);display:flex;align-items:center;justify-content:center;font-size:18px;cursor:pointer;outline:2px solid {{ acColor }};outline-offset:2px">🧥</li>
				<li class="wse-swatch wse-swatch-image"
				    style="width:{{ sizePx }};height:{{ sizePx }};border-radius:{{ shape }};background:linear-gradient(135deg,#2d3436,#636e72);display:flex;align-items:center;justify-content:center;font-size:18px;cursor:pointer">🧥</li>
				<li class="wse-swatch wse-swatch-image"
				    style="width:{{ sizePx }};height:{{ sizePx }};border-radius:{{ shape }};background:linear-gradient(135deg,#c0392b,#e74c3c);display:flex;align-items:center;justify-content:center;font-size:18px;cursor:pointer">🧥</li>
				<li class="wse-swatch wse-swatch-image disabled"
				    style="width:{{ sizePx }};height:{{ sizePx }};border-radius:{{ shape }};background:linear-gradient(135deg,#556b2f,#6b8e23);display:flex;align-items:center;justify-content:center;font-size:18px;cursor:not-allowed;opacity:.35">🧥</li>
			</ul>

			<# if ( showLbl ) { #>
			<div class="wse-attr-label-row" style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
				<span class="wse-attr-name" style="font-weight:600;font-size:13px">Size</span>
				<# if ( showSel ) { #>
				<span class="wse-attr-selected-val" style="font-size:13px;color:#555"></span>
				<# } #>
			</div>
			<# } #>

			<ul class="wse-swatches wse-swatches-label"
			    style="display:flex;flex-wrap:wrap;gap:{{ gapPx }};list-style:none;padding:0;margin:0">
				<li class="wse-swatch wse-swatch-label"
				    style="padding:8px 16px;border:1.5px solid #d0d0d8;border-radius:{{ shape }};cursor:pointer;font-size:13px">S</li>
				<li class="wse-swatch wse-swatch-label selected"
				    style="padding:8px 16px;border:2px solid {{ acColor }};border-radius:{{ shape }};cursor:pointer;font-size:13px;color:{{ acColor }};font-weight:600">M</li>
				<li class="wse-swatch wse-swatch-label"
				    style="padding:8px 16px;border:1.5px solid #d0d0d8;border-radius:{{ shape }};cursor:pointer;font-size:13px">L</li>
				<li class="wse-swatch wse-swatch-label"
				    style="padding:8px 16px;border:1.5px solid #d0d0d8;border-radius:{{ shape }};cursor:pointer;font-size:13px">XL</li>
				<li class="wse-swatch wse-swatch-label disabled"
				    style="padding:8px 16px;border:1.5px solid #e0e0e0;border-radius:{{ shape }};cursor:not-allowed;font-size:13px;opacity:.35;text-decoration:line-through">3XL</li>
			</ul>

		</div>
		<?php
	}
}
