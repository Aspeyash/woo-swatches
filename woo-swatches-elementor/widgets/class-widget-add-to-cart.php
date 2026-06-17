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

		$template = $this->locate_template( $type );

		if ( ! $template ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				printf(
					'<p class="wse-editor-placeholder">%s</p>',
					esc_html__( 'Template not found.', 'woo-swatches-elementor' )
				);
			}
			return;
		}

		// ── Full-width button class ───────────────────────────────────────
		$wrapper_class = 'wse-widget-add-to-cart';
		if ( 'yes' === ( $settings['button_full_width'] ?? 'no' ) ) {
			$wrapper_class .= ' wse-atc-full-width';
		}

		$this->add_render_attribute( 'wrapper', array(
			'class'           => $wrapper_class,
			'data-product-id' => absint( $product_id ),
		) );
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
		var showQty  = settings.show_quantity === 'yes';
		var btnText  = settings.button_text || '<?php echo esc_js( __( 'Add to cart', 'woo-swatches-elementor' ) ); ?>';
		var fullW    = settings.button_full_width === 'yes';
		var qty      = settings.default_quantity || 1;
		#>
		<div class="wse-widget-add-to-cart{{ fullW ? ' wse-atc-full-width' : '' }} wse-editor-preview">
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
