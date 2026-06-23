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
				'label' => esc_html__( 'Sticky Behavior', 'woo-swatches-elementor' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);

		// v1.2.1 (F2) — Per-widget sticky toggles moved to admin.
		// Sticky-on-mobile is a site-wide UX decision, not a per-widget
		// one. Configured globally at WC -> Settings -> WooSwatches ->
		// Sticky Add to Cart so every product page enforces the same
		// rule. Existing per-widget values from v1.2.0 and earlier are
		// silently ignored — the global option takes precedence.
		$this->add_control(
			'sticky_intro',
			array(
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => '<div style="font-size:11px;color:#475569;line-height:1.5;">'
					. esc_html__( 'Sticky behaviour for desktop, tablet, and mobile is configured globally at:', 'woo-swatches-elementor' )
					. '<br><strong>WooCommerce → Settings → WooSwatches → Sticky Add to Cart</strong>'
					. '<br>'
					. esc_html__( 'This ensures the same sticky rule applies to every product page across the store.', 'woo-swatches-elementor' )
					. '</div>',
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

		// v1.2.2 — Icon defaults changed from eicon-minus/eicon-plus to
		// empty values so the inline-SVG fallback in templates/quantity-stepper.php
		// is the default visual. This bypasses the Elementor icon-data manager
		// entirely on installs where its array doesn't have a "minus"/"plus"
		// key (a known issue across some Elementor / Elementor Pro version
		// combinations) — see the v1.2.2 changelog for the full diagnosis.
		// Users who explicitly pick an Elementor icon via the picker still
		// get that rendering through Icons_Manager.
		$this->add_control(
			'decrease_icon',
			array(
				'label'       => esc_html__( 'Decrease icon', 'woo-swatches-elementor' ),
				'type'        => \Elementor\Controls_Manager::ICONS,
				'default'     => array(
					'value'   => '',
					'library' => '',
				),
				'description' => esc_html__( 'Leave empty to use the built-in minus glyph (recommended). Pick an Elementor icon to override.', 'woo-swatches-elementor' ),
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
					'value'   => '',
					'library' => '',
				),
				'description' => esc_html__( 'Leave empty to use the built-in plus glyph (recommended). Pick an Elementor icon to override.', 'woo-swatches-elementor' ),
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

		// ── v1.2.3 Tier 0 — Editable accessibility text overrides ─────────
		// Aria-labels and titles for the stepper controls. Defaults match
		// the previous hardcoded values so existing widget instances keep
		// rendering identically. Per the senior-developer "advanced control
		// over every text" feedback.
		$this->add_control( 'tier0_qty_heading', array(
			'label'     => esc_html__( 'Accessibility & Title Text', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
			'condition' => array( 'show_quantity' => 'yes' ),
		) );

		$this->add_control( 'qty_input_aria_label', array(
			'label'       => esc_html__( 'Quantity field aria-label', 'woo-swatches-elementor' ),
			'description' => esc_html__( 'Screen-reader label for the quantity number input.', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => esc_html__( 'Quantity', 'woo-swatches-elementor' ),
			'condition'   => array( 'show_quantity' => 'yes' ),
		) );

		$this->add_control( 'qty_decrease_aria_label', array(
			'label'       => esc_html__( 'Decrease button aria-label', 'woo-swatches-elementor' ),
			'description' => esc_html__( 'Screen-reader label for the [-] decrement button.', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => esc_html__( 'Decrease quantity', 'woo-swatches-elementor' ),
			'condition'   => array(
				'show_quantity'             => 'yes',
				'show_qty_stepper_buttons'  => 'yes',
			),
		) );

		$this->add_control( 'qty_increase_aria_label', array(
			'label'       => esc_html__( 'Increase button aria-label', 'woo-swatches-elementor' ),
			'description' => esc_html__( 'Screen-reader label for the [+] increment button.', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => esc_html__( 'Increase quantity', 'woo-swatches-elementor' ),
			'condition'   => array(
				'show_quantity'             => 'yes',
				'show_qty_stepper_buttons'  => 'yes',
			),
		) );

		$this->add_control( 'qty_decrease_title', array(
			'label'       => esc_html__( 'Decrease button hover-title', 'woo-swatches-elementor' ),
			'description' => esc_html__( 'Optional title attribute (mouse hover tooltip). Leave empty to omit. Different from the aria-label which is for screen readers.', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'condition'   => array(
				'show_quantity'             => 'yes',
				'show_qty_stepper_buttons'  => 'yes',
			),
		) );

		$this->add_control( 'qty_increase_title', array(
			'label'       => esc_html__( 'Increase button hover-title', 'woo-swatches-elementor' ),
			'description' => esc_html__( 'Optional title attribute (mouse hover tooltip). Leave empty to omit.', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'condition'   => array(
				'show_quantity'             => 'yes',
				'show_qty_stepper_buttons'  => 'yes',
			),
		) );

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
				'label'        => esc_html__( 'Full Width Button — Desktop', 'woo-swatches-elementor' ),
				'description'  => esc_html__( 'Stretch the Add to Cart button to fill its column on desktop. Stacks the button below the quantity stepper.', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		// v1.2.3 (Issue 4) — Per-device full-width toggles.
		// Each switcher emits its own prefix_class on the widget wrapper:
		//   button_full_width_tablet -> wse-atc-fw-tablet-{yes|no}
		//   button_full_width_mobile -> wse-atc-fw-mobile-{yes|no}
		// CSS in add-to-cart.css picks up '-yes' classes inside each
		// breakpoint's media query so the layout cascades naturally:
		// mobile rule overrides tablet, tablet overrides desktop.
		$this->add_control(
			'button_full_width_tablet',
			array(
				'label'        => esc_html__( 'Full Width Button — Tablet (≤1024px)', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'prefix_class' => 'wse-atc-fw-tablet-',
			)
		);

		$this->add_control(
			'button_full_width_mobile',
			array(
				'label'        => esc_html__( 'Full Width Button — Mobile (≤768px)', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'prefix_class' => 'wse-atc-fw-mobile-',
			)
		);

		// v1.2.0 — Inline price toggle.
		// Widget 3 (ZYMARG Price) is the new default owner of price display.
		// This toggle controls whether Widget 2 ALSO renders the variation
		// price slot. Default is read from wse_widget2_inline_price_default,
		// which the activator pins to 'yes' on upgrades from < 1.2.0 (so
		// existing v1.1.x users keep their inline price) and 'no' on fresh
		// installs (so Widget 3 owns the price out of the box).
		$this->add_control(
			'show_inline_price',
			array(
				'label'        => esc_html__( 'Show Inline Price', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'woo-swatches-elementor' ),
				'label_off'    => esc_html__( 'Hide', 'woo-swatches-elementor' ),
				'return_value' => 'yes',
				'default'      => get_option( 'wse_widget2_inline_price_default', 'no' ),
				'description'  => esc_html__(
					'Show the variation price inside this widget. Turn OFF when you have placed the ZYMARG Price (Widget 3) elsewhere on the page so it owns price display.',
					'woo-swatches-elementor'
				),
			)
		);

		$this->end_controls_section();

		// ── v1.4.5 — Buy Now Button section ──────────────────────────────
		$this->start_controls_section(
			'section_buy_now',
			array(
				'label' => esc_html__( 'Buy Now Button', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_buy_now',
			array(
				'label'        => esc_html__( 'Show Buy Now Button', 'woo-swatches-elementor' ),
				'description'  => esc_html__( 'Adds a "Buy Now" button after the Add to Cart button that skips the cart and takes the customer directly to checkout.', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'buy_now_text',
			array(
				'label'       => esc_html__( 'Button Text', 'woo-swatches-elementor' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => esc_html__( 'Buy Now', 'woo-swatches-elementor' ),
				'placeholder' => esc_html__( 'Buy Now', 'woo-swatches-elementor' ),
				'dynamic'     => array( 'active' => true ),
				'condition'   => array( 'show_buy_now' => 'yes' ),
			)
		);

		$this->add_control(
			'buy_now_full_width',
			array(
				'label'        => esc_html__( 'Full Width', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'show_buy_now' => 'yes' ),
			)
		);

		$this->add_control(
			'buy_now_position',
			array(
				'label'       => esc_html__( 'Button Position', 'woo-swatches-elementor' ),
				'description' => esc_html__( 'Choose whether the Buy Now button appears above or below the Add to Cart button.', 'woo-swatches-elementor' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'below',
				'options'     => array(
					'below' => esc_html__( 'Add to Cart above, Buy Now below (default)', 'woo-swatches-elementor' ),
					'above' => esc_html__( 'Buy Now above, Add to Cart below', 'woo-swatches-elementor' ),
				),
				'condition'   => array( 'show_buy_now' => 'yes' ),
			)
		);

		$this->end_controls_section();

		// ── v1.4.7 — Sticky Add to Cart Layout section ───────────────────
		$this->start_controls_section(
			'section_sticky_layout',
			array(
				'label' => esc_html__( 'Sticky Layout', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'sticky_layout_intro',
			array(
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => '<div style="font-size:11px;color:#475569;line-height:1.5;">'
					. esc_html__( 'These controls apply ONLY when the sticky Add to Cart bar is active (fixed to the bottom of the viewport). The normal/inline layout is unaffected.', 'woo-swatches-elementor' )
					. '</div>',
			)
		);

		$this->add_control(
			'sticky_compact_layout',
			array(
				'label'        => esc_html__( 'Compact single-row layout', 'woo-swatches-elementor' ),
				'description'  => esc_html__( 'When ON: sticky bar shows QS + ATC + BN in one horizontal row (30% / 35% / 35% width split). Minimal padding for a sleek mobile bar.', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'sticky_button_order',
			array(
				'label'   => esc_html__( 'Button order in sticky bar', 'woo-swatches-elementor' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'atc_bn',
				'options' => array(
					'atc_bn' => esc_html__( 'QS + Add to Cart + Buy Now', 'woo-swatches-elementor' ),
					'bn_atc' => esc_html__( 'QS + Buy Now + Add to Cart', 'woo-swatches-elementor' ),
				),
				'condition' => array( 'sticky_compact_layout' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	// ── Style ─────────────────────────────────────────────────────────────

	private function register_style_controls(): void {

		// ── v1.4.7 — Widget Background + Spacing ──────────────────────────
		$this->start_controls_section(
			'section_style_widget',
			array(
				'label' => esc_html__( 'Widget Container', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'widget_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-widget-add-to-cart' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'widget_padding',
			array(
				'label'      => esc_html__( 'Padding', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-widget-add-to-cart' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'widget_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-widget-add-to-cart' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'widget_border',
				'selector' => '{{WRAPPER}} .wse-widget-add-to-cart',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'widget_box_shadow',
				'selector' => '{{WRAPPER}} .wse-widget-add-to-cart',
			)
		);

		$this->add_responsive_control(
			'button_gap',
			array(
				'label'      => esc_html__( 'Gap Between Buttons', 'woo-swatches-elementor' ),
				'description' => esc_html__( 'Space between Add to Cart and Buy Now buttons. Also applies between quantity stepper and buttons.', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40 ),
					'em' => array( 'min' => 0, 'max' => 3 ),
				),
				'default'    => array( 'unit' => 'px', 'size' => 10 ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-qty-atc-row' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// ── v1.4.7 — Sticky Bar Style ─────────────────────────────────────
		$this->start_controls_section(
			'section_style_sticky',
			array(
				'label' => esc_html__( 'Sticky Bar', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'sticky_bg_color',
			array(
				'label'     => esc_html__( 'Background Color', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .wse-widget-add-to-cart.wse-sticky-active' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'sticky_padding',
			array(
				'label'      => esc_html__( 'Padding', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'    => '10',
					'right'  => '16',
					'bottom' => '10',
					'left'   => '16',
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .wse-widget-add-to-cart.wse-sticky-active' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'sticky_margin',
			array(
				'label'      => esc_html__( 'Margin', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-widget-add-to-cart.wse-sticky-active' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'sticky_width',
			array(
				'label'      => esc_html__( 'Width', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( '%', 'px', 'vw' ),
				'range'      => array(
					'%'  => array( 'min' => 50, 'max' => 100 ),
					'px' => array( 'min' => 200, 'max' => 1400 ),
					'vw' => array( 'min' => 50, 'max' => 100 ),
				),
				'default'    => array( 'unit' => '%', 'size' => 100 ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-widget-add-to-cart.wse-sticky-active' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'sticky_border',
				'selector' => '{{WRAPPER}} .wse-widget-add-to-cart.wse-sticky-active',
			)
		);

		$this->add_responsive_control(
			'sticky_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-widget-add-to-cart.wse-sticky-active' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'sticky_box_shadow',
				'selector' => '{{WRAPPER}} .wse-widget-add-to-cart.wse-sticky-active',
			)
		);

		$this->add_responsive_control(
			'sticky_gap',
			array(
				'label'      => esc_html__( 'Gap Between Elements', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 8 ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-widget-add-to-cart.wse-sticky-active .wse-qty-atc-row' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

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
				'size_units' => array( 'px', '%', 'em' ),
				// v1.2.3 (Issue 2) — Bumped px max from 160 to 600 and added
				// % + em units. The previous 160px ceiling made it hard to
				// pair the qty input with a wider Add to Cart button on
				// large layouts. The new range covers narrow (40px) through
				// extra-wide (600px / 100%) without artificial constraint.
				'range'      => array(
					'px' => array( 'min' => 40, 'max' => 600 ),
					'%'  => array( 'min' => 10, 'max' => 100 ),
					'em' => array( 'min' => 2,  'max' => 30 ),
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
		// v1.2.1 (F1) — Width mode: Auto / Custom / Full.
		// "Full" makes the stepper fill its parent (qty input grows via
		// flex:1, buttons keep their fixed size). "Custom" exposes the
		// total-width slider below. "Auto" lets contents drive the size.

		// v1.3.5 (F2) — Per-device "Full Width Stepper" convenience
		// switchers. The qty_stepper_width_mode SELECT below already has
		// a 'full' option, but it's not per-device and is buried in the
		// Sizing subsection. These three switchers give a one-click
		// shortcut per breakpoint, matching the existing "Add to Cart
		// Full Width" pattern. When ON for a breakpoint, CSS forces
		// .wse-qty-stepper to width: 100% inside the matching @media
		// query. Independent of the SELECT mode below for backwards
		// compat — when both are set, the switcher wins via CSS source
		// order + !important.
		$this->add_control(
			'qty_stepper_full_width_desktop',
			array(
				'label'        => esc_html__( 'Full Width Stepper — Desktop', 'woo-swatches-elementor' ),
				'description'  => esc_html__( 'Quick toggle to make the stepper fill its parent column at desktop bp.', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'prefix_class' => 'wse-qty-fullw-d-',
			)
		);
		$this->add_control(
			'qty_stepper_full_width_tablet',
			array(
				'label'        => esc_html__( 'Full Width Stepper — Tablet (≤1024px)', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'prefix_class' => 'wse-qty-fullw-t-',
			)
		);
		$this->add_control(
			'qty_stepper_full_width_mobile',
			array(
				'label'        => esc_html__( 'Full Width Stepper — Mobile (≤768px)', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'prefix_class' => 'wse-qty-fullw-m-',
			)
		);

		$this->add_control(
			'qty_stepper_width_mode',
			array(
				'label'        => esc_html__( 'Stepper width mode', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SELECT,
				'default'      => 'auto',
				'options'      => array(
					'auto'   => esc_html__( 'Auto (content-based)', 'woo-swatches-elementor' ),
					'custom' => esc_html__( 'Custom width',         'woo-swatches-elementor' ),
					'full'   => esc_html__( 'Full width of parent', 'woo-swatches-elementor' ),
				),
				'prefix_class' => 'wse-qty-mode-',
				'description'  => esc_html__( 'Full Width fills the parent column; the quantity field grows to fill the space between the [-] and [+] buttons.', 'woo-swatches-elementor' ),
			)
		);

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
				// v1.2.1 (F1) — only relevant in Custom mode.
				'condition'  => array(
					'qty_stepper_width_mode' => 'custom',
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

		// ── v1.4.5 — Buy Now Button style ─────────────────────────────────
		$this->start_controls_section(
			'section_style_buy_now',
			array(
				'label'     => esc_html__( 'Buy Now Button', 'woo-swatches-elementor' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_buy_now' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'buy_now_typography',
				'selector' => '{{WRAPPER}} .wse-buy-now-btn',
			)
		);

		$this->start_controls_tabs( 'tabs_buy_now_style' );

		$this->start_controls_tab(
			'tab_buy_now_normal',
			array( 'label' => esc_html__( 'Normal', 'woo-swatches-elementor' ) )
		);
		$this->add_control(
			'buy_now_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-buy-now-btn' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'buy_now_bg_color',
			array(
				'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-buy-now-btn' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_buy_now_hover',
			array( 'label' => esc_html__( 'Hover', 'woo-swatches-elementor' ) )
		);
		$this->add_control(
			'buy_now_text_color_hover',
			array(
				'label'     => esc_html__( 'Text Color', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-buy-now-btn:hover' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'buy_now_bg_color_hover',
			array(
				'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wse-buy-now-btn:hover' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'buy_now_border',
				'selector' => '{{WRAPPER}} .wse-buy-now-btn',
			)
		);

		$this->add_responsive_control(
			'buy_now_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-buy-now-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'buy_now_padding',
			array(
				'label'      => esc_html__( 'Padding', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-buy-now-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'buy_now_margin_top',
			array(
				'label'      => esc_html__( 'Spacing Above', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 10 ),
				'selectors'  => array(
					'{{WRAPPER}} .wse-buy-now-wrap' => 'margin-top: {{SIZE}}{{UNIT}};',
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
		// v1.2.1 (F2) — sticky settings moved to global admin: WC -> Settings
		// -> WooSwatches -> Sticky Add to Cart. Reads global option directly
		// so every product page enforces the same store-wide sticky rule.
		if ( 'yes' === get_option( 'wse_sticky_desktop', 'no' ) ) {
			$wrapper_class .= ' wse-sticky-desktop';
		}
		if ( 'yes' === get_option( 'wse_sticky_tablet', 'no' ) ) {
			$wrapper_class .= ' wse-sticky-tablet';
		}
		if ( 'yes' === get_option( 'wse_sticky_mobile', 'yes' ) ) {
			$wrapper_class .= ' wse-sticky-mobile';
		}

		// v1.4.7 — Sticky compact layout + button order classes.
		if ( ( $settings['sticky_compact_layout'] ?? 'yes' ) === 'yes' ) {
			$wrapper_class .= ' wse-sticky-compact';
		}
		$sticky_btn_order = $settings['sticky_button_order'] ?? 'atc_bn';
		if ( 'bn_atc' === $sticky_btn_order ) {
			$wrapper_class .= ' wse-sticky-order-bn-atc';
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
