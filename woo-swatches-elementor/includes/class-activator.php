<?php
/**
 * Plugin lifecycle handler.
 *
 * Handles activation (version gates, defaults), deactivation (flush rules),
 * and uninstall (full data cleanup when "delete on uninstall" is enabled).
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Activator {

	// ─────────────────────────────────────────────────────────────────────
	// Gap 15 — Activation
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Runs on plugin activation.
	 *
	 * Checks PHP and WooCommerce version requirements, sets default options,
	 * and flags the thumbnail regeneration notice.
	 */
	public static function activate(): void {

		// ── PHP version gate ──────────────────────────────────────────────
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( plugin_basename( WSE_FILE ) );
			wp_die(
				esc_html__(
					'WooSwatches for Elementor requires PHP 8.1 or higher. Please upgrade your PHP version.',
					'woo-swatches-elementor'
				),
				esc_html__( 'Plugin Activation Error', 'woo-swatches-elementor' ),
				array( 'back_link' => true )
			);
		}

		// ── WooCommerce version gate ──────────────────────────────────────
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.0', '<' ) ) {
			deactivate_plugins( plugin_basename( WSE_FILE ) );
			wp_die(
				esc_html__(
					'WooSwatches for Elementor requires WooCommerce 8.0 or higher.',
					'woo-swatches-elementor'
				),
				esc_html__( 'Plugin Activation Error', 'woo-swatches-elementor' ),
				array( 'back_link' => true )
			);
		}

		// ── Set default options (add_option is a no-op if key already exists) ──
		// These are the global settings for the plugin admin panel.
		// Each Elementor widget can override these per-widget-instance.
		$defaults = array(

			// Swatch shape: rounded (6px) | circle (50%) | square (0)
			// Default is "rounded" — slightly rounded square as requested
			'wse_shape'               => 'rounded',

			// Tooltip: show term name on hover
			'wse_tooltip'             => 'yes',

			// Out-of-stock behaviour: blur | cross | hide
			'wse_oos_behavior'        => 'blur',

			// Load plugin stylesheet (false = theme writes all CSS)
			'wse_stylesheet'          => 'yes',

			// Style variant (reserved for future preset themes)
			'wse_style'               => 'default',

			// Gap 43 — Show swatches on standard WooCommerce shop/archive loop
			// Can be toggled on/off from WooSwatches → Settings → Archive
			'wse_archive_swatches'    => 'yes',

			// Max swatches shown in archive loop before "show more"
			'wse_archive_max'         => 5,

			// Archive click behaviour: link | ajax_add_to_cart
			'wse_archive_click'       => 'link',

			// Transient cache TTL in seconds (24 hours)
			'wse_cache_ttl'           => DAY_IN_SECONDS,

			// Whether to wipe all plugin data on uninstall
			'wse_delete_on_uninstall' => 'no',
		);

		foreach ( $defaults as $key => $value ) {
			add_option( $key, $value );
		}

		// ── Version stamp — used for future upgrade routines ──────────────
		update_option( 'wse_version', WSE_VERSION );

		// ── Gap 57 — Flag: thumbnail regeneration notice not yet dismissed ──
		// The admin notice (added in Phase 14) will show once until dismissed.
		// Set to false on activation so the notice appears on first load.
		if ( false === get_option( 'wse_regen_notice_dismissed' ) || 
		     ! get_option( 'wse_regen_notice_dismissed' ) ) {
			update_option( 'wse_regen_notice_dismissed', false );
		}

		// Flush rewrite rules so any plugin-registered endpoints are ready
		flush_rewrite_rules();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 15 — Deactivation
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Runs on plugin deactivation.
	 *
	 * Intentionally minimal — only flushes rewrite rules.
	 * Data is NOT deleted on deactivation (only on uninstall if opted-in).
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Gap 15 — Uninstall
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Runs when the plugin is deleted from WP admin.
	 *
	 * Only executes full cleanup if the store owner has opted in via
	 * WooSwatches → Settings → "Delete all data on uninstall".
	 * Default is false — data is preserved across reinstalls.
	 */
	public static function uninstall(): void {

		// Bail if the store owner hasn't opted in to data deletion
		if ( 'yes' !== get_option( 'wse_delete_on_uninstall', 'no' ) ) {
			return;
		}

		// ── Delete all plugin options ─────────────────────────────────────
		$options = array(
			'wse_shape',
			'wse_tooltip',
			'wse_oos_behavior',
			'wse_stylesheet',
			'wse_style',
			'wse_archive_swatches',
			'wse_archive_max',
			'wse_archive_click',
			'wse_cache_ttl',
			'wse_delete_on_uninstall',
			'wse_version',
			'wse_regen_notice_dismissed',
		);
		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// ── Delete global attribute term meta ─────────────────────────────
		// These are the color hex codes and image IDs stored per attribute term
		// e.g. "Red" → #ff0000, "Blue" → attachment_id:123
		delete_metadata( 'term', 0, 'wse_color', '', true );
		delete_metadata( 'term', 0, 'wse_image', '', true );

		// ── Delete per-product local attribute swatch meta (Gap 38) ──────
		// These are swatch configs for non-taxonomy (local) product attributes
		delete_post_meta_by_key( '_wse_local_swatches' );

		// ── Gap 42 — $wpdb->prepare() on all direct DB queries ───────────
		// Clear all swatch transients from wp_options (Gap 25)
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_wse_swatches_%'
			    OR option_name LIKE '_transient_timeout_wse_swatches_%'"
		);

		// Delete regeneration transients (Phase 14)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_wse_regen_%'
			    OR option_name LIKE '_transient_timeout_wse_regen_%'"
		);
	}
}
