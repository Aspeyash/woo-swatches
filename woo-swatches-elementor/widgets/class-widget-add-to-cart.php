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

		// ── v1.1.0 — Behavior section (Presenter Mode + Sticky toggles) ──
		$this->start_controls_section(
			'section_behavior',
			array(
				'label' => esc_html__( 'Behavior', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'presenter_mode',
			array(
				'label'        => esc_html__( 'Presenter Mode', 'woo-swatches-elementor' ),
				'description'  => esc_html__( 'Enable on a secondary instance (sticky bar / quick-view). The widget will share the form of the primary Add-to-Cart widget on the same page instead of rendering its own.', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On',  'woo-swatches-elementor' ),
				'label_off'    => esc_html__( 'Off', 'woo-swatches-elementor' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'sticky_heading',
			array(
				'label'     => esc_html__( 'Sticky Behavior (per device)', 'woo-swatches-elementor' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'presenter_mode' => 'yes' ),
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
				'condition'    => array( 'presenter_mode' => 'yes' ),
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
				'condition'    => array( 'presenter_mode' => 'yes' ),
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
				'condition'    => array( 'presenter_mode' => 'yes' ),
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

			// Feature B — per-device sticky classes
			if ( 'yes' === ( $settings['sticky_desktop'] ?? 'no' ) ) {
				$wrapper_class .= ' wse-sticky-desktop';
			}
			if ( 'yes' === ( $settings['sticky_tablet']  ?? 'no' ) ) {
				$wrapper_class .= ' wse-sticky-tablet';
			}
			if ( 'yes' === ( $settings['sticky_mobile']  ?? 'no' ) ) {
				$wrapper_class .= ' wse-sticky-mobile';
			}
		}

		$this->add_render_attribute( 'wrapper', array(
			'class'           => $wrapper_class,
			'data-product-id' => absint( $product_id ),
			'data-form-id'    => $canonical_form_id,
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
