<?php
/**
 * Plugin Name:       ZYMARG Variation Swatches for Elementor
 * Plugin URI:        https://zymarg.com/plugins/variation-swatches-elementor
 * Description:       Professional variation swatches and add-to-cart widgets for Elementor, fully compatible with WooCommerce. Pairs cleanly with Astra and Dokan multi-vendor.
 * Version:           1.4.0
 * Author:            ZYMARG
 * Author URI:        https://zymarg.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-swatches-elementor
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  elementor, woocommerce
 *
 * @package WooSwatchesElementor
 */

// Gap 17 — Prevent direct file access
defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────────────────
// Core constants — defined here so every class can reference WSE_FILE
// ─────────────────────────────────────────────────────────────────────────────
define( 'WSE_FILE',    __FILE__ );
define( 'WSE_VERSION', '1.4.0' );
define( 'WSE_PATH',    plugin_dir_path( __FILE__ ) );
define( 'WSE_URL',     plugin_dir_url( __FILE__ ) );
define( 'WSE_BASENAME', plugin_basename( __FILE__ ) );

// Gap 49 — SCRIPT_DEBUG suffix: loads .min assets in production, full in debug
define( 'WSE_SUFFIX', ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min' );

// ─────────────────────────────────────────────────────────────────────────────
// Gap 11 — HPOS (High Performance Order Storage) compatibility declaration
// Gap 12 — Cart & Checkout Blocks compatibility declaration
// Must fire before WooCommerce initialises
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables', // HPOS
			WSE_FILE,
			true
		);
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks', // WC Blocks
			WSE_FILE,
			true
		);
	}
} );

// ─────────────────────────────────────────────────────────────────────────────
// Gap 15 — Lifecycle hooks
// register_*_hook() must be called from the main plugin file
// The activator class is required immediately so the hooks can reference it
// ─────────────────────────────────────────────────────────────────────────────
require_once WSE_PATH . 'includes/class-activator.php';

register_activation_hook( WSE_FILE,   array( 'WSE_Activator', 'activate' ) );
register_deactivation_hook( WSE_FILE, array( 'WSE_Activator', 'deactivate' ) );
register_uninstall_hook( WSE_FILE,    array( 'WSE_Activator', 'uninstall' ) );

// ─────────────────────────────────────────────────────────────────────────────
// Gap 1 — Bootstrap on plugins_loaded
// This is the correct timing: WooCommerce and Elementor are both loaded
// Returns null silently if dependencies are missing (admin notices handle UX)
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'woo_swatches_elementor', 20 );

/**
 * Returns the single plugin instance, or null if dependencies are not met.
 *
 * @return WSE_Plugin|null
 */
function woo_swatches_elementor(): ?WSE_Plugin {

	// ── Dependency: Elementor ─────────────────────────────────────────────
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', 'wse_notice_missing_elementor' );
		return null;
	}

	// ── Dependency: WooCommerce ───────────────────────────────────────────
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wse_notice_missing_woocommerce' );
		return null;
	}

	// ── Load and boot the plugin singleton ───────────────────────────────
	require_once WSE_PATH . 'includes/class-plugin.php';
	return WSE_Plugin::instance();
}

// ─────────────────────────────────────────────────────────────────────────────
// Admin notice callbacks — separated for clean output escaping (Gap 53)
// ─────────────────────────────────────────────────────────────────────────────

/** Admin notice: Elementor not active */
function wse_notice_missing_elementor(): void {
	echo wp_kses(
		sprintf(
			'<div class="notice notice-error"><p><strong>WooSwatches for Elementor</strong> %s <a href="%s" target="_blank">%s</a> %s</p></div>',
			esc_html__( 'requires', 'woo-swatches-elementor' ),
			esc_url( 'https://wordpress.org/plugins/elementor/' ),
			esc_html__( 'Elementor', 'woo-swatches-elementor' ),
			esc_html__( 'to be installed and active.', 'woo-swatches-elementor' )
		),
		array(
			'div' => array( 'class' => array() ),
			'p'   => array(),
			'strong' => array(),
			'a'   => array( 'href' => array(), 'target' => array() ),
		)
	);
}

/** Admin notice: WooCommerce not active */
function wse_notice_missing_woocommerce(): void {
	echo wp_kses(
		sprintf(
			'<div class="notice notice-error"><p><strong>WooSwatches for Elementor</strong> %s <a href="%s" target="_blank">%s</a> %s</p></div>',
			esc_html__( 'requires', 'woo-swatches-elementor' ),
			esc_url( 'https://wordpress.org/plugins/woocommerce/' ),
			esc_html__( 'WooCommerce', 'woo-swatches-elementor' ),
			esc_html__( 'to be installed and active.', 'woo-swatches-elementor' )
		),
		array(
			'div' => array( 'class' => array() ),
			'p'   => array(),
			'strong' => array(),
			'a'   => array( 'href' => array(), 'target' => array() ),
		)
	);
}
