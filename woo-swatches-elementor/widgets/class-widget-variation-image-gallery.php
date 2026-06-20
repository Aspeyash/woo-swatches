<?php
/**
 * Elementor Widget 4 — ZYMARG Variation Image Gallery (v1.3.0)
 *
 * Renders a product image gallery as a fully configurable, standalone
 * Elementor widget that stays in sync with Widget 1's swatch selection
 * via the canonical form's variation events. Built on the same canonical-
 * form coordination pattern as Widget 3 (ZYMARG Price), so multiple
 * gallery instances + multiple price instances + the swatches widget all
 * share the same event bus on a single product page.
 *
 * Layout system (5 desktop layouts × 3 breakpoints + per-device thumb
 * toggles). See the v1.3.0 plan/changelog for the full layout catalogue
 * and industry-research-backed defaults.
 *
 * Variation sync: each variation's image set (variation thumb + parent
 * gallery, falling back through variation→parent→placeholder) is computed
 * server-side and embedded as data-variation-images JSON on the widget
 * root. JS reads it, subscribes to the canonical form's found_variation /
 * reset_data events, cross-fades thumbs + main image on selection change.
 * No AJAX round-trip.
 *
 * Widget identity:
 *   Slug    : zymarg-variation-image-gallery
 *   Title   : ZYMARG Variation Image Gallery
 *   Author  : ZYMARG
 *   Version : 1.3.0 (introduced)
 *
 * @package WooSwatchesElementor
 * @since   1.3.0
 */

defined( 'ABSPATH' ) || exit;

class WSE_Widget_Variation_Image_Gallery extends \Elementor\Widget_Base {

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected string $widget_slug = 'zymarg-variation-image-gallery';

