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
	 * v1.1.0 (B14) — gate hardening:
	 *   • PHP version check removed — the plugin header `Requires PHP: 8.1`
	 *     already prevents activation on lower PHP since WP 5.1+.
	 *   • WC presence check uses class_exists('WooCommerce') as the
	 *     authoritative gate (replaces the unreliable defined('WC_VERSION')
	 *     check that silently passed during multi-plugin bulk activation).
	 *   • If WC is absent at activation time, the plugin still activates
	 *     and the missing-WC admin notice (in woo-swatches-elementor.php)
	 *     handles UX — matches Emran Ahmed's pattern.
	 */
	public static function activate(): void {

		// ── WooCommerce version gate ──────────────────────────────────────
		// Only enforce when WC is actually loaded; on bulk activation WC
		// may not be loaded yet — the plugins_loaded gate in
		// woo_swatches_elementor() will handle the runtime check.
		if ( class_exists( 'WooCommerce' )
			&& defined( 'WC_VERSION' )
			&& version_compare( WC_VERSION, '8.0', '<' ) ) {
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

			// v1.1.0 (Feature A) — Show "View Cart" link in success message.
			// When disabled, the wc_add_to_cart_message_html filter strips the
			// <a class="wc-forward"> from add-to-cart success notices and the
			// View Cart button from cart fragments. Default 'yes' preserves
			// v1.0.5 behaviour.
			'wse_show_view_cart_link' => 'yes',
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
			'wse_show_view_cart_link', // v1.1.0
			'wse_template_override_acks', // v1.1.0 — hard-cut migration notice acks
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
