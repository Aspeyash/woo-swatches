=== ZYMARG Variation Swatches for Elementor ===
Contributors: zymarg
Tags: woocommerce, variation swatches, elementor, color swatches, product attributes
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 9.4
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replace WooCommerce variation dropdowns with beautiful color, image, and label swatches — built natively for Elementor.

== Description ==

**ZYMARG Variation Swatches for Elementor** replaces WooCommerce's default variation dropdowns with visual swatches you can style and position anywhere using Elementor.

= Two dedicated Elementor widgets =

**Widget 1 — ZYMARG Variation Swatches**
Renders color, image, label, and button swatches for any variable product. Place it anywhere on your product page layout.

**Widget 2 — ZYMARG Add to Cart**
A fully configurable quantity stepper + Add to Cart button. Syncs automatically with Widget 1 — selecting a swatch updates the form and enables the button.

= Key features =

* **Four swatch types** — color (hex), dual/split color, image, label, button
* **Cross-widget sync** — Widgets 1 and 2 communicate without page reloads
* **Archive loop support** — show compact swatches on shop/category pages
* **Out-of-stock handling** — blur, cross-out, or hide unavailable options
* **CSS tooltip** — shows term name on hover with no JavaScript
* **RTL support** — full right-to-left layout via swatches-rtl.css
* **Keyboard accessible** — arrow key navigation, focus-visible rings (WCAG AA)
* **WooCommerce Blocks** — works with the All Products block and Product Collection block
* **REST API** — `zymarg_swatches` field added to `/products` and `/products/variations` endpoints
* **WP-CLI** — `wp wse regen-thumbs` to batch-regenerate swatch thumbnails
* **Transient cache** — configurable TTL with one-click flush
* **Theme override** — place templates in `{theme}/woo-swatches-elementor/` to customise output

= Requirements =

* WordPress 6.4+
* WooCommerce 8.0+
* Elementor 3.20+ (free or Pro)
* PHP 8.1+

= Shortcodes =

No shortcodes. All output is via dedicated Elementor widgets.

== Installation ==

1. Upload the `woo-swatches-elementor` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Go to **WooCommerce → Settings → WooSwatches** to configure global defaults.
4. For each product attribute, go to **Products → Attributes**, select an attribute, set its **Type** to `color`, `image`, `label`, or `button`.
5. Edit each attribute term and fill in the colour, image, or label value.
6. In Elementor, add the **ZYMARG Variation Swatches** and/or **ZYMARG Add to Cart** widgets to your product page template.

== Frequently Asked Questions ==

= Does this work without Elementor? =

The two Elementor widgets require Elementor. However, archive/shop loop swatches and the WooCommerce Blocks integration work on any theme.

= Can I override the templates? =

Yes. Copy any file from `templates/swatches/` or `templates/add-to-cart/` into your theme at `{theme}/woo-swatches-elementor/{same-path}` and WordPress will use your version.

= How do I add swatches to the shop loop? =

Enable **Archive / Shop Loop → Enable on Archive Pages** under WooCommerce → Settings → WooSwatches. Swatches appear automatically below product titles.

= Is it compatible with WooCommerce Blocks? =

Yes. Swatches are injected into the All Products block (legacy) and the Product Collection block (WC 8.0+) automatically when archive swatches are enabled.

= How do I regenerate swatch thumbnails after changing image sizes? =

Go to **Tools → Regen Swatch Thumbnails** in the WordPress admin, or run `wp wse regen-thumbs` from the command line.

= Will my data be deleted if I uninstall? =

Only if you enable **Advanced → Delete Data on Uninstall** before deleting the plugin.

== Screenshots ==

1. Widget 1 (Variation Swatches) and Widget 2 (Add to Cart) in Elementor editor
2. Color, image, label, and button swatch types
3. WooCommerce → Settings → WooSwatches
4. Attribute term edit screen with colour picker
5. Shop loop with archive swatches

== Changelog ==

= 1.0.5 =
* Fix: Add to Cart on variable products showed "Something went wrong" even though the item was successfully added to the cart. A server-side plugin (typically a multi-vendor plugin such as Dokan) was filtering woocommerce_add_to_cart_validation to false for AJAX-submitted variable product requests, causing WooCommerce's AJAX handler to return {error:true} — even though a parallel mechanism (WooCommerce's own variation form) had already added the item to the cart session. The Add to Cart engine now verifies the actual cart state via a follow-up get_refreshed_fragments call whenever an error response is received. If the cart hash changed (item was genuinely added), the widget correctly shows "Added ✓" and triggers the theme's Added to Cart notification; if the hash is unchanged (genuine failure), it shows "Something went wrong".
* Fix: The "Adding…" button label was displaying the literal text "Adding\u2026" instead of "Adding…". PHP does not interpret \uXXXX Unicode escapes inside strings (that is JavaScript syntax); the sequence was being passed through as-is by wp_localize_script. Fixed by using the actual UTF-8 ellipsis character (…) in the PHP source for all three affected labels: the add-to-cart loading state, the cache-flush progress label, and the thumbnail regeneration progress label.