	/**
	 * Request-scoped cache for validated safe template paths.
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
		return esc_html__( 'ZYMARG Variation Image Gallery', 'woo-swatches-elementor' );
	}

	public function get_icon(): string {
		return 'eicon-images-grid';
	}

	public function get_categories(): array {
		return array( 'woo-swatches-elementor' );
	}

	public function get_keywords(): array {
		return array(
			'zymarg', 'woo', 'woocommerce', 'gallery', 'images',
			'variation', 'variable', 'product', 'thumbnail', 'lightbox',
			'zoom', 'carousel',
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Asset dependencies (Gap 21)
	// ─────────────────────────────────────────────────────────────────────

	public function get_script_depends(): array {
		return array( WSE_Assets::handle( 'gallery_js' ) );
	}

	public function get_style_depends(): array {
		return array(
			WSE_Assets::handle( 'gallery_css' ),
			WSE_Assets::handle( 'gallery_lightbox_css' ),
		);
	}

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
		$this->register_section_product();
		$this->register_section_layout();
		$this->register_section_display();
		$this->register_section_image();
		$this->register_section_variation_sync();
		$this->register_section_animation();

		// Phase 5 — Style tab sections.
		$this->register_section_style_main_image();
		$this->register_section_style_thumbs();
		$this->register_section_style_zoom_lens();
		$this->register_section_style_sale_badge();
		$this->register_section_style_dots();
		$this->register_section_style_counter();   // v1.3.2 (F6)
		$this->register_section_style_lightbox();
	}

	private function register_section_product(): void {
		$this->start_controls_section(
			'section_product',
			array(
				'label' => esc_html__( 'Product', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

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
	}

	private function register_section_layout(): void {

		$this->start_controls_section(
			'section_layout',
			array(
				'label' => esc_html__( 'Layout', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$layout_options = array(
			'vertical_left'    => esc_html__( 'Vertical thumbs LEFT + main right (default desktop, Apple / Nike / Sephora pattern)', 'woo-swatches-elementor' ),
			'vertical_right'   => esc_html__( 'Vertical thumbs RIGHT + main left',                                                    'woo-swatches-elementor' ),
			'horizontal_below' => esc_html__( 'Horizontal thumbs BELOW main (Adidas / Zara / WC default pattern)',                    'woo-swatches-elementor' ),
			'horizontal_above' => esc_html__( 'Horizontal thumbs ABOVE main',                                                          'woo-swatches-elementor' ),
			'stacked'          => esc_html__( 'Stacked vertical (Allbirds / H&M minimal pattern)',                                     'woo-swatches-elementor' ),
			'grid'             => esc_html__( 'Grid 2-column (Shopify Dawn pattern)',                                                  'woo-swatches-elementor' ),
		);

		$this->add_control(
			'desktop_layout',
			array(
				'label'   => esc_html__( 'Desktop layout', 'woo-swatches-elementor' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'vertical_left',
				'options' => $layout_options,
			)
		);

		$this->add_control(
			'tablet_layout',
			array(
				'label'   => esc_html__( 'Tablet layout', 'woo-swatches-elementor' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'horizontal_below',
				'options' => $layout_options,
			)
		);

		$this->add_control(
			'mobile_layout',
			array(
				'label'   => esc_html__( 'Mobile layout', 'woo-swatches-elementor' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				// Default: swipeable carousel + stacked vertical thumbs below
				// (per ZYMARG product spec — gives mobile users both swipe AND
				// scroll-to-thumb).
				'default' => 'mobile_carousel',
				'options' => array(
					'mobile_carousel'  => esc_html__( 'Swipe carousel + stacked thumbs below (default mobile)', 'woo-swatches-elementor' ),
					'mobile_stacked'   => esc_html__( 'Stacked vertical (no carousel — long scroll feed)',     'woo-swatches-elementor' ),
					'horizontal_below' => esc_html__( 'Horizontal thumbs below main',                            'woo-swatches-elementor' ),
					'horizontal_above' => esc_html__( 'Horizontal thumbs above main',                            'woo-swatches-elementor' ),
				),
			)
		);

		// Per-device thumb visibility toggles. Adds prefix-class on the
		// widget root that CSS uses inside each breakpoint's @media query.
		$this->add_control(
			'show_thumbs_desktop',
			array(
				'label'        => esc_html__( 'Show thumbnails — Desktop', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'prefix_class' => 'zymarg-vig-thumbs-d-',
			)
		);
		$this->add_control(
			'show_thumbs_tablet',
			array(
				'label'        => esc_html__( 'Show thumbnails — Tablet (≤1024px)', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'prefix_class' => 'zymarg-vig-thumbs-t-',
			)
		);
		$this->add_control(
			'show_thumbs_mobile',
			array(
				'label'        => esc_html__( 'Show thumbnails — Mobile (≤768px)', 'woo-swatches-elementor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'prefix_class' => 'zymarg-vig-thumbs-m-',
			)
		);

		$this->add_responsive_control(
			'thumbs_per_view',
			array(
				'label'      => esc_html__( 'Thumbnails visible at once', 'woo-swatches-elementor' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'min' => 2, 'max' => 12, 'step' => 1 ) ),
				'default'        => array( 'unit' => 'px', 'size' => 5 ),
				'tablet_default' => array( 'unit' => 'px', 'size' => 4 ),
				'mobile_default' => array( 'unit' => 'px', 'size' => 4 ),
				'description' => esc_html__( 'Used as a CSS hint via --zymarg-vig-thumbs-per-view; behaviour varies per layout.', 'woo-swatches-elementor' ),
				'selectors'  => array(
					'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-thumbs-per-view: {{SIZE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_section_display(): void {

		$this->start_controls_section(
			'section_display',
			array(
				'label' => esc_html__( 'Display', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control( 'show_zoom', array(
			'label'        => esc_html__( 'Show hover-zoom lens (desktop)', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'show_lightbox', array(
			'label'        => esc_html__( 'Click main image → lightbox', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'show_arrows', array(
			'label'        => esc_html__( 'Show arrows', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'show_dots', array(
			'label'        => esc_html__( 'Show dots (mobile carousel)', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		// v1.3.2 (F6) — Image counter overlay (mobile + tablet only).
		$this->add_control( 'show_image_counter', array(
			'label'        => esc_html__( 'Show image counter (mobile / tablet)', 'woo-swatches-elementor' ),
			'description'  => esc_html__( 'Renders a small overlay at bottom-left of the main image with the current/total slide count. Shown only at tablet (≤1024px) and mobile (≤768px) breakpoints — desktop has thumbs visible so the counter would be redundant.', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'image_counter_format', array(
			'label'       => esc_html__( 'Counter format', 'woo-swatches-elementor' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '{current} / {total}',
			'description' => esc_html__( 'Use {current} and {total} as placeholders. Examples: "1 / 3", "Image 1 of 3", "1 of 3 photos".', 'woo-swatches-elementor' ),
			'condition'   => array( 'show_image_counter' => 'yes' ),
		) );

		$this->add_control( 'show_video_indicator', array(
			'label'        => esc_html__( 'Play-icon overlay on video thumbnails', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'show_sale_badge', array(
			'label'        => esc_html__( 'Sale badge overlay on main image', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'sale_badge_text', array(
			'label'     => esc_html__( 'Sale badge text', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => esc_html__( 'Sale', 'woo-swatches-elementor' ),
			'condition' => array( 'show_sale_badge' => 'yes' ),
		) );

		$this->add_control( 'sticky_main_desktop', array(
			'label'        => esc_html__( 'Sticky main image (desktop)', 'woo-swatches-elementor' ),
			'description'  => esc_html__( 'Keeps the main image in viewport while the customer scrolls the description (Apple / Sephora pattern).', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'prefix_class' => 'zymarg-vig-sticky-',
		) );

		$this->add_control( 'lazy_load_thumbs', array(
			'label'        => esc_html__( 'Lazy-load thumbnails below the fold', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->end_controls_section();
	}

	private function register_section_image(): void {

		$this->start_controls_section(
			'section_image',
			array(
				'label' => esc_html__( 'Image', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control( 'aspect_ratio', array(
			'label'   => esc_html__( 'Aspect ratio', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => '1-1',
			'options' => array(
				'1-1'  => esc_html__( '1:1 Square (industry default)', 'woo-swatches-elementor' ),
				'4-5'  => esc_html__( '4:5 Portrait',                  'woo-swatches-elementor' ),
				'3-4'  => esc_html__( '3:4 Portrait classic',          'woo-swatches-elementor' ),
				'16-9' => esc_html__( '16:9 Landscape',                'woo-swatches-elementor' ),
				'auto' => esc_html__( 'Auto (use original)',           'woo-swatches-elementor' ),
			),
			'prefix_class' => 'zymarg-vig-ar-',
		) );

		$this->add_control( 'image_size', array(
			'label'   => esc_html__( 'Main image size', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'woocommerce_single',
			'options' => $this->get_registered_image_sizes(),
		) );

		$this->add_control( 'thumb_size', array(
			'label'   => esc_html__( 'Thumbnail image size', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'woocommerce_gallery_thumbnail',
			'options' => $this->get_registered_image_sizes(),
		) );

		$this->add_control( 'placeholder_color', array(
			'label'     => esc_html__( 'Placeholder color (while image loads)', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#eaedff',
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-placeholder-bg: {{VALUE}};',
			),
		) );

		$this->end_controls_section();
	}

	private function register_section_variation_sync(): void {

		$this->start_controls_section(
			'section_variation_sync',
			array(
				'label' => esc_html__( 'Variation Sync', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control( 'sync_with_widget_1', array(
			'label'        => esc_html__( 'Swap gallery on swatch click', 'woo-swatches-elementor' ),
			'description'  => esc_html__( 'When the customer picks a swatch in the ZYMARG Variation Swatches widget, swap to the matched variation\'s images.', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'fallback_to_parent_gallery', array(
			'label'        => esc_html__( 'Fall back to parent gallery for variations without their own image set', 'woo-swatches-elementor' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'condition'    => array( 'sync_with_widget_1' => 'yes' ),
		) );

		$this->end_controls_section();
	}

	private function register_section_animation(): void {

		$this->start_controls_section(
			'section_animation',
			array(
				'label' => esc_html__( 'Animation', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control( 'transition_type', array(
			'label'   => esc_html__( 'Transition type', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'fade',
			'options' => array(
				'fade'  => esc_html__( 'Cross-fade (Apple / Sephora pattern, recommended)', 'woo-swatches-elementor' ),
				'slide' => esc_html__( 'Slide left-to-right',                                'woo-swatches-elementor' ),
				'none'  => esc_html__( 'Instant swap',                                       'woo-swatches-elementor' ),
			),
		) );

		$this->add_control( 'transition_duration_ms', array(
			'label'      => esc_html__( 'Duration (ms)', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 800, 'step' => 25 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 200 ),
			'description' => esc_html__( 'Within 500ms a swap doesn\'t count toward Cumulative Layout Shift, so keep ≤ 250ms for the perceived-quality sweet spot.', 'woo-swatches-elementor' ),
			'selectors'  => array(
				'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-transition-ms: {{SIZE}};',
			),
		) );

		$this->end_controls_section();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Phase 5 — Style tab sections
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Style → Main Image.
	 *
	 * Targets the hero image figure (.zymarg-vig-main) and the <img> inside
	 * (.zymarg-vig-main-img). Padding, radius, border, shadow apply to the
	 * figure wrapper; object-fit applies to the inner image.
	 */
	private function register_section_style_main_image(): void {

		$this->start_controls_section(
			'section_style_main_image',
			array(
				'label' => esc_html__( 'Main Image', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control( 'main_padding', array(
			'label'      => esc_html__( 'Padding', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array(
				'{{WRAPPER}} .zymarg-vig-main' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->add_control( 'main_radius_px', array(
			'label'      => esc_html__( 'Border radius', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 64, 'step' => 1 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 8 ),
			'description' => esc_html__( 'Sets --zymarg-vig-radius (used by both main image and thumbs at 75%% scale).', 'woo-swatches-elementor' ),
			'selectors'  => array(
				'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-radius: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'main_border',
				'selector' => '{{WRAPPER}} .zymarg-vig-main',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'main_shadow',
				'selector' => '{{WRAPPER}} .zymarg-vig-main',
			)
		);

		$this->add_control( 'main_object_fit', array(
			'label'   => esc_html__( 'Image fit', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'cover',
			'options' => array(
				'cover'   => esc_html__( 'Cover (crop to fill — industry default)', 'woo-swatches-elementor' ),
				'contain' => esc_html__( 'Contain (fit inside — show full image)',   'woo-swatches-elementor' ),
			),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-main-img' => 'object-fit: {{VALUE}};',
			),
		) );

		$this->end_controls_section();
	}

	/**
	 * Style → Thumbnails.
	 *
	 * Tabs Normal / Hover / Active so each state can be styled separately.
	 * Active state writes to --zymarg-vig-active-color / --active-width
	 * which are also used by the zoom lens border.
	 */
	private function register_section_style_thumbs(): void {

		$this->start_controls_section(
			'section_style_thumbs',
			array(
				'label' => esc_html__( 'Thumbnails', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control( 'thumbs_size_px', array(
			'label'          => esc_html__( 'Thumbnail size', 'woo-swatches-elementor' ),
			'type'           => \Elementor\Controls_Manager::SLIDER,
			'range'          => array( 'px' => array( 'min' => 32, 'max' => 200, 'step' => 1 ) ),
			'default'        => array( 'unit' => 'px', 'size' => 72 ),
			'tablet_default' => array( 'unit' => 'px', 'size' => 64 ),
			'mobile_default' => array( 'unit' => 'px', 'size' => 56 ),
			'selectors'      => array(
				'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-thumbs-size: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_responsive_control( 'thumbs_gap_px', array(
			'label'          => esc_html__( 'Gap between thumbs', 'woo-swatches-elementor' ),
			'type'           => \Elementor\Controls_Manager::SLIDER,
			'range'          => array( 'px' => array( 'min' => 0, 'max' => 32, 'step' => 1 ) ),
			'default'        => array( 'unit' => 'px', 'size' => 8 ),
			'mobile_default' => array( 'unit' => 'px', 'size' => 6 ),
			'selectors'      => array(
				'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-thumbs-gap: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_control( 'thumbs_radius', array(
			'label'      => esc_html__( 'Thumb border radius', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 64, 'step' => 1 ), '%' => array( 'min' => 0, 'max' => 50, 'step' => 1 ) ),
			'size_units' => array( 'px', '%' ),
			'default'    => array( 'unit' => 'px', 'size' => 6 ),
			'selectors'  => array(
				'{{WRAPPER}} .zymarg-vig-thumb, {{WRAPPER}} .zymarg-vig-thumb-img' => 'border-radius: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->start_controls_tabs( 'tabs_thumb_states' );

		// ── Normal ────────────────────────────────────────────────────────
		$this->start_controls_tab( 'tab_thumb_normal', array(
			'label' => esc_html__( 'Normal', 'woo-swatches-elementor' ),
		) );

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'thumb_border',
				'selector' => '{{WRAPPER}} .zymarg-vig-thumb',
			)
		);

		$this->add_control( 'thumb_opacity', array(
			'label'   => esc_html__( 'Opacity', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 0.2, 'max' => 1, 'step' => 0.05 ) ),
			'default' => array( 'unit' => 'px', 'size' => 1 ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-thumb' => 'opacity: {{SIZE}};',
			),
		) );

		$this->end_controls_tab();

		// ── Hover ─────────────────────────────────────────────────────────
		$this->start_controls_tab( 'tab_thumb_hover', array(
			'label' => esc_html__( 'Hover', 'woo-swatches-elementor' ),
		) );

		$this->add_control( 'thumb_hover_border_color', array(
			'label'   => esc_html__( 'Hover border color', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => '#bd00d1',
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-thumb:hover, {{WRAPPER}} .zymarg-vig-thumb:focus-visible' => 'border-color: {{VALUE}};',
			),
		) );

		$this->add_control( 'thumb_hover_opacity', array(
			'label'   => esc_html__( 'Hover opacity', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 0.2, 'max' => 1, 'step' => 0.05 ) ),
			'default' => array( 'unit' => 'px', 'size' => 1 ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-thumb:hover' => 'opacity: {{SIZE}};',
			),
		) );

		$this->add_control( 'thumb_hover_lift_px', array(
			'label'   => esc_html__( 'Hover lift (px)', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 0, 'max' => 8, 'step' => 1 ) ),
			'default' => array( 'unit' => 'px', 'size' => 0 ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-thumb:hover' => 'transform: translateY(-{{SIZE}}{{UNIT}});',
			),
		) );

		$this->end_controls_tab();

		// ── Active ────────────────────────────────────────────────────────
		$this->start_controls_tab( 'tab_thumb_active', array(
			'label' => esc_html__( 'Active', 'woo-swatches-elementor' ),
		) );

		$this->add_control( 'thumb_active_color', array(
			'label'   => esc_html__( 'Active border color', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => '#9500a5',
			'description' => esc_html__( 'ZYMARG Primary by default. Also used by the zoom lens border.', 'woo-swatches-elementor' ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-active-color: {{VALUE}};',
			),
		) );

		$this->add_control( 'thumb_active_width', array(
			'label'   => esc_html__( 'Active border width', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
			'default' => array( 'unit' => 'px', 'size' => 2 ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-active-width: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_control( 'thumb_active_opacity', array(
			'label'   => esc_html__( 'Active opacity', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 0.2, 'max' => 1, 'step' => 0.05 ) ),
			'default' => array( 'unit' => 'px', 'size' => 1 ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-thumb.is-active' => 'opacity: {{SIZE}};',
			),
		) );

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Style → Zoom Lens.
	 *
	 * Visible only when Display → "Show hover-zoom lens" is on. Size is
	 * exposed as the CSS var --zymarg-vig-lens-size which gallery.js reads
	 * back; the rest are pure CSS overrides on .zymarg-vig-zoom-lens.
	 */
	private function register_section_style_zoom_lens(): void {

		$this->start_controls_section(
			'section_style_zoom_lens',
			array(
				'label'     => esc_html__( 'Zoom Lens', 'woo-swatches-elementor' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_zoom' => 'yes' ),
			)
		);

		$this->add_control( 'lens_size_px', array(
			'label'   => esc_html__( 'Lens size', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 40, 'max' => 240, 'step' => 4 ) ),
			'default' => array( 'unit' => 'px', 'size' => 80 ),
			'description' => esc_html__( 'Read by gallery.js via --zymarg-vig-lens-size.', 'woo-swatches-elementor' ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-lens-size: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_control( 'lens_shape', array(
			'label'   => esc_html__( 'Lens shape', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'square',
			'options' => array(
				'square' => esc_html__( 'Rounded square', 'woo-swatches-elementor' ),
				'circle' => esc_html__( 'Circle',         'woo-swatches-elementor' ),
			),
			'selectors_dictionary' => array(
				'square' => '4px',
				'circle' => '50%',
			),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-zoom-lens' => 'border-radius: {{VALUE}};',
			),
		) );

		$this->add_control( 'lens_bg', array(
			'label'   => esc_html__( 'Lens background', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => 'rgba(255,255,255,0.15)',
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-zoom-lens' => 'background: {{VALUE}};',
			),
		) );

		$this->add_control( 'lens_border_color', array(
			'label'   => esc_html__( 'Lens border color', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => '',
			'description' => esc_html__( 'Leave empty to inherit from active color.', 'woo-swatches-elementor' ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-zoom-lens' => 'border-color: {{VALUE}};',
			),
		) );

		$this->add_control( 'lens_border_width', array(
			'label'   => esc_html__( 'Lens border width', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 0, 'max' => 4, 'step' => 1 ) ),
			'default' => array( 'unit' => 'px', 'size' => 1 ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-zoom-lens' => 'border-width: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->end_controls_section();
	}

	/**
	 * Style → Sale Badge.
	 *
	 * Visible only when Display → "Sale badge overlay" is on. Position
	 * uses prefix-class on the widget wrapper to switch corners.
	 */
	private function register_section_style_sale_badge(): void {

		$this->start_controls_section(
			'section_style_sale_badge',
			array(
				'label'     => esc_html__( 'Sale Badge', 'woo-swatches-elementor' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_sale_badge' => 'yes' ),
			)
		);

		$this->add_control( 'sale_badge_position', array(
			'label'   => esc_html__( 'Position', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'top_left',
			'options' => array(
				'top_left'     => esc_html__( 'Top left',     'woo-swatches-elementor' ),
				'top_right'    => esc_html__( 'Top right',    'woo-swatches-elementor' ),
				'bottom_left'  => esc_html__( 'Bottom left',  'woo-swatches-elementor' ),
				'bottom_right' => esc_html__( 'Bottom right', 'woo-swatches-elementor' ),
			),
			'prefix_class' => 'zymarg-vig-badge-',
		) );

		$this->add_control( 'sale_badge_bg', array(
			'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#9500a5',
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-sale-badge' => 'background: {{VALUE}};',
			),
		) );

		$this->add_control( 'sale_badge_color', array(
			'label'     => esc_html__( 'Text color', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-sale-badge' => 'color: {{VALUE}};',
			),
		) );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'sale_badge_typography',
				'selector' => '{{WRAPPER}} .zymarg-vig-sale-badge',
			)
		);

		$this->add_responsive_control( 'sale_badge_padding', array(
			'label'      => esc_html__( 'Padding', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array(
				'{{WRAPPER}} .zymarg-vig-sale-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->add_control( 'sale_badge_radius', array(
			'label'      => esc_html__( 'Border radius', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 32, 'step' => 1 ), '%' => array( 'min' => 0, 'max' => 50, 'step' => 1 ) ),
			'size_units' => array( 'px', '%' ),
			'default'    => array( 'unit' => 'px', 'size' => 4 ),
			'selectors'  => array(
				'{{WRAPPER}} .zymarg-vig-sale-badge' => 'border-radius: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'sale_badge_shadow',
				'selector' => '{{WRAPPER}} .zymarg-vig-sale-badge',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Carousel Dots (mobile).
	 *
	 * Always available so it shows up even on desktop preview. The dots
	 * themselves only appear at mobile bp + show_dots = yes (CSS-gated).
	 */
	private function register_section_style_dots(): void {

		$this->start_controls_section(
			'section_style_dots',
			array(
				'label' => esc_html__( 'Carousel Dots (mobile)', 'woo-swatches-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control( 'dot_size', array(
			'label'   => esc_html__( 'Dot size', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 4, 'max' => 16, 'step' => 1 ) ),
			'default' => array( 'unit' => 'px', 'size' => 8 ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-dot-size: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_control( 'dot_gap', array(
			'label'   => esc_html__( 'Gap between dots', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 2, 'max' => 16, 'step' => 1 ) ),
			'default' => array( 'unit' => 'px', 'size' => 6 ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-dots' => 'gap: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_control( 'dot_color', array(
			'label'   => esc_html__( 'Dot color', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => '#d8bfd3',
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-dot-color: {{VALUE}};',
			),
		) );

		$this->add_control( 'dot_active_color', array(
			'label'   => esc_html__( 'Active dot color', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => '#9500a5',
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig' => '--zymarg-vig-dot-active-color: {{VALUE}};',
			),
		) );

		$this->end_controls_section();
	}

	/**
	 * Style → Lightbox.
	 *
	 * The lightbox is a body-level shared modal injected by gallery.js, so
	 * we scope styles by .zymarg-vig-lightbox (no {{WRAPPER}} prefix would
	 * apply globally; we keep WRAPPER for parity since multiple gallery
	 * instances per page is rare and Elementor will inject its own
	 * specificity).
	 */
	/**
	 * v1.3.2 (F6) — Style → Image Counter (mobile / tablet overlay).
	 *
	 * Targets the .zymarg-vig-counter span rendered inside .zymarg-vig-main-wrap.
	 * Visibility is gated by show_image_counter (Display section) plus a
	 * @media query in CSS that hides the counter on desktop.
	 */
	private function register_section_style_counter(): void {

		$this->start_controls_section(
			'section_style_counter',
			array(
				'label'     => esc_html__( 'Image Counter', 'woo-swatches-elementor' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_image_counter' => 'yes' ),
			)
		);

		$this->add_control( 'counter_bg', array(
			'label'     => esc_html__( 'Background', 'woo-swatches-elementor' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => 'rgba(19,27,46,0.75)',
			'description' => esc_html__( 'ZYMARG Text Body at 75%% opacity by default.', 'woo-swatches-elementor' ),
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-counter' => 'background: {{VALUE}};',
			),
		) );

		$this->add_control( 'counter_color', array(
			'label'   => esc_html__( 'Text color', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => '#ffffff',
			'selectors' => array(
				'{{WRAPPER}} .zymarg-vig-counter' => 'color: {{VALUE}};',
			),
		) );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'counter_typography',
				'selector' => '{{WRAPPER}} .zymarg-vig-counter',
			)
		);

		$this->add_control( 'counter_padding', array(
			'label'      => esc_html__( 'Padding', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array(
				'{{WRAPPER}} .zymarg-vig-counter' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->add_control( 'counter_radius', array(
			'label'      => esc_html__( 'Border radius', 'woo-swatches-elementor' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 32, 'step' => 1 ), '%' => array( 'min' => 0, 'max' => 50, 'step' => 1 ) ),
			'size_units' => array( 'px', '%' ),
			'default'    => array( 'unit' => 'px', 'size' => 4 ),
			'selectors'  => array(
				'{{WRAPPER}} .zymarg-vig-counter' => 'border-radius: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_control( 'counter_position', array(
			'label'   => esc_html__( 'Position', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'bottom_left',
			'options' => array(
				'bottom_left'  => esc_html__( 'Bottom left (default)', 'woo-swatches-elementor' ),
				'bottom_right' => esc_html__( 'Bottom right',         'woo-swatches-elementor' ),
				'top_left'     => esc_html__( 'Top left',             'woo-swatches-elementor' ),
				'top_right'    => esc_html__( 'Top right',            'woo-swatches-elementor' ),
			),
			'prefix_class' => 'zymarg-vig-counter-pos-',
		) );

		$this->end_controls_section();
	}

	private function register_section_style_lightbox(): void {

		$this->start_controls_section(
			'section_style_lightbox',
			array(
				'label'     => esc_html__( 'Lightbox', 'woo-swatches-elementor' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_lightbox' => 'yes' ),
			)
		);

		$this->add_control( 'lightbox_backdrop', array(
			'label'   => esc_html__( 'Backdrop color', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => 'rgba(19,27,46,0.92)',
			'description' => esc_html__( 'ZYMARG Text Body at 92%% opacity by default.', 'woo-swatches-elementor' ),
			'selectors' => array(
				'.zymarg-vig-lightbox' => 'background: {{VALUE}};',
			),
		) );

		$this->add_control( 'lightbox_btn_color', array(
			'label'   => esc_html__( 'Close / arrow icon color', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => '#ffffff',
			'selectors' => array(
				'.zymarg-vig-lb-close, .zymarg-vig-lb-arrow' => 'color: {{VALUE}};',
			),
		) );

		$this->add_control( 'lightbox_btn_bg', array(
			'label'   => esc_html__( 'Close / arrow background', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => 'rgba(255,255,255,0.12)',
			'selectors' => array(
				'.zymarg-vig-lb-close, .zymarg-vig-lb-arrow' => 'background: {{VALUE}};',
			),
		) );

		$this->add_control( 'lightbox_btn_hover_bg', array(
			'label'   => esc_html__( 'Close / arrow hover background', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => 'rgba(255,255,255,0.24)',
			'selectors' => array(
				'.zymarg-vig-lb-close:hover, .zymarg-vig-lb-arrow:hover, .zymarg-vig-lb-close:focus-visible, .zymarg-vig-lb-arrow:focus-visible' => 'background: {{VALUE}};',
			),
		) );

		$this->add_control( 'lightbox_counter_color', array(
			'label'   => esc_html__( 'Counter color', 'woo-swatches-elementor' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => 'rgba(255,255,255,0.85)',
			'selectors' => array(
				'.zymarg-vig-lb-counter' => 'color: {{VALUE}};',
			),
		) );

		$this->end_controls_section();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns a label-value array of all registered image sizes.
	 * Used by the image_size and thumb_size controls.
	 *
	 * @return array<string,string>
	 */
	private function get_registered_image_sizes(): array {
		$sizes = array();
		foreach ( get_intermediate_image_sizes() as $size ) {
			$sizes[ $size ] = $size;
		}
		$sizes['full'] = esc_html__( 'Full (original)', 'woo-swatches-elementor' );
		return $sizes;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Render
	// ─────────────────────────────────────────────────────────────────────

	protected function render(): void {

		$settings   = $this->get_settings_for_display();
		$product_id = $this->resolve_product_id( $settings );
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product instanceof \WC_Product ) {
			$this->render_no_product_placeholder();
			return;
		}

		// Build variation→images map. Key '0' = parent / no variation matched.
		$image_size = sanitize_key( $settings['image_size'] ?? 'woocommerce_single' );
		$thumb_size = sanitize_key( $settings['thumb_size'] ?? 'woocommerce_gallery_thumbnail' );

		$images_map = $this->build_variation_images_map( $product, $image_size, $thumb_size );
		$current    = $images_map['0'] ?? array();

		if ( empty( $current ) ) {
			$this->render_no_product_placeholder();
			return;
		}

		// ── Wrapper class assembly ────────────────────────────────────────
		$desktop_layout = sanitize_html_class( $settings['desktop_layout'] ?? 'vertical_left' );
		$tablet_layout  = sanitize_html_class( $settings['tablet_layout']  ?? 'horizontal_below' );
		$mobile_layout  = sanitize_html_class( $settings['mobile_layout']  ?? 'mobile_carousel' );

		$wrapper_classes = array(
			'zymarg-vig',
			'zymarg-vig--layout-d-' . $desktop_layout,
			'zymarg-vig--layout-t-' . $tablet_layout,
			'zymarg-vig--layout-m-' . $mobile_layout,
			'zymarg-vig--transition-' . sanitize_html_class( $settings['transition_type'] ?? 'fade' ),

			// v1.3.4 — Mirror prefix_class-driven controls onto the inner
			// .zymarg-vig div. Elementor's prefix_class attribute lands the
			// class on its OUTER widget wrapper (.elementor-widget-…), not
			// on .zymarg-vig — so compound CSS selectors like
			// `.zymarg-vig.zymarg-vig-ar-16-9` never matched. Mirroring
			// the classes here makes the existing CSS rules work without
			// rewriting them as descendant selectors.
			'zymarg-vig-ar-'          . sanitize_html_class( (string) ( $settings['aspect_ratio']        ?? '1-1' ) ),
			'zymarg-vig-thumbs-d-'    . ( ( $settings['show_thumbs_desktop'] ?? 'yes' ) === 'yes' ? 'yes' : 'no' ),
			'zymarg-vig-thumbs-t-'    . ( ( $settings['show_thumbs_tablet']  ?? 'yes' ) === 'yes' ? 'yes' : 'no' ),
			'zymarg-vig-thumbs-m-'    . ( ( $settings['show_thumbs_mobile']  ?? 'yes' ) === 'yes' ? 'yes' : 'no' ),
			'zymarg-vig-sticky-'      . ( ( $settings['sticky_main_desktop'] ?? 'yes' ) === 'yes' ? 'yes' : 'no' ),
			'zymarg-vig-counter-pos-' . sanitize_html_class( (string) ( $settings['counter_position']    ?? 'bottom_left' ) ),
			'zymarg-vig-badge-'       . sanitize_html_class( (string) ( $settings['sale_badge_position'] ?? 'top_left' ) ),
		);

		// ── Form coordination data attrs ──────────────────────────────────
		$form_id = 'wse-form-' . $product->get_id();

		// Sale state — used by the main-image template's sale-badge overlay.
		$is_on_sale = $product->is_on_sale();

		// JSON-encode for the data attribute. Use wp_json_encode + wc_esc_json
		// when available (matches add-to-cart variable.php pattern).
		$variations_json = wp_json_encode( $images_map );
		$variations_attr = function_exists( 'wc_esc_json' )
			? wc_esc_json( $variations_json )
			: htmlspecialchars( (string) $variations_json, ENT_QUOTES, 'UTF-8' );

		?>
		<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"
			data-product-id="<?php echo absint( $product->get_id() ); ?>"
			data-form-id="<?php echo esc_attr( $form_id ); ?>"
			data-product-type="<?php echo esc_attr( $product->get_type() ); ?>"
			data-sync-enabled="<?php echo ( ( $settings['sync_with_widget_1'] ?? 'yes' ) === 'yes' ) ? '1' : '0'; ?>"
			data-fallback-parent="<?php echo ( ( $settings['fallback_to_parent_gallery'] ?? 'yes' ) === 'yes' ) ? '1' : '0'; ?>"
			data-variation-images="<?php echo $variations_attr; // phpcs:ignore ?>">

			<?php
			// Choose layout template based on desktop_layout (Phase 2 ships
			// vertical-thumbs only; the wrapper classes for tablet/mobile
			// are emitted so Phase 3 CSS can switch layouts at breakpoints
			// without re-rendering).
			$layout_template = $this->resolve_layout_template( $desktop_layout );

			$layout_args = array(
				'images'                  => $current,
				'active_index'            => 0,
				'show_zoom'               => 'yes' === ( $settings['show_zoom']        ?? 'yes' ),
				'show_lightbox'           => 'yes' === ( $settings['show_lightbox']    ?? 'yes' ),
				'show_sale_badge'         => 'yes' === ( $settings['show_sale_badge']  ?? 'yes' ),
				'sale_badge_text'         => (string) ( $settings['sale_badge_text']   ?? __( 'Sale', 'woo-swatches-elementor' ) ),
				'is_on_sale'              => $is_on_sale,
				'lazy_load_thumbs'        => 'yes' === ( $settings['lazy_load_thumbs'] ?? 'yes' ),
				'show_arrows'             => 'yes' === ( $settings['show_arrows']      ?? 'yes' ),
				'show_dots'               => 'yes' === ( $settings['show_dots']        ?? 'yes' ),
				// v1.3.2 (F4) — Render a horizontal scroll-snap carousel of
				// ALL images inside .zymarg-vig-main-wrap when the mobile
				// layout is mobile_carousel. CSS gates the carousel to mobile
				// bp only; on desktop/tablet the single .zymarg-vig-main hero
				// is shown instead. v1.3.0–v1.3.1 only rendered the single
				// hero, leaving nothing to swipe through on mobile.
				'mobile_carousel_enabled' => 'mobile_carousel' === $mobile_layout,
				// v1.3.2 (F6) — Image counter overlay format string. JS
				// substitutes {current} / {total} placeholders.
				'show_image_counter'      => 'yes' === ( $settings['show_image_counter']   ?? 'yes' ),
				'image_counter_format'    => (string) ( $settings['image_counter_format'] ?? '{current} / {total}' ),
			);

			echo self::include_template( $layout_template, $layout_args ); // phpcs:ignore WordPress.Security.EscapeOutput
			?>

		</div><!-- .zymarg-vig -->
		<?php
	}

	/**
	 * Resolves a layout key to a template path.
	 *
	 * Layout family routing:
	 *   vertical_left / vertical_right / horizontal_below / horizontal_above
	 *     → layouts/vertical-thumbs.php  (one template; CSS @media + parent
	 *       class drive the flex-direction differences within the family)
	 *   stacked → layouts/stacked.php  (no thumbs, all images stacked
	 *       full-size; Allbirds / H&M minimal pattern)
	 *   grid    → layouts/grid.php     (no thumbs, 2-col grid all images
	 *       at once; Shopify Dawn pattern)
	 *
	 * @param string $layout_key
	 * @return string
	 */
	private function resolve_layout_template( string $layout_key ): string {
		switch ( $layout_key ) {
			case 'stacked':
				return 'layouts/stacked.php';
			case 'grid':
				return 'layouts/grid.php';
			case 'vertical_left':
			case 'vertical_right':
			case 'horizontal_below':
			case 'horizontal_above':
			default:
				return 'layouts/vertical-thumbs.php';
		}
	}

	/**
	 * Resolves the product ID from the widget settings, falling back to the
	 * queried object on a single-product page.
	 *
	 * @param array<string, mixed> $settings
	 * @return int 0 if no product can be resolved.
	 */
	private function resolve_product_id( array $settings ): int {
		$id = absint( $settings['product_id'] ?? 0 );
		if ( $id > 0 ) {
			return $id;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			$queried = get_queried_object_id();
			if ( $queried ) {
				return absint( $queried );
			}
		}
		return 0;
	}

	/**
	 * Builds the variation→images mapping for a product.
	 *
	 * Returns:
	 *   [
	 *     '0'   => [ ...parent product images... ],
	 *     '123' => [ ...variation 123's images... ],
	 *     '456' => [ ...variation 456's images... ],
	 *   ]
	 *
	 * Each image entry has:
	 *   id, src, srcset, sizes, alt, width, height, thumb
	 *
	 * Variation image set composition (per ZYMARG product spec):
	 *   1. Variation's own image (variation->get_image_id())
	 *   2. Parent gallery images (get_gallery_image_ids() on the variable
	 *      product) — always shown so customers see all angles per variant
	 *   3. Plugin filter wse_variation_image_ids — lets 3rd-party plugins
	 *      like "WooCommerce Additional Variation Images" or "Smart
	 *      Variations Images" override with their own per-variation gallery
	 *
	 * @param  \WC_Product $product
	 * @param  string      $image_size
	 * @param  string      $thumb_size
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function build_variation_images_map(
		\WC_Product $product,
		string $image_size,
		string $thumb_size
	): array {

		$map = array();

		// ── Parent ('0') — featured + gallery ────────────────────────────
		$parent_ids = array();
		$featured = $product->get_image_id();
		if ( $featured ) {
			$parent_ids[] = $featured;
		}
		foreach ( $product->get_gallery_image_ids() as $gid ) {
			if ( ! in_array( $gid, $parent_ids, true ) ) {
				$parent_ids[] = $gid;
			}
		}
		if ( empty( $parent_ids ) ) {
			$parent_ids[] = 0; // placeholder
		}

		$map['0'] = array_map(
			fn( $id ) => $this->format_image_data( $id, $image_size, $thumb_size ),
			$parent_ids
		);

		// ── Variations — each gets variation-image + parent gallery ──────
		if ( $product instanceof \WC_Product_Variable ) {
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation instanceof \WC_Product_Variation ) {
					continue;
				}

				$ids = array();
				$var_main = $variation->get_image_id();
				if ( $var_main ) {
					$ids[] = $var_main;
				}
				foreach ( $product->get_gallery_image_ids() as $gid ) {
					if ( ! in_array( $gid, $ids, true ) ) {
						$ids[] = $gid;
					}
				}
				if ( empty( $ids ) && $featured ) {
					$ids[] = $featured;
				}

				/**
				 * Filter: wse_variation_image_ids
				 *
				 * Lets 3rd-party plugins (WooCommerce Additional Variation
				 * Images, Smart Variations Images, etc.) replace our default
				 * image-id list with their own per-variation gallery.
				 *
				 * @param int[]                       $ids       Default image IDs.
				 * @param int                         $variation_id
				 * @param \WC_Product_Variable        $product
				 */
				$ids = (array) apply_filters( 'wse_variation_image_ids', $ids, $variation_id, $product );

				if ( empty( $ids ) ) {
					$ids[] = 0; // placeholder
				}

				$map[ (string) $variation_id ] = array_map(
					fn( $id ) => $this->format_image_data( $id, $image_size, $thumb_size ),
					$ids
				);
			}
		}

		return $map;
	}

	/**
	 * Formats a single attachment ID into the image data structure consumed
	 * by main-image.php / thumbnail.php / gallery.js.
	 *
	 * @param  int    $attachment_id  0 means "use WC placeholder".
	 * @param  string $main_size
	 * @param  string $thumb_size
	 * @return array<string, mixed>
	 */
	private function format_image_data(
		int $attachment_id,
		string $main_size,
		string $thumb_size
	): array {

		if ( $attachment_id <= 0 ) {
			$placeholder = wc_placeholder_img_src();
			return array(
				'id'     => 0,
				'src'    => (string) $placeholder,
				'srcset' => '',
				'sizes'  => '',
				'alt'    => '',
				'width'  => 0,
				'height' => 0,
				'thumb'  => (string) $placeholder,
			);
		}

		$main_data  = wp_get_attachment_image_src( $attachment_id, $main_size );
		$thumb_data = wp_get_attachment_image_src( $attachment_id, $thumb_size );
		$srcset     = (string) wp_get_attachment_image_srcset( $attachment_id, $main_size );
		$sizes      = (string) wp_get_attachment_image_sizes(  $attachment_id, $main_size );
		$alt        = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		return array(
			'id'     => $attachment_id,
			'src'    => $main_data ? (string) $main_data[0] : '',
			'srcset' => $srcset,
			'sizes'  => $sizes,
			'alt'    => $alt,
			'width'  => $main_data ? (int) $main_data[1] : 0,
			'height' => $main_data ? (int) $main_data[2] : 0,
			'thumb'  => $thumb_data ? (string) $thumb_data[0] : ( $main_data ? (string) $main_data[0] : '' ),
		);
	}

	/**
	 * Renders the Elementor-only "no product selected" placeholder.
	 */
	private function render_no_product_placeholder(): void {
		$is_editor = class_exists( '\Elementor\Plugin' )
			&& method_exists( \Elementor\Plugin::$instance->editor, 'is_edit_mode' )
			&& \Elementor\Plugin::$instance->editor->is_edit_mode();
		if ( ! $is_editor ) {
			return;
		}
		?>
		<div class="zymarg-vig zymarg-vig--no-product">
			<em>
				<?php esc_html_e(
					'ZYMARG Variation Image Gallery — set a Product ID (or use Dynamic Tags → Post ID on a single-product template).',
					'woo-swatches-elementor'
				); ?>
			</em>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────
	// Template system — LFI-safe (mirrors WSE_Widget_Price pattern)
	// ─────────────────────────────────────────────────────────────────────

	public static function locate_template( string $template_name ): string {
		$template_name = ltrim( $template_name, '/\\' );
		if ( '' === $template_name || ! str_ends_with( $template_name, '.php' ) ) {
			return '';
		}
		if ( str_contains( $template_name, '..' ) ) {
			return '';
		}
		if ( isset( self::$template_path_cache[ $template_name ] ) ) {
			return self::$template_path_cache[ $template_name ];
		}

		$sub_dir   = 'woo-swatches-elementor/gallery/';
		$locations = array(
			get_stylesheet_directory() . '/' . $sub_dir . $template_name,
			get_template_directory()   . '/' . $sub_dir . $template_name,
			WSE_PATH . 'templates/gallery/' . $template_name,
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
	 * Locates, validates, and includes a gallery template, returning its
	 * output. LFI guard via realpath() allow-list.
	 *
	 * @param  string               $template_name
	 * @param  array<string, mixed> $args
	 * @return string
	 */
	public static function include_template( string $template_name, array $args = array() ): string {
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
