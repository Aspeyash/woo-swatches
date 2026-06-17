=== ZYMARG Variation Swatches for Elementor ===
Contributors: zymarg
Tags: woocommerce, variation swatches, elementor, color swatches, product attributes
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 9.4
Stable tag: 1.1.0
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

= 1.1.0 =
**Major release: B3 architecture refactor + 23 bug fixes + 2 new features**

This release replaces the dual-form architecture with a single canonical form per product, eliminating duplicate variation engines and fixing a long list of real-world bugs. It also introduces a Presenter Mode for sticky add-to-cart bars and a global toggle to hide the View Cart link.

**Architecture (B3) — single canonical form per product**
* Widget 1 (Swatches) no longer renders its own `<form class="variations_form">`. It registers with a new per-page form registry and emits swatch UI only.
* Widget 2 (Add to Cart) renders the canonical `<form id="wse-form-{product_id}">`. Multiple instances of Widget 2 on the same page (sticky bar + main button) coordinate automatically — the first becomes canonical, others become presenters that target its form via the HTML5 `form=` attribute.
* Variation JSON is emitted exactly once per product per page.
* Result: one `wc-add-to-cart-variation.js` engine per product instead of two; halved page weight on multi-attribute variable products.

**New: Presenter Mode (sticky add-to-cart bar)**
* New "Behavior" section on the Add to Cart widget with a Presenter Mode switcher.
* When enabled, the widget renders a button + quantity input that targets another instance's canonical form. Quantity values are kept in bidirectional sync via JS.
* Per-device sticky toggles: independent on/off switchers for Desktop (≥ 1025px), Tablet (768–1024px), and Mobile (≤ 767px). All default OFF — opt in per device.
* When sticky is active for the current viewport, the body picks up matching padding-bottom so content is never obscured by the pinned bar.

**New: "View Cart" link toggle (global)**
* New WooCommerce Settings → WooSwatches → Display → "Show 'View Cart' link after Add to Cart" option. Default ON (matches v1.0.5 behaviour).
* When OFF, strips the View Cart anchor from the success message and the cart fragments — applies everywhere (main button, sticky bar, archive AJAX, Astra snackbar).
* Server-side filter is the primary path; client-side fragment sweep handles stale page-cache fragments.

**Bug fixes (B1–B23)**
* B1 — Operator-precedence bug fixed: the "Enable on Archive Pages" toggle now actually disables when set to "no". Previously `! 'yes' === get_option(...)` always evaluated to false.
* B2 — WooCommerce Blocks integration is no longer dead. Added the `WSE_Archive_Swatches::render_for_product()` method that the All Products and Product Collection block paths actually call.
* B4 — Mixed swatch + native dropdown attributes (e.g. Color swatch + Logo dropdown) now correctly enable Add to Cart. Cross-widget sync resolves through the canonical form's hidden selects regardless of input type.
* B5 — Initial keyboard tabindex: when no default attribute is set, the first available swatch in each group now receives `tabindex="0"` so the swatch group is reachable via Tab. Previously every swatch had `tabindex="-1"` until something was selected.
* B6 — Multi-attribute variable products on archive pages no longer fail with "Please select all options". Auto-falls back from AJAX-add-to-cart to "Go to product page" mode when the product has more than one attribute.
* B7 — `wse_archive_max` setting is now enforced. Swatches above the limit collapse into a "+N more" link to the product page.
* B8 — Add-to-Cart AJAX result is now deterministic. WooCommerce's `error: true` response is treated as a real error (not masked as success via cart-hash). Network errors still fall back to fragment verification, but explicit validation rejections (Dokan etc.) are surfaced honestly.
* B9 — `wse_skip_renderer` bypass removed; replaced with the `wse_renderer_emit_select` and `wse_renderer_emit_swatches` filters that scope rendering cleanly without conflicting with third-party hooks.
* B10 — Init storm fix: `updateAvailability` is now requestAnimationFrame-debounced per form. A product with N default attributes triggers exactly one availability scan at init instead of N.
* B11 — `is_dynamic_content()` is now `public` on Widget 1 (was `protected`) for consistency with Widget 2 and Elementor 3.20+ direct method invocation.
* B12 — Gallery image swap now uses an active-slide-aware selector chain (`.flex-active-slide` → `:not(.clone)` → `:first-child`). Themes with carousel galleries (Astra Pro, Flatsome, Hello+) now swap the visible slide.
* B13 — Archive hook is registered conditionally — only when the toggle is on. No more wasted callback invocations on every shop loop item when the feature is disabled.
* B14 — Activation gate hardening: removed redundant PHP version check (the plugin header `Requires PHP: 8.1` already gates this in WP 5.1+); WC version check uses `class_exists('WooCommerce')` for reliability across bulk-activation orders.
* B15 — Swatch wrapper no longer announces the attribute label twice on screen readers (legend + aria-label duplication).
* B17 — Cache flush is chunked (200 entries per call) and returns the remaining count so large-catalog stores don't time out on the admin Flush Cache button.
* B18 — `WSEAdmin` JS object moved off the global `jquery` handle onto a dedicated `wse-admin` script handle to prevent collisions with other plugins.
* B19 — Archive AJAX click handler only binds when the click behaviour is set to `ajax_add_to_cart`. No dead-code listeners on link-mode shops.
* B20 — Archive AJAX flow audited for idempotency; binds via namespaced `.off()/.on()` pattern.
* B21 — REST extension now also exposes the `zymarg_swatches` field on the public WC Store API (`/wc/store/v1/products`) for headless storefronts and the modern Cart & Checkout blocks.
* B22 — Plugin description clarified: the widgets work with free Elementor; Elementor Pro is only required to use these widgets in Theme Builder templates.
* B23 — Archive add-to-cart AJAX moved off `admin-ajax.php` onto the WC AJAX endpoint (`wc-ajax=wse_archive_add_to_cart`). LiteSpeed Cache, Hostinger Cache Manager, and other major page-cache plugins exclude `wc-ajax` from caching by default, fixing stale-nonce 403s.

**Migration**
* Existing stores: no database migration required. All v1.0.5 options keep their format. Cache should be flushed once after upgrade (Settings → WooSwatches → Performance → Flush Cache).
* Child-theme template overrides of `templates/add-to-cart/variable.php`, `templates/swatches/wrapper.php`, `templates/swatches/color.php`, `templates/swatches/image.php`, or `templates/swatches/label.php` MUST be re-synced — the markup has changed. An admin notice will list any stale overrides detected on your install. Dismiss the notice once you've re-synced.

**Performance**
* Per-product page weight reduced ~40–50% on multi-attribute variable products (single variation JSON instead of two).
* `wc-add-to-cart-variation.js` runs once per product per page instead of twice.
* `updateAvailability` runs once per init regardless of default-attribute count.

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

= 1.1.0 =
Major release: single-canonical-form architecture (B3), 23 bug fixes, Presenter Mode for sticky add-to-cart bars, and a global "View Cart" link toggle. Flush the swatch cache once after upgrade. Child-theme template overrides MUST be re-synced — an admin notice will list any stale ones.

= 1.0.0 =
Initial release — no upgrade steps required.
