<?php
/**
 * Transient caching layer for swatch render data.
 *
 * Prevents repeated DB queries on every page load.
 * Without this, a product page with 5 attributes × 10 terms fires
 * 50+ extra queries per request just to read color hex codes and image IDs.
 *
 * Cache key format : wse_swatches_{product_id}
 * Storage          : WordPress transients (DB or external object cache)
 * TTL              : get_option('wse_cache_ttl')  default: DAY_IN_SECONDS
 *
 * Invalidation triggers (all cleared automatically):
 *   • Product saved / status changed
 *   • Product variation saved / deleted
 *   • WC attribute term created / edited
 *   • WC attribute taxonomy added / updated / deleted
 *   • Manual flush via admin AJAX button
 *
 * Gap 25 — Transient caching + invalidation hooks
 * Gap 42 — $wpdb->prepare() on all direct DB queries
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Cache {

	// ─────────────────────────────────────────────────────────────────────
	// Constants
	// ─────────────────────────────────────────────────────────────────────

	/** Transient key prefix. All cache entries start with this. */
	const PREFIX = 'wse_swatches_';

	// ─────────────────────────────────────────────────────────────────────
	// Gap 34 — PHP 8.2+ explicit property declarations
	// ─────────────────────────────────────────────────────────────────────
	protected static ?WSE_Cache $instance = null;

	// ─────────────────────────────────────────────────────────────────────
	// Singleton
	// ─────────────────────────────────────────────────────────────────────

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

	// ─────────────────────────────────────────────────────────────────────
	// Hooks — all invalidation triggers
	// ─────────────────────────────────────────────────────────────────────

	private function hooks(): void {

		// ── Product-level invalidation ────────────────────────────────────

		// When a product post is saved (covers WC product editor saves)
		add_action(
			'save_post_product',
			array( $this, 'clear_on_product_save' ),
			10,
			1
		);

		// When a product variation is saved — clear the parent product cache
		add_action(
			'woocommerce_save_product_variation',
			array( $this, 'clear_on_variation_save' ),
			10,
			2
		);

		// When a product variation is deleted
		add_action(
			'woocommerce_delete_product_variation',
			array( $this, 'clear_on_variation_delete' ),
			10,
			1
		);

		// ── Term-level invalidation ───────────────────────────────────────

		// When any WC attribute term is saved (created or edited)
		// covers both created_term and edited_term via the unified hook
		add_action( 'saved_term', array( $this, 'clear_on_term_save' ), 10, 3 );

		// ── Taxonomy-level invalidation ───────────────────────────────────

		// When a WC attribute taxonomy itself changes — flush everything
		// because the attribute type may have changed for many products
		add_action( 'woocommerce_attribute_added',   array( $this, 'handle_taxonomy_change' ) );
		add_action( 'woocommerce_attribute_updated', array( $this, 'handle_taxonomy_change' ) );
		add_action( 'woocommerce_attribute_deleted', array( $this, 'handle_taxonomy_change' ) );

		// ── Admin AJAX flush ──────────────────────────────────────────────
		add_action( 'wp_ajax_wse_flush_cache', array( $this, 'ajax_flush_all' ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Core read / write / delete API
	// These are the methods called by WSE_Swatch_Renderer (Phase 6)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Retrieves cached swatch data for a product.
	 *
	 * Returns false (not null) when no cache entry exists,
	 * matching WordPress transient conventions.
	 *
	 * @param  int   $product_id Product post ID.
	 * @return mixed Cached data or false if not cached.
	 */
	public static function get( int $product_id ): mixed {
		return get_transient( self::PREFIX . $product_id );
	}

	/**
	 * Stores swatch data for a product.
	 *
	 * TTL comes from the admin setting 'wse_cache_ttl' (default: DAY_IN_SECONDS).
	 * TTL is capped at 30 days to prevent stale data from persisting too long.
	 *
	 * @param int   $product_id Product post ID.
	 * @param mixed $data       Swatch data array built by WSE_Swatch_Renderer.
	 */
	public static function set( int $product_id, mixed $data ): void {
		$ttl = (int) get_option( 'wse_cache_ttl', DAY_IN_SECONDS );
		// Cap at 30 days to prevent indefinitely stale cache
		$ttl = min( $ttl, 30 * DAY_IN_SECONDS );
		// Minimum 60 seconds to avoid hammering on high-traffic pages
		$ttl = max( $ttl, 60 );
		set_transient( self::PREFIX . $product_id, $data, $ttl );
	}

	/**
	 * Deletes the cache entry for a single product.
	 *
	 * Uses delete_transient() which handles both DB storage and
	 * external object caches (Redis, Memcached) correctly.
	 *
	 * @param int $product_id Product post ID.
	 */
	public static function clear( int $product_id ): void {
		delete_transient( self::PREFIX . $product_id );
	}

	/**
	 * Deletes cache entries for all products that use a given term.
	 *
	 * Called when a WC attribute term's meta (color/image) is updated
	 * so that any product displaying that term gets fresh swatch data.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function clear_by_term( int $term_id ): void {

		global $wpdb;

		// Gap 42 — $wpdb->prepare() on all direct DB queries
		// Find all product post IDs that have a term relationship with this term
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT tr.object_id
				 FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->posts} p
				         ON p.ID = tr.object_id
				        AND p.post_type IN ('product','product_variation')
				 INNER JOIN {$wpdb->term_taxonomy} tt
				         ON tt.term_taxonomy_id = tr.term_taxonomy_id
				        AND tt.term_id = %d",
				$term_id
			)
		);

		if ( empty( $product_ids ) ) {
			return;
		}

		foreach ( $product_ids as $id ) {
			$id = (int) $id;
			// If it's a variation, clear the parent product's cache too
			$parent = wp_get_post_parent_id( $id );
			if ( $parent ) {
				self::clear( $parent );
			} else {
				self::clear( $id );
			}
		}
	}

	/**
	 * Deletes ALL plugin swatch transients from the database.
	 *
	 * v1.1.0 (B17) — chunked batching.
	 *   The previous version iterated delete_transient() over every match
	 *   in a single loop. On stores with thousands of cached products this
	 *   could exceed PHP's max_execution_time. v1.1.0 caps each invocation
	 *   at WSE_FLUSH_BATCH_SIZE deletions and returns the number remaining
	 *   so the admin AJAX flush can iterate via a follow-up call.
	 *
	 * @param  bool $force_sql   When true, uses a single SQL DELETE instead
	 *                           of the API loop. Used by uninstall.
	 * @param  int  $batch_size  Max deletions per call (0 = no cap).
	 * @return int               Number of transients still pending deletion.
	 */
	public static function flush_all( bool $force_sql = false, int $batch_size = 0 ): int {

		global $wpdb;

		if ( $force_sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE '_transient_wse_swatches_%'
				    OR option_name LIKE '_transient_timeout_wse_swatches_%'"
			);
			return 0;
		}

		$cap = $batch_size > 0 ? (int) $batch_size : 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transient_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT REPLACE(option_name, '_transient_', '')
				 FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				 ORDER BY option_id ASC"
				 . ( $cap > 0 ? ' LIMIT ' . ( $cap + 1 ) : '' ),
				$wpdb->esc_like( '_transient_' . self::PREFIX ) . '%'
			)
		);

		if ( empty( $transient_keys ) ) {
			return 0;
		}

		// If we asked for a cap and got more, the extra row tells us at
		// least one more page exists.
		$has_more = ( $cap > 0 && count( $transient_keys ) > $cap );
		if ( $has_more ) {
			$transient_keys = array_slice( $transient_keys, 0, $cap );
		}

		foreach ( $transient_keys as $key ) {
			delete_transient( $key );
		}

		// Re-count what's left so the caller knows whether to recurse.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$wpdb->options}
				 WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::PREFIX ) . '%'
			)
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Invalidation hook callbacks
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Clears cache when a product post is saved.
	 * Skips autosaves and revisions to avoid unnecessary cache churn.
	 *
	 * @param int $post_id Product post ID.
	 */
	public function clear_on_product_save( int $post_id ): void {

		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// Skip revisions
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::clear( $post_id );
	}

	/**
	 * Clears the parent product's cache when a variation is saved.
	 *
	 * @param int $variation_id Variation post ID.
	 * @param int $loop_index   Loop index (unused).
	 */
	public function clear_on_variation_save( int $variation_id, int $loop_index ): void {
		$variation = wc_get_product( $variation_id );
		if ( $variation instanceof \WC_Product_Variation ) {
			self::clear( $variation->get_parent_id() );
		}
	}

	/**
	 * Clears the parent product's cache when a variation is deleted.
	 *
	 * @param int $variation_id Variation post ID.
	 */
	public function clear_on_variation_delete( int $variation_id ): void {
		$parent_id = wp_get_post_parent_id( $variation_id );
		if ( $parent_id ) {
			self::clear( $parent_id );
		}
	}

	/**
	 * Clears product caches when a WC attribute term is saved.
	 * Scoped to pa_* taxonomies only.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID (unused).
	 * @param string $taxonomy Taxonomy slug e.g. 'pa_color'.
	 */
	public function clear_on_term_save( int $term_id, int $tt_id, string $taxonomy ): void {
		// Only handle WC attribute taxonomies
		if ( strpos( $taxonomy, 'pa_' ) !== 0 ) {
			return;
		}
		self::clear_by_term( $term_id );

		// Also invalidate WSE_Attribute_Types in-memory cache
		if ( class_exists( 'WSE_Attribute_Types' ) ) {
			WSE_Attribute_Types::instance()->clear_cache_for( $taxonomy );
		}
	}

	/**
	 * Flushes all transients when a WC attribute taxonomy changes.
	 * (Adding/updating/deleting an attribute type affects all products.)
	 */
	public function handle_taxonomy_change(): void {
		self::flush_all();

		// Clear attribute type in-memory cache too
		if ( class_exists( 'WSE_Attribute_Types' ) ) {
			WSE_Attribute_Types::instance()->clear_cache();
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// Admin AJAX flush
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * AJAX handler: flushes all swatch transients.
	 * Used by the "Clear swatch cache" button in WooSwatches → Settings.
	 *
	 * v1.1.0 (B17) — chunked: handles up to WSE_FLUSH_BATCH_SIZE entries
	 * per request and returns the remaining count so the admin script
	 * can issue follow-up requests when more are pending.
	 *
	 * Gap 13 — Nonce verification + capability check.
	 */
	public function ajax_flush_all(): void {

		check_ajax_referer( 'wse_flush_cache', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Insufficient permissions.', 'woo-swatches-elementor' ) ),
				403
			);
			return;
		}

		$total_before = self::get_cache_count();
		$remaining    = self::flush_all( false, 200 ); // 200 transients per call

		$cleared = max( 0, $total_before - $remaining );

		wp_send_json_success(
			array(
				'message'   => sprintf(
					/* translators: %d: number of cache entries cleared */
					esc_html__( 'Cleared %d cached swatch entries.', 'woo-swatches-elementor' ),
					$cleared
				),
				'cleared'   => $cleared,
				'remaining' => $remaining,
				'has_more'  => $remaining > 0,
			)
		);
	}

	// ─────────────────────────────────────────────────────────────────────
	// Admin helpers
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns the number of active swatch cache entries.
	 * Used by the admin settings page to show cache status.
	 *
	 * @return int Number of cached product entries.
	 */
	public static function get_cache_count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$wpdb->options}
				 WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::PREFIX ) . '%'
			)
		);

		return $count;
	}

	/**
	 * Returns whether a specific product is currently cached.
	 *
	 * @param int $product_id Product post ID.
	 * @return bool True if a valid cache entry exists.
	 */
	public static function is_cached( int $product_id ): bool {
		return false !== self::get( $product_id );
	}

	/**
	 * Returns the cache key for a product (for debugging/tools).
	 *
	 * @param int $product_id Product post ID.
	 * @return string Transient key.
	 */
	public static function get_key( int $product_id ): string {
		return self::PREFIX . $product_id;
	}
}
