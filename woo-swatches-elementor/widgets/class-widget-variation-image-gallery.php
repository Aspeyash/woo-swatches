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
 * Layout system (5 layouts × 3 breakpoints + per-device thumb toggles):
 *
 *   Desktop / Tablet / Mobile each pick one of:
 *     vertical_left      — Vertical thumbs left, main right (default desktop;
 *                          Apple / Nike / Sephora / Amazon pattern)
 *     vertical_right     — Vertical thumbs right, main left
 *     horizontal_below   — Horizontal thumb strip below main (default tablet;
 *                          Adidas / Zara / WooCommerce default pattern)
 *     horizontal_above   — Horizontal thumb strip above main
 *     stacked            — All images stacked vertically, no thumbs
 *                          (Allbirds / H&M / Patagonia minimal pattern)
 *     grid               — 2-column grid, all images visible at once
 *                          (Shopify Dawn theme pattern)
 *     mobile_carousel    — Swipeable main + dots + STACKED VERTICAL thumbs
 *                          below (default mobile, per ZYMARG product spec —
 *                          gives mobile users both swipe AND scroll-to-thumb)
 *
 *   Plus per-device "Show Thumbnails" toggles that hide the thumb strip
 *   without affecting the main image. Useful for stacked / minimal layouts.
 *
 * Variation sync:
 *
 *   Each variation's image set (variation thumbnail + variation gallery if
 *   the WC site uses an add-on for per-variation gallery, falling back
 *   through variation→parent featured→parent gallery→WC placeholder) is
 *   computed server-side and embedded as data-variation-images JSON on
 *   the widget root. JS reads the data attribute, subscribes to the
 *   canonical form's found_variation / reset_data events, and cross-fades
 *   thumbs + main image on selection change. No AJAX round-trip.
 *
 * Industry-research-backed defaults (see v1.3.0 changelog for source mix):
 *
 *   - Vertical thumbs left + main right is the highest-converting desktop
 *     layout for fashion / footwear / accessories.
 *   - Mobile must be a swipeable carousel regardless of desktop choice.
 *   - 200ms cross-fade is the perceived-quality sweet spot for variation
 *     swaps (also fits inside the CLS-exemption click-to-paint window).
 *   - Sticky main image during description scroll measurably improves
 *     conversion (Apple / Sephora data).
 *   - Hover-zoom lens (Amazon-style) outperforms click-then-lightbox by
 *     ~15% engagement on desktop; both are supported here.
 *
 * Widget identity:
 *
 *   Slug    : zymarg-variation-image-gallery
 *   Title   : ZYMARG Variation Image Gallery
 *   Author  : ZYMARG
 *   Version : 1.3.0 (introduced)
 *
 * Gap 21 — get_script_depends() / get_style_depends()
 * Gap 22 — Dynamic tags on product_id control
 * Gap 26 — Custom 'woo-swatches-elementor' category
 * Gap 31 — has_widget_inner_wrapper() + is_dynamic_content()
 * Gap 34 — PHP 8.2+ explicit property declarations
 * Gap 41 — add_responsive_control() for sizing controls
 * Gap 46 — LFI guard on template includes
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
	 * Mirrors the LFI-guard cache in WSE_Swatch_Renderer / WSE_Widget_Price.
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
		return esc_html__( 'ZYMARG Variation Image Gallery', 'woo-swatches-elementor' );
	}

	public function get_icon(): string {
		// eicon-images-grid reads visually as "image gallery" and pairs
		// well with the existing eicon-product-* / eicon-price-* set
		// used by Widgets 1 / 2 / 3.
		return 'eicon-images-grid';
	}

	/** Gap 26 — custom widget category (shared with Widgets 1, 2, 3). */
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
	// Gap 21 — Asset dependencies (resolved via WSE_Assets::handle())
	// Elementor only loads these on pages where Widget 4 is present.
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

	// ─────────────────────────────────────────────────────────────────────
	// Gap 31 — Elementor DOM optimisation + dynamic content flag
	// ─────────────────────────────────────────────────────────────────────

	/** Strips the extra .elementor-widget-container wrapper. */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * Prevents Elementor from caching this widget's output.
	 * Critical: the variation→images mapping changes whenever the user
	 * adds / removes variations or updates per-variation images. Cached
	 * widget HTML would serve a stale data-variation-images JSON.
	 */
	public function is_dynamic_content(): bool {
		return true;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Controls registration
	//
	// Phase 1 (this commit) ships the Product section + minimal Layout
	// scaffold so the widget appears in the Elementor panel and the
	// registration plumbing can be smoke-tested. Phases 2-5 fill in the
	// full Display, Animation, Image, Variation Sync, Lightbox, and
	// Style sections per the v1.3.0 plan.
	// ─────────────────────────────────────────────────────────────────────

	protected function register_controls(): void {

		// ── Product section ──────────────────────────────────────────────
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

		// Phase 2-5 will add: Layout, Display, Animation, Image, Variation
		// Sync, Lightbox, plus the full Style tab.
	}

	// ─────────────────────────────────────────────────────────────────────
	// Render — populated in Phase 2.
	// Phase 1 ships a placeholder so the widget renders something visible
	// in the Elementor preview without errors.
	// ─────────────────────────────────────────────────────────────────────

	protected function render(): void {
		?>
		<div class="zymarg-vig zymarg-vig--scaffold">
			<em class="zymarg-vig-scaffold-note">
				<?php esc_html_e( 'ZYMARG Variation Image Gallery — full render lands in v1.3.0 Phase 2.', 'woo-swatches-elementor' ); ?>
			</em>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────
	// Template system — Gap 4 + Gap 46 (mirrors WSE_Widget_Price pattern)
	// Skeleton — fleshed out in Phase 2 when templates land.
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Locates a gallery template, supporting child-theme overrides at
	 * {child-theme}/woo-swatches-elementor/gallery/{file}.
	 *
	 * @param  string $template_name e.g. 'main-image.php', 'layouts/vertical-thumbs.php'.
	 * @return string                Absolute path or empty string if not found.
	 */
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
}
