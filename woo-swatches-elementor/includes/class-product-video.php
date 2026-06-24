<?php
/**
 * Product-level video support (v1.5.0 — B2).
 *
 * Lets a vendor attach ONE video per product (YouTube, Vimeo, or a
 * self-hosted MP4/WebM/OGG file). The video is surfaced by the Variation
 * Image Gallery widget as a "Watch video" trigger that opens a lazy-loaded
 * overlay player — see assets/js/gallery.js and assets/css/gallery.css.
 *
 * Scope (locked with ZYMARG for v1.5.0): PRODUCT-LEVEL only — a single
 * video per product, shown regardless of the selected variation. (Per-
 * variation video is on the roadmap for a later release.)
 *
 * Responsibilities of this class:
 *   1. Admin — render a "Product Video URL" text field on the WooCommerce
 *      product-data General tab, and persist it to the `_wse_product_video`
 *      post meta on save.
 *   2. Parsing — a reusable static parse_video_url() that classifies a URL
 *      as youtube | vimeo | mp4 and returns the canonical embed source the
 *      frontend JS needs. Centralised here so both the admin (for a light
 *      validity hint) and the gallery widget use identical logic.
 *
 * No frontend output happens here; the gallery widget reads the meta via
 * get_product_video_data() and emits the overlay markup itself.
 *
 * @package WooSwatchesElementor
 * @since   1.5.0
 */

defined( 'ABSPATH' ) || exit;

class WSE_Product_Video {

	/** Post-meta key holding the raw video URL. */
	const META_KEY = '_wse_product_video';

	protected static ?WSE_Product_Video $instance = null;

	public static function instance(): static {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {
		$this->hooks();
	}

	private function __clone() {}

	private function hooks(): void {
		// Admin — render the field on the product-data General tab.
		add_action(
			'woocommerce_product_options_general_product_data',
			array( $this, 'render_admin_field' )
		);

		// Admin — persist the field on save. Mirrors the hook used by
		// WSE_Local_Attributes for consistency.
		add_action(
			'woocommerce_process_product_meta',
			array( $this, 'save_admin_field' )
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Admin field
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Renders the "Product Video URL" text input on the General tab.
	 */
	public function render_admin_field(): void {
		woocommerce_wp_text_input(
			array(
				'id'          => self::META_KEY,
				'label'       => esc_html__( 'Product Video URL', 'woo-swatches-elementor' ),
				'placeholder' => 'https://www.youtube.com/watch?v=…',
				'desc_tip'    => true,
				'description' => esc_html__(
					'Optional. YouTube, Vimeo, or a direct .mp4 / .webm / .ogg URL. The Variation Image Gallery widget shows a "Watch video" button that opens this in an overlay player.',
					'woo-swatches-elementor'
				),
				'type'        => 'url',
			)
		);
	}

	/**
	 * Saves the video URL post meta.
	 *
	 * @param int $post_id Product post ID.
	 */
	public function save_admin_field( $post_id ): void {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}

		// Capability + nonce: woocommerce_process_product_meta fires inside
		// WC's own save flow which has already verified the product nonce
		// and edit_product capability, so we don't re-check here (matches
		// WSE_Local_Attributes::save_local_swatches).
		$raw = isset( $_POST[ self::META_KEY ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? esc_url_raw( wp_unslash( (string) $_POST[ self::META_KEY ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';

		if ( '' === $raw ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		update_post_meta( $post_id, self::META_KEY, $raw );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Parsing + retrieval
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the parsed video data for a product, or null when no valid
	 * video is configured.
	 *
	 * @param int $product_id
	 * @return array{type:string,embed:string,raw:string}|null
	 */
	public static function get_product_video_data( int $product_id ): ?array {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return null;
		}

		$raw = (string) get_post_meta( $product_id, self::META_KEY, true );
		if ( '' === trim( $raw ) ) {
			return null;
		}

		return self::parse_video_url( $raw );
	}

	/**
	 * Classifies a video URL and returns the canonical embed source.
	 *
	 * Supported:
	 *   - YouTube  (watch?v=, youtu.be/, /embed/, /shorts/)  → privacy-enhanced nocookie embed
	 *   - Vimeo    (vimeo.com/{id})                          → player.vimeo.com embed
	 *   - Direct   (.mp4 / .webm / .ogg)                     → file URL for a <video> element
	 *
	 * @param string $url Raw URL.
	 * @return array{type:string,embed:string,raw:string}|null  null when unsupported.
	 */
	public static function parse_video_url( string $url ): ?array {
		$url = trim( $url );
		if ( '' === $url ) {
			return null;
		}

		// ── YouTube ───────────────────────────────────────────────────────
		// Matches: youtu.be/ID, youtube.com/watch?v=ID, /embed/ID, /shorts/ID
		if ( preg_match(
			'~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~i',
			$url,
			$m
		) ) {
			$id = $m[1];
			return array(
				'type'  => 'youtube',
				// youtube-nocookie keeps the privacy-enhanced mode; params
				// added by the JS at play time (autoplay etc.).
				'embed' => 'https://www.youtube-nocookie.com/embed/' . $id,
				'raw'   => $url,
			);
		}

		// ── Vimeo ───────────────────────────────────────────────────────
		if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~i', $url, $m ) ) {
			$id = $m[1];
			return array(
				'type'  => 'vimeo',
				'embed' => 'https://player.vimeo.com/video/' . $id,
				'raw'   => $url,
			);
		}

		// ── Direct file (mp4 / webm / ogg) ──────────────────────────────
		// Strip any query string before checking the extension.
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( preg_match( '~\.(mp4|webm|ogg|ogv)$~i', (string) $path ) ) {
			return array(
				'type'  => 'mp4',
				'embed' => $url,
				'raw'   => $url,
			);
		}

		// Unsupported URL — caller treats null as "no video".
		return null;
	}
}
