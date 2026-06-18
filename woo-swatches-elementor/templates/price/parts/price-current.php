<?php
/**
 * Price part — current/sale price (the prominent number).
 *
 * Used by templates/price/simple.php and templates/price/variable.php
 * for both the initial server-side render and the structural reference
 * that price.js reproduces during variation updates.
 *
 * Available variables:
 *   @var string $current_html  Pre-formatted current price (e.g. "1,400৳").
 *
 * @package WooSwatchesElementor
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;
?>
<span class="zymarg-price-current"><?php echo esc_html( $current_html ); ?></span>