= 1.0.4 =
* Fix: After a successful Add to Cart on a variable product (Widget 2), the button briefly showed "Adding..." and then "Something went wrong" — even though the item was genuinely added to the cart (visible on the Cart page). Root cause: WooCommerce's wc_fragments_loaded and wc_fragments_refreshed events both fire during a normal page load, and each previously triggered a separate internal "reinit" signal. add-to-cart.js responded to every reinit by re-binding its click handler without removing the previous one, so a single click fired 2-3 simultaneous add-to-cart requests — the first succeeded, the extras failed (e.g. due to stock already reserved by the first), and whichever response arrived last overwrote the button state. The internal reinit signal now fires at most once per page load, and every event handler in add-to-cart.js is rebound idempotently (no duplicate handlers regardless of how many times init runs).
* Improvement: A successful Add to Cart from the ZYMARG Add to Cart widget now triggers WooCommerce's standard added_to_cart event (matching core's own add-to-cart button signature). Themes that show a native "Added to cart" notification/snackbar for AJAX add-to-cart (as Astra does for shop-loop add-to-cart buttons) will now show that same notification for Widget 2 as well.

= 1.0.3 =
* Fix: Products with a second, non-swatch attribute (e.g. a plain "Logo" dropdown, or any attribute left as WooCommerce's default Select type) kept Add to Cart permanently disabled even after every swatch was correctly selected. wc-add-to-cart-variation.js only collects "chosen attributes" from elements inside a .variations ancestor; passthrough native <select> elements had none. They are now wrapped in a structural .wse-native-attr.variations container — same visible dropdown, now correctly included in variation matching.
* Fix: The Clear link inside each swatch group never worked. It targeted .reset_variations, an element from WooCommerce's default <table class="variations"> template that this plugin never renders, so the click handler was a permanent no-op. Clear now directly resets every .variations select in the form (covering both swatch-type and the new native passthrough selects) and triggers WooCommerce's recalculation, then runs the existing swatch-deselect/reset logic and notifies the Add to Cart widget.
* Fix: For products with default attribute values (WooCommerce pre-selects e.g. Color=Red, Size=Large on page load), the Add to Cart widget's hidden selects stayed empty and its button remained disabled until the shopper manually re-clicked an already-selected swatch. The cross-widget sync now also runs once on page load for any attribute with a pre-selected default, bringing both widgets into sync immediately without requiring an extra click.

= 1.0.2 =
* Fix: Variation swatches were visually selectable but the Add to Cart button stayed permanently disabled, the variation_id never resolved, and clicking the button showed WooCommerce's "Please select some product options before adding this product to your cart." The hidden <select> elements (in both the Variation Swatches widget and the Add to Cart widget) now carry the class "variations" alongside their existing wrapper classes. wc-add-to-cart-variation.js binds its change/found_variation/reset_data handlers via a delegated ".variations select" selector — without that class the script never recalculated the selected variation, regardless of how correctly the swatch-to-select sync ran.

= 1.0.1 =
* Fix: Elementor widget classes were loaded before Elementor itself, causing a fatal "Class Elementor\Widget_Base not found" error and missing widgets in the Elementor panel. Widget registration now hooks into elementor/loaded with did_action() fallbacks for all load orders.
* Fix: Replaced four calls to the non-existent woocommerce_dropdown_variation_attribute_options() with the correct wc_dropdown_variation_attribute_options() — affected Widget 1 (swatches), the Swatch Renderer's filter hook, Widget 2's variable-product template, and archive/shop-loop swatches. The variable-product instance caused a silent fatal that left the entire single product page blank for both admin and guest users.

= 1.0.0 =
* Initial release.
* Widgets: ZYMARG Variation Swatches, ZYMARG Add to Cart.
* Swatch types: color, dual-color, image, label, button.
* Archive loop support with click-behaviour option (link / AJAX add to cart).
* WooCommerce Blocks compatibility (All Products + Product Collection).
* REST API extension (`zymarg_swatches` field on products and variations).
* Transient cache with configurable TTL and one-click flush.
* Thumbnail generator with WP-CLI support.
* RTL stylesheet.
* Full WCAG AA keyboard accessibility.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
