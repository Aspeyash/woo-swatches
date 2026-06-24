=== ZYMARG Variation Swatches for Elementor ===
Contributors: zymarg
Tags: woocommerce, variation swatches, elementor, color swatches, product attributes
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 9.4
Stable tag: 1.6.0
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

= 1.6.0 =
**Minor release — customization controls, sticky moved into the widget, savings-pill removed.**

⚠️ **Action required after updating:** Sticky Add to Cart is no longer
configured under WooCommerce → Settings → WooSwatches. Those options have
been **removed** and replaced by per-widget controls. Re-enable sticky in
**Elementor → edit your Add to Cart widget → Content → Behavior** (Sticky on
Desktop / Tablet / Mobile + Scroll-trigger). Until you do, the bar uses the
widget defaults (sticky ON for mobile only).

Changes:

* **Sticky is now per-widget (single source of truth).** The global admin
  panel for Sticky Add to Cart + Scroll-triggered visibility has been
  removed. Each Add to Cart widget now owns its own sticky rule via new
  Behavior controls: Sticky on Desktop / Tablet / Mobile and Scroll-trigger
  on Desktop / Tablet / Mobile. More flexible (different rules per template)
  and no more split-brain between admin + widget. add-to-cart.js reads the
  per-device scroll-trigger flags from the widget's data attributes.

* **Sticky Bar appearance** gains a Minimum Height control (responsive),
  alongside the existing background / padding / margin / width / border /
  radius / shadow / gap / compact-layout / button-order controls.

* **Deep customization controls across all widgets.** New container-box and
  sub-element style sections:
  - **Swatches:** Widget Container (background incl. gradient — responsive,
    border, radius, padding, margin, max-width, alignment), Attribute Block
    (background, border, radius, padding, margin), Swatches Container (gap
    between swatches, padding, margin, fieldset padding, Clear-link color +
    typography).
  - **Add to Cart:** Widget Container gains gradient background, margin, and
    max-width (on top of the existing controls).
  - **Price:** new Widget Container section (background incl. gradient,
    border, radius, padding, margin, shadow, max-width).
  - **Gallery:** new Widget Container section (background, border, radius,
    padding, margin, shadow) plus Thumbnail-strip and Main-image-area
    background + padding.
  All dimensional controls are responsive (desktop / tablet / mobile).

* **Removed:** the v1.5.0 per-swatch savings / percent-off pill (the "Show
  Savings Pill" toggle and its rendering) — fully reverted per product
  decision.

No DB schema migration. The only behavioural change is the sticky-settings
relocation noted above.

= 1.5.0 =
**Minor release — product video, price animation, savings pill, and sticky-bar hardening.**

New features:

* **Product video in the gallery (B2).** Attach one video per product via
  the new "Product Video URL" field under Product data → General (YouTube,
  Vimeo, or a direct .mp4 / .webm / .ogg URL). The Variation Image Gallery
  widget shows a "Watch video" button (toggle + custom text in the Display
  section) that opens a lazy-loaded overlay player — the embed is only built
  on first click, so there is zero extra page-load cost. Closing the overlay
  (button / backdrop / ESC) removes the embed to stop playback reliably.

* **Per-swatch savings pill (C2').** New "Show Savings Pill (% off) on
  Swatches" toggle in the Swatches widget renders a small "-N%" corner badge
  on each swatch whose variation is on sale, where N is the highest discount
  among that term's in-stock variations. Works on color, image, label, and
  button swatch types. Default off.

* **Price-change animation (C1).** New "Price change animation" control in
  the Price widget's Layout style section: Fade in (default), Slide up + fade,
  or None. The animation plays when a variation is selected and is suppressed
  on the initial page-load reset (no flash). Honours the visitor's OS-level
  "reduce motion" setting automatically.

Hardening & maintenance:

* **Proactive sticky-bar gap hardening (A1).** Beyond the empty-`<p>` fixes
  in v1.4.10–v1.4.11, the sticky bar now also collapses empty headings
  (h1–h6) and WooCommerce's empty float-clear divs. All rules are sticky-only
  and `:empty`-guarded; the quantity stepper's CSS-glyph icons are explicitly
  preserved.

* **Admin term-UI hardening (A4).** The v1.4.12 `woocommerce_product_option_terms`
  handler now carries an explicit `is_taxonomy()` guard, documenting and
  enforcing that it never affects local (per-product) attributes.

* **Documentation.** Added a QA regression checklist (`QA-CHECKLIST.md`) and
  cleaned up the readme's stale roadmap / duplicate upgrade-notice entries.

CSS + PHP + JS changes, no DB schema migration. Drop-in replacement for
v1.4.12. Hard-refresh + Regenerate CSS after install.

= 1.4.12 =
**Fix: Empty Value(s) section in the product editor for swatch-typed attributes.**

When the plugin was active and the merchant added a global attribute
whose Type had been set to Color / Image / Label / Button on
Products → Attributes, the Value(s) section in the product editor's
Attributes panel was completely empty — no multi-select for picking
existing terms, no "Select all" / "Select none" / "Create value"
buttons. Deactivating the plugin made the UI reappear instantly.

Root cause: WooCommerce's html-product-attribute.php view renders the
term-selection UI inline only for the built-in `select` and `text`
types. For every other type it fires
`do_action( 'woocommerce_product_option_terms', $taxonomy, $i, $attr )`
expecting a third-party plugin to render its own UI. This plugin
registered Color / Image / Label / Button as custom types via
`product_attributes_type_selector` but never hooked into
`woocommerce_product_option_terms`, so the panel rendered nothing.

v1.4.12 hooks `WSE_Attribute_Types::render_term_selector_for_custom_
types()` into `woocommerce_product_option_terms` and outputs the same
multi-select + Select all / Select none / Create value UI that WC
renders for the `select` type — byte-for-byte matching DOM classes,
data attributes, and form field names. Because every selector matches
WC's defaults, all of WC's existing admin JS (Select2 term picker,
AJAX term-search, Select all / none handlers, Create value AJAX
endpoint) and the product-save logic continue to work without any
additional plugin JS or PHP. Merchants can now pick existing terms
and create new ones for swatch-typed attributes exactly as they do
for built-in select attributes.

PHP-only patch (~80 lines added to `class-attribute-types.php`). No
JS, CSS, template, DB schema, or settings changes. Drop-in
replacement for v1.4.11.

= 1.4.11 =
**Fix: Empty-<p> sticky-bar gap on simple, grouped, and external products.**

v1.4.10 scoped its empty-<p> neutralizer to `.wse-canonical-form`, which
is the form class for VARIABLE products only. Customer testing on a
simple product ("Hoodie with Logo") confirmed the gap persisted there —
because the plugin emits THREE other product-type form/wrapper classes
that v1.4.10's selector never matched:

* Simple   — `<form class="cart wse-cart-form wse-simple-cart">`
* Grouped  — `<form class="cart wse-cart-form wse-grouped-cart">`
* External — `<div class="wse-external-product">` (no form at all)

v1.4.11 rescopes the rule to the widget root
`.wse-widget-add-to-cart.wse-sticky-active`, which is the parent of all
four product-type templates. The empty-<p> neutralizer now applies
uniformly across simple, variable, grouped, and external products —
the gap closes the same way on every product type.

Still strictly sticky-only per ZYMARG product decision: non-sticky mode
is byte-identical to v1.4.10 across all product types. The `:empty`
predicate is preserved so paragraphs with real content stay visible.

CSS-only patch. No PHP, JS, template, DB schema, or settings changes.
Drop-in replacement for v1.4.10.

= 1.4.10 =
**Fix: True root cause of empty vertical gap inside the sticky bar.**

The empty space between the swatches strip and the qty/ATC row was
*not* coming from WooCommerce's `.woocommerce-variation` slots (the
v1.4.9 guess) — DevTools inspection on production revealed an empty
`<p class="warranty_info"></p>` injected into the canonical form by
the theme (Astra) or another plugin via one of WooCommerce's variation-
form action hooks.

The `<p>` itself was zero-height, but it carried the browser's user-
agent default `margin-block-start: 1em; margin-block-end: 1em;` — about
32px of vertical space — and that produced the visible gap in both
sticky and non-sticky modes.

v1.4.10 neutralises any empty `<p>` inside `.wse-canonical-form` **only
when the sticky bar is active** (`.wse-sticky-active`). Non-sticky mode
keeps the existing layout completely untouched, so themed/3rd-party
spacing in the page-level form is preserved. The `:empty` predicate
keeps the rule safe: paragraphs with real content (legitimate warranty
messages, third-party announcements) stay visible. Only truly empty
paragraphs collapse, and only inside the compact sticky UI.

**Design polish:** Sticky bar default padding updated from `10px 12px`
to `0 16px 10px 16px` per ZYMARG design feedback. Top padding removed
(closes a small visual gap at the top of the bar); horizontal padding
raised slightly to 16px; bottom padding unchanged at 10px. Users who
were applying this as a per-instance Elementor Custom CSS override can
now remove that override.

CSS-only patch. No PHP, JS, template, DB schema, or settings changes.
Drop-in replacement for v1.4.9.

= 1.4.9 =
**Fix: Empty vertical gap inside the sticky Add-to-Cart bar.**

When the sticky Add-to-Cart bar was active, a ~30-40px empty white space
appeared between the swatches strip and the qty / Add-to-Cart / Buy Now
row. The space came from WooCommerce's `.woocommerce-variation` slot
group inside `.single_variation_wrap` — three empty child divs
(`.woocommerce-variation-price`, `-availability`, `-description`) that
WC's `wc-add-to-cart-variation.js` calls `.show()` on at init, writing
inline `style="display: block"` that defeated the previous non-
`!important` hide rule. Each empty slot then asserted its default WC
core margin (~10-15px), totalling the visible ~30-40px gap.

v1.4.9 makes the sticky-mode hide rule `!important`, explicitly targets
each of the three child slots individually (defense in depth in case WC
ever changes its DOM), and zeros their `margin`, `padding`, `min-height`,
and `height` so they contribute zero vertical space inside the sticky bar
regardless of what WC's JS sets inline. Non-sticky mode behavior is
unchanged — those slots still display in the page proper as before.

CSS-only patch. No PHP, JS, template, DB schema, or settings changes.
Drop-in replacement for v1.4.8.

= 1.4.8 =
**Fix: Multi-attribute selected-value label and swatch-state sync.**

On products with two or more variation attributes (e.g. Color + Size), the
first attribute clicked could lose its visible `selected-value` text and,
in some flows, its swatch border — even though the underlying form value
was correct and Add to Cart picked the right variation.

Two compounding causes were addressed:

* `_onResetData` (bound to WC's `reset_data` event) unconditionally wiped
  every swatch's `.selected` class and `.wse-attr-selected-val` text. WC
  fires `reset_data` on EVERY partial selection — not only on explicit
  Clear / no_matching_variations — so any mid-selection click ran this
  handler. v1.4.8 rewrites `_onResetData` to SYNC the UI from the
  canonical form's hidden `<select>` values. If a select still holds a
  value, the matching swatch keeps its `.selected` class, its reset link
  stays visible, and its label text is restored from `title` (or value as
  a fallback). The UI now stays in lockstep with form state regardless
  of who fired `reset_data`.

* `bindCrossWidgetSync` always re-triggered `select.val(value).trigger('change')`
  on every click, even when the form's `WSE_Swatches` instance was
  already scoped to the same widget. `_selectSwatch` had already done
  the mirror, so the cross-sync caused a second WC onChange →
  onCheckVariations cycle — which on partial selections fired
  `reset_data` a second time. v1.4.8 short-circuits the cross-sync when
  the form's wired scope matches the click's source widget.

Drop-in replacement for v1.4.7. No DB schema changes.

= 1.4.7 =
**Feature: Button gap control, widget background, sticky compact layout with full customization.**

Drop-in replacement for v1.4.6. No DB schema changes.

**1. Button Gap Control (Style → Widget Container → Gap Between Buttons)**

Responsive slider to adjust the space between the quantity stepper, Add to Cart button, and Buy Now button. Default: 10px (matches v1.4.6 behavior). Set to 0 for a flush look, or increase for more breathing room.

**2. Widget Background + Container Controls (Style → Widget Container)**

New style section with:
- Background Color
- Padding (responsive)
- Border Radius (responsive)
- Border (full group control)
- Box Shadow

These style the widget's outer `.wse-widget-add-to-cart` wrapper in its normal (non-sticky) state.

**3. Sticky Compact Single-Row Layout (Content → Sticky Layout)**

When the sticky bar is active (fixed to viewport bottom):
- "Compact single-row layout" toggle (default: ON)
- Forces QS + ATC + BN into one horizontal row
- Quantity stepper: 30% width
- ATC + BN: split remaining 70% evenly (flex: 1 1 0)
- Minimal padding for a sleek mobile-friendly bar
- Buy Now message hidden in sticky mode to save space

**4. Sticky Button Order Control**

Dropdown to choose element order inside the sticky bar:
- "QS + Add to Cart + Buy Now" (default)
- "QS + Buy Now + Add to Cart"

Uses CSS `order` property so the DOM order stays consistent for accessibility.

**5. Sticky Bar Full Customization (Style → Sticky Bar)**

Dedicated style section that ONLY applies when the widget is in sticky mode:
- Background Color (default: #ffffff)
- Padding (responsive, default: 10px 16px)
- Margin (responsive)
- Width (responsive, default: 100%)
- Border (full group control)
- Border Radius (responsive)
- Box Shadow (default: subtle upward shadow)
- Gap Between Elements (default: 8px)

**6. JS Update — wse-sticky-active class**

The sticky JS logic now adds/removes `wse-sticky-active` class on all `.wse-widget-add-to-cart` elements (not just presenters) based on the current breakpoint. This class gates all sticky-specific CSS rules so normal and sticky states are completely independent.

**Files changed**

* `widgets/class-widget-add-to-cart.php` — Sticky Layout content section + Widget Container style section + Sticky Bar style section + compact/order classes in render()
* `assets/js/add-to-cart.js` — Updated getActiveStickyHeight() to add/remove wse-sticky-active class on all widgets
* `assets/js/add-to-cart.min.js` — minified
* `assets/css/add-to-cart.css` — Sticky compact layout CSS + sticky-active base styles
* `assets/css/add-to-cart.min.css` — minified
* `woo-swatches-elementor.php` — Version 1.4.7
* `readme.txt` — Stable tag, Changelog

**Migration**

Drop-in replacement for v1.4.6. After install: hard-refresh (Ctrl+F5) + Hostinger Cache Manager → Purge All. The sticky compact layout is ON by default — if you prefer the old stacked sticky look, set "Compact single-row layout" to No in Content → Sticky Layout.

= 1.4.6 =
**Enhancement: Buy Now position control + unified quantity stepper + matched button design.**

Drop-in replacement for v1.4.5. CSS + PHP template changes. No DB / JS logic changes.

**1. Buy Now button position control**

New dropdown in Widget 2 → Content → Buy Now Button → "Button Position":
- "Add to Cart above, Buy Now below" (default — same as v1.4.5)
- "Buy Now above, Add to Cart below"

Works on all product types (simple, variable, presenter).

**2. Unified quantity stepper (no double borders)**

Previously the [-] button, quantity input, and [+] button each had their own individual borders, creating a "double border" look at the junctions. v1.4.6 puts a single 2px outer border with 6px radius on the `.wse-qty-stepper` container and removes all individual child borders. The [-] and [+] buttons now use subtle 1px internal dividers instead. The result is a clean single-box stepper that matches the Buy Now button's design.

**3. Matched Add to Cart button design**

The Add to Cart button now uses the same border/radius/padding as the Buy Now button:
- 2px border (uses `currentColor` so existing color settings are preserved)
- 6px border radius (rounded corners)
- 0.7em vertical / 1.5em horizontal padding (comfortable tap target)

Colors are NOT changed — they remain whatever the user has set in the Elementor Style controls.

**Files changed**

* `widgets/class-widget-add-to-cart.php` — Buy Now position dropdown control
* `templates/add-to-cart/variable.php` — Position-aware Buy Now rendering
* `templates/add-to-cart/simple.php` — Position-aware Buy Now rendering
* `assets/css/add-to-cart.css` — Unified stepper + matched ATC button design
* `assets/css/add-to-cart.min.css` — minified
* `woo-swatches-elementor.php` — Version 1.4.6
* `readme.txt` — Stable tag, Changelog

**Migration**

Drop-in replacement for v1.4.5. After install: hard-refresh (Ctrl+F5) + Hostinger Cache Manager → Purge All to load the new CSS. Existing Elementor style overrides (border color, radius, padding set via widget controls) will cascade on top of these defaults as before.

= 1.4.5 =
**Feature: Buy Now button integrated into the Add to Cart widget.**

Adds a "Buy Now" button to Widget 2 (ZYMARG Add to Cart) that skips the cart and takes the customer directly to checkout. The customer's existing cart is preserved — never modified — and restored after purchase or abandonment.

**How it works**

1. Enable via Widget 2 → Content → Buy Now Button → Show Buy Now Button = Yes
2. Button renders after the quantity stepper + Add to Cart button
3. On click: AJAX saves existing cart → swaps with Buy Now product → redirects to checkout
4. After order: original cart is restored automatically
5. If customer navigates away from checkout: both the Buy Now product AND existing cart items are kept

**Variation support**

- Button starts disabled on variable products until a variation is selected
- Syncs with WooCommerce's `found_variation` / `reset_data` events
- Reads the page's quantity input automatically

**Elementor controls (Content tab)**

- Show Buy Now Button (toggle)
- Button Text (editable, default: "Buy Now")
- Full Width (toggle, default: Yes)

**Elementor controls (Style tab)**

- Typography
- Text Color / Background (Normal + Hover tabs)
- Border + Border Radius
- Padding
- Spacing Above

**Files added/changed**

* `includes/class-buy-now.php` — NEW: AJAX handler + session management + isolated checkout
* `includes/class-plugin.php` — Registers WSE_Buy_Now
* `widgets/class-widget-add-to-cart.php` — Buy Now content + style controls
* `templates/add-to-cart/variable.php` — Buy Now button rendering
* `templates/add-to-cart/simple.php` — Buy Now button rendering
* `templates/add-to-cart/variable-presenter.php` — Buy Now button rendering
* `assets/js/add-to-cart.js` — Buy Now JS logic (variation sync, click handler, AJAX)
* `assets/js/add-to-cart.min.js` — minified
* `assets/css/add-to-cart.css` — Buy Now styles
* `assets/css/add-to-cart.min.css` — minified
* `woo-swatches-elementor.php` — Version 1.4.5
* `readme.txt` — Stable tag, Changelog

**Migration**

Drop-in replacement for v1.4.4. After install: hard-refresh (Ctrl+F5) + Hostinger Cache Manager → Purge All. The Buy Now button is OFF by default — enable it per-widget when ready. No conflict with the standalone Buy Now Widgets plugin (different AJAX action name + session keys).

= 1.4.4 =
**Enhancement: hover-to-preview now works on ALL thumbnails (not just variation thumbnails).**

Drop-in replacement for v1.4.3. JS + minor PHP control change. No DB / template changes.

**The change**

Previously, the "Hover-to-preview" feature only fired on thumbnails carrying the `.zymarg-vig-thumb--variation` class (variation featured images). Parent gallery thumbnails were excluded — hovering them did nothing.

v1.4.4 expands the hover preview to fire on **any** `.zymarg-vig-thumb` element. Now hovering ANY thumbnail — whether it's a parent gallery image, a variation featured image, or anything else in the strip — temporarily shows that image in the main area. Mouse-leave reverts to the previously active image. Click commits.

Also:
- The Elementor control no longer requires `gallery_image_source != parent_only` — hover preview is available in all gallery source modes.
- Added a skip for the currently-active thumbnail (hovering the already-active image is a no-op to avoid unnecessary work).
- Properly clears srcset/sizes on mouseenter when the hovered image doesn't have them (prevents stale attributes from a previous image).

**Files changed**

* `assets/js/gallery.js` — `bindHoverPreview()` expanded to `.zymarg-vig-thumb`
* `assets/js/gallery.min.js` — minified sync
* `widgets/class-widget-variation-image-gallery.php` — removed condition on control
* `woo-swatches-elementor.php` — Version 1.4.4
* `readme.txt` — Stable tag, Changelog

**Migration**

Drop-in replacement for v1.4.3. After install: hard-refresh (Ctrl+F5) + Hostinger Cache Manager → Purge All. If you had previously set the toggle to Yes but it wasn't working, it will now work immediately after cache clear.

= 1.4.3 =
**Enhancement: swatches auto-scroll when horizontal scroll is enabled.**

Drop-in replacement for v1.4.2. JS-only change in `assets/js/swatches.js`. No PHP / template / DB changes.

**The change**

Previously, the swatch strip auto-scroll feature required TWO separate toggles to be enabled:
1. "Enable horizontal scroll" = Yes
2. "Auto-scroll into view" = Yes (a separate, hidden toggle that defaulted to OFF)

This meant that when the gallery's reverse-sync selected a swatch (via thumbnail click, keyboard navigation, or mobile swipe), the swatch strip would NOT scroll to show the newly active swatch — even though horizontal scroll was enabled. Users had to discover and enable the second toggle manually.

**v1.4.3 removes the second toggle requirement entirely.** Now, whenever horizontal scroll is enabled for image swatches at any breakpoint, auto-scroll into view works automatically. The swatch strip smoothly scrolls to center the active swatch whenever:
- Gallery reverse-sync selects a swatch (thumbnail click / keyboard nav / swipe)
- User clicks a swatch directly
- Keyboard arrow navigation within swatches

**Files changed**

* `assets/js/swatches.js` — `_maybeScrollActiveIntoView()` simplified
* `assets/js/swatches.min.js` — minified sync
* `woo-swatches-elementor.php` — Version 1.4.3
* `readme.txt` — Stable tag, Changelog

**Migration**

Drop-in replacement for v1.4.2. After install: hard-refresh (Ctrl+F5) + Hostinger Cache Manager → Purge All to ensure the new swatches.js is loaded.

= 1.4.2 =
**Fix: variation thumbnails no longer disappear when a variation is selected (gallery_source = both/variation_only).**

Drop-in replacement for v1.4.1. JS-only fix in `assets/js/gallery.js`. No PHP / template / DB changes.

**The bug**

After v1.4.1, the initial page load correctly displayed all 15 thumbnails (variation featured images + parent gallery). However, the moment a user clicked a variation thumbnail (triggering reverse-sync → swatch selection → `found_variation` event), all OTHER variation thumbnails disappeared — leaving only the selected variation's image + parent gallery images (8 thumbnails instead of 15).

**Root cause**

The `bindVariationSync()` handler in `gallery.js` responded to `found_variation` by calling `switchToVariation(state, '123')`, which read `state.images['123']` — the per-variation key containing only that variation's featured image + parent gallery (8 images). It then called `renderImageList()` which **rebuilt the entire thumbnail strip** from that reduced 8-image set, wiping out all other variation featured images.

This is the "Mode A vs Mode B" conflict: initial render used the extended list (all variations + gallery), but the variation-selection handler rebuilt from the per-variation key (single variation + gallery).

**Fix**

When `gallery_source` is `'both'` or `'variation_only'` (i.e., the extended list is active), the `found_variation` handler now **navigates to the matching variation's image within the current extended list** using `switchToIndex()` instead of rebuilding. The `reset_data` handler similarly navigates to index 0 instead of triggering a full rebuild.

The gallery dataset stays intact — all 15 thumbnails remain visible at all times. Only the active/highlighted image changes.

**Verification chain**

- Page load — 15 thumbnails visible ✅
- User clicks variation thumbnail → reverse-sync → swatch selected → `found_variation` fires → gallery navigates to that image's index. All 15 thumbnails remain. ✅
- User clicks a different swatch directly → `found_variation` fires → gallery navigates to matching image index. All 15 thumbnails remain. ✅
- User clicks "Clear" → `reset_data` → gallery resets to index 0. All 15 thumbnails remain. ✅
- `parent_only` mode (v1.3.x behavior) → unchanged, still uses per-variation rebuild as before. ✅

**Files changed**

* `assets/js/gallery.js` — `bindVariationSync()` rewritten for extended mode
* `assets/js/gallery.min.js` — minified sync
* `woo-swatches-elementor.php` — Version 1.4.2
* `readme.txt` — Stable tag, Changelog

**Migration**

Drop-in replacement for v1.4.1. After install: hard-refresh (Ctrl+F5) + Hostinger Cache Manager → Purge All to ensure the new gallery.js is loaded.

= 1.4.1 =
**Critical fix for v1.4.0 — variation thumbnails disappeared moments after page load.**

Drop-in replacement for v1.4.0. Single-line substantive PHP change in `widgets/class-widget-variation-image-gallery.php` `render()`. No JS / template / DB / settings changes.

**The bug**

After upgrading to v1.4.0 and switching a Variation Image Gallery widget to "Product Gallery + Variation Images", customers saw the gallery counter briefly show "1 / 15" (full extended list with variation thumbs) during page load, then **drop to "1 / 7"** (parent-only list) the moment the page finished loading. The variation thumbnails disappeared without any user interaction.

**Root cause** (the smoking gun was the 15 → 7 transition with no user input)

The v1.4.0 architecture was:
1. **PHP** — `render()` builds the extended list ($current) for the INITIAL DOM render — 15 images. ✅
2. **PHP** — JSON-encodes `$images_map` into `data-variation-images` for the JS to use on variation swaps. The map's `'0'` key (the "no variation matched" fallback) held only the parent-only list — 7 images. ❌
3. **JS** — `state.images = JSON.parse(data-variation-images)` → `state.images['0']` = 7 images.
4. **WC's variation form** — fires `reset_data` during its own init, BEFORE any user interaction.
5. **JS** — `bindVariationSync()` listens for `reset_data` → calls `switchToVariation(state, '0')` → reads `state.images['0']` = 7 images → `renderImageList()` rebuilds the strip from those 7 images, **wiping the server-rendered 15-image strip**.

**Fix** (1 line of code + 16 lines of explanatory comment)

After `$current` is built, when `gallery_image_source != 'parent_only'`, override `$images_map['0']` with the same `$current` extended list:

```php
} else {
    $current = $this->build_extended_image_list( ... );

    // v1.4.1 (B1) — Make the variation map's "no variation matched"
    // key ('0') carry the SAME extended list that's about to be
    // server-rendered, so the JS reset path doesn't wipe it.
    $images_map['0'] = $current;
}
```

Per-variation keys (the variation IDs as strings) stay unchanged so swatch-driven variation swap still loads each variation's specific image set as designed.

**Verification chain end-to-end after fix**

- Initial page load — server renders extended 15-image strip ✅
- WC `reset_data` during form init — JS reads `state.images['0']` which is now ALSO the 15-image extended list, re-renders identical content. Counter stays at 15. ✅
- Customer picks "Blue" swatch — `found_variation` event → `switchToVariation('123')` → reads variation 123's specific image set (unchanged by this fix). Gallery swaps to Blue's images. ✅
- Customer clicks "Clear" — `reset_data` → returns to extended 15-image view. ✅
- Reverse-sync (v1.4.0 killer feature) — JS rebuilds via `renderImageList()`; `buildThumbHtml`/`buildCarouselSlideHtml` correctly emit `data-variation-id` + `data-variation-attrs` from the extended list records. Click-to-select-variation still works. ✅

**Files changed**

* `widgets/class-widget-variation-image-gallery.php` — 1 substantive line in `render()`
* `woo-swatches-elementor.php` — Version 1.4.1
* `readme.txt` — Stable tag, Changelog, Upgrade Notice

**Migration**

Drop-in replacement for v1.4.0. After install: hard-refresh (Ctrl+F5) + Hostinger Cache Manager → Purge All. The browser-cached pre-1.4.1 PHP-rendered HTML doesn't matter (this is a PHP-side fix), but JS-cached old gallery.js could mask the verification.

= 1.4.0 =
**Minor: variation featured images in the gallery + bidirectional sync.**

Drop-in replacement for v1.3.8. No DB schema changes. Existing widget instances render exactly the same as v1.3.8 (default `gallery_image_source` = `parent_only` preserves back-compat) — opt in per-widget when ready.

**The integration**

Pre-1.4.0 the Variation Image Gallery widget showed only the parent product's gallery. Variation featured images were hidden until the customer clicked a swatch (one-way sync via `found_variation`). v1.4.0 lets the gallery INCLUDE variation featured images directly as thumbnails AND reverse-syncs them to the swatches: clicking, swiping, or arrow-keying to a variation's image automatically picks the matching variation in Widget 1, and the whole plugin (price, add-to-cart, smart heading) updates as if the customer had clicked the swatch directly. The two widgets behave like one integrated system instead of two separate widgets.

**New core controls (Widget 4 → Content → Variation Sync)**

* `gallery_image_source` (SELECT, default `parent_only`):
  * **Product Gallery Only** — pre-1.4.0 behavior, kept as default for back-compat
  * **Variation Images Only** — clean fashion-forward UX (only color thumbs)
  * **Product Gallery + Variation Images** — most common merchant choice
* `variation_image_order` (SELECT, conditional on source=both, default `variation_first`) — variations-first or gallery-first ordering
* `gallery_dedupe` (SWITCHER, default ON) — when a vendor uses the same image for both parent gallery AND a variation featured image, prevents duplicate thumbnails. Variation association wins on dedupe so reverse-sync remains functional.
* `variation_triggers_selection` (SWITCHER, default ON) — when ON, variation thumb clicks select the matching variation; when OFF, thumbs are decorative only.

**S4 — Multi-attribute behavior (auto-detect, default)**

When a variation has multiple attributes (color + size + material…), the plugin auto-detects which attribute is "image-bearing" — i.e., the attribute whose values produce distinct featured-image sets (typically Color or Pattern). Reverse-sync only sets the image-bearing attribute, leaving Size / Material / etc. preserving the customer's existing picks. Matches the Amazon / Nike / ASOS pattern. New control `image_bearing_attribute` lets power users override to "Set ALL attributes" if preferred. For single-attribute products the two behaviors are identical (no difference).

Algorithm: For each attribute, group variations by attribute value, get the unique image-id set per group. If all groups produce distinct signatures → image-bearing. If any two groups share images → not image-bearing.

**S5 — Lazy-load variation thumbs**

All variation thumbnails after index 5 in the rendered list get a hard `loading="lazy"` regardless of other settings. Keeps mobile data usage low when a product has 10+ variation images. Server-side template + client-side `buildThumbHtml` both honor this.

**S6 — Hover-to-preview (desktop only, opt-in)**

New `hover_preview_desktop` SWITCHER. When ON: hovering a variation thumbnail temporarily shows that variation's image in the main area without committing the selection. Mouse leave reverts to the previously-active image. Click commits via the normal flow. Touch devices ignore this (no mouseenter on tap) so the toggle naturally only affects desktop. Premium UX (Zara / Nike pattern).

**Architecture**

Server-side (PHP):

* `format_image_data()` extended with optional `variation_id` and `attributes` args. Each image record now carries variation association (or `0` + `[]` for parent-only images).
* `build_extended_image_list()` — composes the gallery list per source mode + order + dedupe. Used when source ≠ `parent_only`. The original `build_variation_images_map()` still runs unconditionally for the swatch-driven forward-sync path.
* `dedupe_image_list_prefer_variation()` — variation association wins the dedupe conflict so reverse-sync stays functional on shared images.
* `detect_image_bearing_attributes()` — auto-detects which attribute keys are image-bearing using the algorithm above. Returns empty array when in `all` mode or when the auto-detector finds nothing.
* `render()` chooses initial image list per source mode, then emits 4 new data-attrs on the wrapper: `data-gallery-source`, `data-trigger-selection`, `data-image-bearing-attrs` (JSON), `data-hover-preview`.

Templates (`thumbnail.php`, `main-image.php`, `vertical-thumbs.php` carousel slides) — emit `data-variation-id` and `data-variation-attrs` per element when the image is variation-associated. JS reads these on click / swipe / arrow-key.

Client-side (`gallery.js`):

* New `syncSwatchesFromGalleryImage(state, img)` helper — reads `img.variation_id` + `img.attributes`, applies S4 image-bearing filter (when state has the list), finds matching `.wse-swatch[data-attribute][data-value]` in Widget 1, triggers click. The click cascades through the existing v1.3.x chain (selectSwatch → form change → WC found_variation → price update → add-to-cart enable → smart heading).
* New `bindHoverPreview(state)` — desktop hover handler with `hoverPreviewBackup` save/restore via `setTimeout` to avoid racing the click commit.
* `bindVariationSync()` extended with `state.suppressSwatchSync = true` flag wrapping the handler — releases on next tick. Loop prevention: when the gallery responds to a swatch-driven `found_variation` event, the resulting `switchToIndex` won't turn around and re-trigger swatch selection. Same pattern proven in v1.3.5 F1 (carousel scroll vs thumb click loop).
* `switchToIndex()` extended with single-line reverse-sync call gated on `state.triggerSelection && ! state.suppressSwatchSync`. Every navigation source (thumb click, swipe, arrow-key, dot click, lightbox prev/next) routes through here, so reverse-sync fires on all of them automatically.
* `buildThumbHtml()` and `buildCarouselSlideHtml()` — emit `data-variation-id` and `data-variation-attrs` (JSON-encoded `attributes`) when the image record carries them. Used when JS re-renders the strip after a swatch swap.

**Files changed**

* `widgets/class-widget-variation-image-gallery.php` — 6 new controls + 3 new helper methods + render() reworked
* `templates/gallery/thumbnail.php` — variation data attrs + S5 lazy-load
* `templates/gallery/main-image.php` — variation data attrs
* `templates/gallery/layouts/vertical-thumbs.php` — carousel slide variation data attrs
* `assets/js/gallery.js` (+ .min) — `syncSwatchesFromGalleryImage`, `bindHoverPreview`, suppressSwatchSync flag, updated builders (842 → 1187 LOC)
* `woo-swatches-elementor.php` — Version 1.4.0
* `readme.txt` — Stable tag, Changelog, Upgrade Notice, Roadmap pop

**Migration**

Drop-in replacement for v1.3.8. **No DB schema changes**, **no settings reset**. Default `gallery_image_source = parent_only` means existing widgets behave identically to v1.3.8 until you flip the source dropdown. After install: hard-refresh (Ctrl+F5) + Elementor → Tools → Regenerate CSS + Hostinger Cache Manager → Purge All. Then per-product: open the Variation Image Gallery widget in Elementor → Content tab → Variation Sync section → set "Gallery image source" to "Both" or "Variation Only" to enable the new feature.

= 1.3.8 =
**Patch + behaviour change: keyboard nav auto-selects + mobile scroll-to-form removed.**

Drop-in replacement for v1.3.7. No DB schema changes. JS-only changes in `assets/js/swatches.js`.

**Bug fixes**

* **B1 — Arrow-key navigation moved focus but didn't actually select the variation.** Pre-1.3.8 when a customer clicked the first swatch (which selected correctly: gallery + price + add-to-cart all updated) and then pressed Arrow keys to navigate to other swatches, only the visual focus moved — the variation never actually changed. The customer had to press Enter / Space (or click again) to commit the selection, which felt broken. This violated the [WAI-ARIA radiogroup automatic-activation pattern](https://www.w3.org/WAI/ARIA/apg/patterns/radio/) which mandates "Pressing the right arrow or down arrow moves focus to the next radio button **and selects it**".

  **Fix:** Added a call to `_selectSwatch()` after the focus change in both arrow-key branches (`ArrowRight`/`ArrowDown` and `ArrowLeft`/`ArrowUp`). This triggers the same chain as a click — variation form `change` event, gallery main-image swap, price update, add-to-cart enable/disable, smart heading recompute, etc. The desktop user can now click the first swatch then arrow-navigate through the rest with each press updating everything in real time.

* **B2 — Mobile auto-scroll-to-Add-to-Cart removed.** Pre-1.3.8 the `bindMobileScrollToForm()` function (added in v1.2.1 S3) ran a hardcoded `window.scrollTo()` on every swatch click on mobile, jumping the page down to the canonical form. Customers reported this as disorienting — the page felt like it "broke" after every variation tap. Per ZYMARG product-owner decision, the function and its call site are both removed. The customer now stays exactly where they are after picking a swatch on mobile. No scroll, no focus change, no anchor change.

  Stores that liked the v1.2.1 behaviour can re-introduce it via a small custom JS snippet — happy to add a per-widget opt-in toggle in a future release if requested.

**Files changed**

* `assets/js/swatches.js` (+ .min) — B1 add `_selectSwatch` after arrow-key focus change; B2 delete `bindMobileScrollToForm` function + its call site (732 LOC, down from 769)
* `woo-swatches-elementor.php` — Version 1.3.8
* `readme.txt` — Stable tag, Changelog, Upgrade Notice

**Migration**

Drop-in replacement for v1.3.7. After install: hard-refresh (Ctrl+F5) + Hostinger Cache Manager → Purge All. Browser JS cache is the most likely reason the changes appear not to take effect after upgrade.

= 1.3.7 =
**Critical follow-up to v1.3.6: fieldset width constraint completes the layout chain.**

Drop-in replacement for v1.3.6. No DB schema changes. Single-file CSS edit (~10 added properties on one rule).

**Bug fixes**

* **B1 — Image swatches container still overflowed in hscroll mode despite v1.3.6.** v1.3.6 added `width: 100%; max-width: 100%; min-width: 0` to `.wse-attr-block` and `.wse-swatches` — but the rendered DOM has a `<fieldset class="wse-fieldset">` element BETWEEN those two layers (`.wse-attr-block` → `<fieldset>` → `<ul.wse-swatches>`). The fieldset only had `padding: 10px` from v1.3.2 — no width constraint.

  HTML5 fieldsets default to `min-inline-size: min-content` (a per-spec quirk) which lets them grow to fit their content regardless of parent width. So in hscroll mode (`flex-wrap: nowrap` on `.wse-swatches`):
  1. `.wse-attr-block` is constrained to parent column width ✅
  2. `<fieldset>` has no width → grows to fit its content (the ul) which itself wants 100% of fieldset → recursive expansion to fit all swatches in one row
  3. `.wse-swatches { width: 100% }` resolves to 100% of the overflowed fieldset
  4. Net result: the entire chain overflows the column

  **Fix:** Extend the existing `fieldset.wse-fieldset` rule with:
  - `width: 100%; max-width: 100%; box-sizing: border-box` — match parent
  - `min-width: 0` — defeat flex-item min-width:auto default
  - `min-inline-size: 0` — explicit override of the HTML5 fieldset min-content quirk (this is the key piece — `min-width: 0` alone doesn't fully address the fieldset-specific behavior in some browsers)
  - `margin: 0; border: 0` — normalise so theme-injected fieldset styles (Astra adds a default border) don't push content past the column

  After this, the chain is complete: Elementor widget container → `.wse-widget-swatches` (width:100%) → `.wse-attr-block` (width:100%; min-width:0) → `<fieldset>` (width:100%; min-inline-size:0) → `<ul.wse-swatches>` (width:100%; min-width:0). All hscroll behaviors (clip + swipe + auto-scroll-into-view) now work as intended on every breakpoint.

**Files changed**

* `assets/css/swatches.css` (+ .min) — fieldset rule extended with 7 new properties
* `woo-swatches-elementor.php` — Version 1.3.7
* `readme.txt` — Stable tag, Changelog, Upgrade Notice

**Migration**

Drop-in replacement for v1.3.6. After install: hard-refresh (Ctrl+F5) + Elementor → Tools → Regenerate CSS + Hostinger Cache Manager → Purge All. Browser CSS cache is the most likely reason the fix appears not to work after upgrade — the cached `swatches.css` keeps serving the v1.3.5 / v1.3.6 version.

= 1.3.6 =
**Critical CSS fix: image swatches container now respects parent column width.**

Drop-in replacement for v1.3.5. No DB schema changes, no settings reset. Single-file CSS change.

**Bug fixes**

* **B1 — Image swatches overflowed the column on desktop/tablet AND overflowed the screen on mobile hscroll mode.** Pre-1.3.6 the base `.wse-attr-block` and `.wse-swatches` rules had no explicit width — both were `width: auto` (default for block elements), so the flex container expanded to fit its content rather than respecting the parent column's width. Net effect:
  - **Desktop / tablet (default wrap mode):** With ~10+ swatches the flex container grew wider than the column, pushing past the right edge of the visible viewport. `flex-wrap: wrap` was a no-op because the container had infinite room.
  - **Mobile (hscroll mode from v1.3.5 F1):** `flex-wrap: nowrap` + `overflow-x: auto` should have clipped the strip and provided internal swipe scrolling, but `overflow-x` only fires when content is wider than the container — and since the container had no width, it expanded to fit too. Result: the strip overflowed the screen instead of becoming an internal scrolling area.

  **Fix:** Add `width: 100%; max-width: 100%; box-sizing: border-box; min-width: 0` to both base rules. The `min-width: 0` is the subtle-but-critical part — flex items default to `min-width: auto` which prevents shrinking below content size and would have defeated the constraint by itself. With these in place:
  - Default wrap mode now actually wraps to multiple rows when content exceeds column width
  - Hscroll mode now clips the strip and produces proper internal swipe scrolling

  Both v1.3.5 hscroll behaviors (Show Scrollbar, Auto-scroll Active Into View) are unchanged in their JS / CSS logic — they were correct, the underlying container just wasn't constraining the layout properly.

**Files changed**

* `assets/css/swatches.css` (+ .min) — `.wse-attr-block` and `.wse-swatches` get explicit width / max-width / box-sizing / min-width
* `woo-swatches-elementor.php` — Version 1.3.6
* `readme.txt` — Stable tag, Changelog, Upgrade Notice

**Migration**

Drop-in replacement for v1.3.5. After install: hard-refresh (Ctrl+F5) + Elementor → Tools → Regenerate CSS + Hostinger Cache Manager → Purge All. Browser cache may serve pre-1.3.6 `swatches.css` otherwise.

= 1.3.5 =
**Patch + features: 2 critical bug fixes + 4 features.**

Drop-in replacement for v1.3.4. No DB schema changes; legacy integer values for the 12 admin width controls are treated as px automatically (back-compat handled via new `wse_sanitize_css_length()` helper).

**Bug fixes**

* **B1 — "Show Clear Button" toggle did nothing.** Pre-1.3.5 the toggle in the Widget 1 Elementor controls was being read from settings but never applied — `templates/swatches/wrapper.php` unconditionally rendered the `<a class="wse-reset-link">` element. Fix: wired through a new `wse_show_clear_button` filter following the same tier-0 pattern as `wse_clear_button_text` / `wse_choose_option_placeholder` / `wse_oos_label_suffix`. The widget's `render()` adds the filter at start (returning 'yes' or 'no' based on the `show_clear` setting) and removes it at end. `wrapper.php` checks the filter and skips the element when 'no'.

* **B2 — Image swatch label position dropdown only "Below" worked.** The other three options (`above` / `hover` / `hidden`) silently did nothing despite the CSS rules existing. Two compounding root causes:
  - The class `wse-image-label-pos-X` was only added to `.wse-attr-block` when `'image' === $swatch_type`. Local-attribute type detection sometimes returns a different string (or empty), so the gate skipped image-detected swatches.
  - The CSS rules had no `!important`, losing specificity battles with theme styles (Astra) and Elementor per-instance Style controls.
  
  Fix: belt-and-braces. (1) Always emit `wse-image-label-pos-X` on every `.wse-attr-block` regardless of detected swatch_type — the CSS only does anything for elements that contain `.wse-swatch-image-label`, so harmless on non-image attributes. (2) Bumped specificity with `!important` on the 3 active position rules (`above`, `hover`, `hidden`).

**Features**

* **F1 — Horizontal scroll for image swatches (per-device responsive).** New three-group control set in Widget 1 → Content → Swatches → "Image Swatches — Horizontal Scroll":

  - **Enable horizontal scroll** (3 switchers: Desktop / Tablet / Mobile) — defaults D=OFF, T=ON, M=ON. When ON, the image-type swatches container changes from `flex-wrap: wrap` to a single-row horizontal scroll strip with `scroll-snap-type: x proximity`. Each swatch becomes `flex: 0 0 auto; scroll-snap-align: start` so swipes settle on swatch boundaries.
  - **Show scrollbar** (3 switchers) — defaults all OFF. When OFF, the scrollbar chrome is hidden via `::-webkit-scrollbar { display:none }` + `scrollbar-width: none` (Firefox) + `-ms-overflow-style: none` (IE/legacy Edge). Swipe still works either way; OFF gives a cleaner Amazon/Nike-style look. Conditional on the matching enable-scroll switcher being ON.
  - **Auto-scroll active swatch into view** (3 switchers) — defaults all OFF (opt-in). When ON for a breakpoint and a swatch off-screen is selected, JS smooth-scrolls the strip's `scrollLeft` to center the active swatch in the visible area. Uses direct `scrollLeft` math (NOT `scrollIntoView()` which walks all scrollable ancestors and risks page jumps).

  CSS uses the prefix-class-on-outer-wrapper pattern that descendant selectors handle naturally — no v1.3.4-style mirror-class workaround needed since these are descendant rules, not compound selectors. JS handler in `swatches.js` (`_maybeScrollActiveIntoView`) reads the per-breakpoint classes off the Elementor outer wrapper at click time.

* **F2 — Quantity stepper full-width per-device switchers.** New 3 switchers in Widget 2 → Style → Quantity Stepper Buttons → top of the Sizing subsection: "Full Width Stepper — Desktop / Tablet / Mobile" (defaults all OFF). Independent of the existing `qty_stepper_width_mode` SELECT (which keeps its `auto / custom / full` options for backwards compat). When ON for a breakpoint, CSS applies `width: 100%` on `.wse-qty-stepper` plus `flex: 1 1 auto` on the qty input inside the matching `@media` query. Quick discoverable shortcut matching the existing "Add to Cart Full Width" pattern.

* **F3 — "Hidden" added as third Label Position option.** The general `label_position` dropdown (Widget 1 → Content → Label) gains a third option `Hidden` alongside the existing `Above swatches` / `Beside swatches`. New CSS rule `body.wse-stylesheet-enabled .wse-label-hidden .wse-attr-label-row { display: none !important }`. Complements the existing `show_label` Yes/No toggle by giving users a third position option that's natural inside the dropdown.

* **F4 — Admin width controls accept multiple units.** All 12 fields under WC → Settings → WooSwatches → Display → Swatch Sizes (color / image / label / button × desktop / tablet / mobile) changed from `type: 'number'` to `type: 'text'`. Each accepts any CSS length: `32px`, `10%`, `2em`, `1.5rem`. Defaults to `px` if no unit is given. Legacy stored integer values (pre-1.3.5) get treated as `px` automatically via the new `wse_sanitize_css_length()` helper that:
  - Validates input against `^(\d+(?:\.\d+)?)(px|%|em|rem)$`
  - Falls back to the field's default if invalid
  - Normalizes `32.0px` → `32px`
  - Treats plain integers as px (back-compat)

  The CSS-generation code in `class-assets.php` no longer hard-codes `'px'` suffixes — values now carry their own unit. Per the senior dev's recommendation, `vw` / `vh` are deliberately not accepted (no clear use case for swatch widths).

**Files changed**

* `widgets/class-widget-swatches.php` — B1 filter wiring, B2 always-emit class, F1 9 hscroll switchers, F3 hidden option
* `widgets/class-widget-add-to-cart.php` — F2 3 fullw switchers
* `templates/swatches/wrapper.php` — B1 honor wse_show_clear_button filter
* `assets/css/swatches.css` — B2 !important on 3 position rules, F1 9 @media rules + scrollbar hiding, F3 wse-label-hidden rule
* `assets/css/add-to-cart.css` — F2 3 @media rules for full-width
* `assets/js/swatches.js` — F1 _maybeScrollActiveIntoView helper + call from _selectSwatch
* `includes/class-settings.php` — F4 12 fields converted to text type with multi-unit description
* `includes/class-assets.php` — F4 wse_sanitize_css_length helper + drop hardcoded 'px' suffixes from CSS templates
* `woo-swatches-elementor.php` — Version 1.3.5
* `readme.txt` — Stable tag, Changelog, Upgrade Notice

**Migration**

Drop-in replacement for v1.3.4. No DB schema changes. After install: hard-refresh (Ctrl+F5) + Elementor → Tools → Regenerate CSS + Hostinger Cache Manager → Purge All. Existing widget settings keep working unchanged. Existing admin width values (stored as integers) automatically get `px` appended at runtime — no manual re-entry needed.

= 1.3.4 =
**Critical patch: prefix_class controls now actually work.**

Drop-in replacement for v1.3.3, no DB migration. Update strongly recommended for everyone on v1.3.x — fixes a serious regression introduced in v1.3.3 that hid all thumbnails on desktop, plus a long-standing latent bug where the Aspect Ratio dropdown only worked at its default value.

**Root cause** (single architectural fix that resolves multiple symptoms):

Elementor's `prefix_class` control parameter applies the class to the widget's **OUTER** wrapper element (`.elementor-widget-…`), not to the inner `.zymarg-vig` div that the gallery's CSS selectors target. The compound CSS rules `.zymarg-vig.zymarg-vig-ar-16-9` therefore never matched (the two classes lived on different elements). 7 different controls were affected: `aspect_ratio`, `show_thumbs_desktop / _tablet / _mobile`, `sticky_main_desktop`, `counter_position`, `sale_badge_position`. Symptoms varied by control:
* `aspect_ratio` — only the default (1:1) ever worked, dropdown choice for 4:5 / 3:4 / 16:9 / Auto did nothing
* `show_thumbs_*` — never worked in v1.0–v1.3.2; v1.3.3's `:not(.X-yes)` "fix" actually made it worse, since `:not()` always evaluated true on `.zymarg-vig` (the `-yes` class is on the parent, never on `.zymarg-vig` itself), hiding thumbs unconditionally on desktop
* `sticky_main_desktop`, `counter_position`, `sale_badge_position` — non-default values silently ignored

**Fix**

`render()` in `class-widget-variation-image-gallery.php` now mirrors all 7 prefix_class-driven classes onto the inner `.zymarg-vig` div explicitly:

```php
'zymarg-vig-ar-'           . sanitize_html_class( $settings['aspect_ratio']        ?? '1-1' ),
'zymarg-vig-thumbs-d-'     . ( ( $settings['show_thumbs_desktop'] ?? 'yes' ) === 'yes' ? 'yes' : 'no' ),
'zymarg-vig-thumbs-t-'     . ( ( $settings['show_thumbs_tablet']  ?? 'yes' ) === 'yes' ? 'yes' : 'no' ),
'zymarg-vig-thumbs-m-'     . ( ( $settings['show_thumbs_mobile']  ?? 'yes' ) === 'yes' ? 'yes' : 'no' ),
'zymarg-vig-sticky-'       . ( ( $settings['sticky_main_desktop'] ?? 'yes' ) === 'yes' ? 'yes' : 'no' ),
'zymarg-vig-counter-pos-'  . sanitize_html_class( $settings['counter_position']    ?? 'bottom_left' ),
'zymarg-vig-badge-'        . sanitize_html_class( $settings['sale_badge_position'] ?? 'top_left' ),
```

The compound selectors `.zymarg-vig.zymarg-vig-X` now match because both classes live on the same element. v1.3.3's `:not(.X-yes)` thumb-toggle CSS reverted to the original `.X-no` form (now correct because the `-no` class is explicitly emitted by PHP).

The `prefix_class` parameter on each control is intentionally kept (so Elementor's editor live-preview can react quickly when the user toggles a value); the duplicate class on the outer wrapper is harmless.

**Files changed**

* `widgets/class-widget-variation-image-gallery.php` — 7 mirror classes added to `$wrapper_classes`
* `assets/css/gallery.css` (+ .min) — 3 thumb-toggle rules reverted from `:not(.X-yes)` to compound `.X-no`
* `woo-swatches-elementor.php` — Version 1.3.4
* `readme.txt` — Stable tag, Changelog, Upgrade Notice

**Migration**

Drop-in replacement for v1.3.3. No DB schema changes, no settings reset. After install: hard-refresh (Ctrl+F5) + Elementor → Tools → Regenerate CSS + Hostinger Cache Manager → Purge All. Then verify: the Aspect Ratio dropdown should respect 4:5 / 3:4 / 16:9 / Auto choices, and the Show Thumbnails Desktop/Tablet/Mobile toggles should hide and show thumbs as labeled.

= 1.3.3 =
**Patch: 2 critical fixes + 7 gallery polish items.**

Drop-in replacement for v1.3.2, no DB migration. Update strongly recommended for everyone on v1.3.x.

**Bug fixes**

* **B1 — "Show thumbnails Desktop / Tablet / Mobile" toggles did nothing.** v1.2.x added these per-device switchers to the Layout section (Content tab) but the CSS never matched the actual class Elementor produced. Root cause: CSS rule was `.zymarg-vig-thumbs-d-no` (matching the `-no` class for OFF state), but Elementor's Switcher with `return_value: 'yes'` produces either `.zymarg-vig-thumbs-d-yes` (ON) or **no class at all** (OFF). The `-no` class never existed, so the rule never matched, so the toggle did nothing. Fix: rewrote the three rules as `:not(.zymarg-vig-thumbs-d-yes)` / `-t-yes` / `-m-yes` to correctly hide the thumb strip when the `-yes` class is absent. Each rule also gets `display: none !important` so 3rd-party CSS can't override it.

* **B2 — Sale dot still showing on swatches despite v1.3.2 retirement.** The PHP layer was correct (renderer hardcoded `is_on_sale => false`), but the **CSS rules for `.wse-on-sale::after` still existed in `add-to-cart.css`**. If the class got added by anything (3rd-party code, stale cache, hot-reload), the dot rendered. v1.3.3 belt-and-braces: replaced the dot CSS with an explicit `content: none !important; display: none !important; background: transparent !important; box-shadow: none !important; width: 0 !important; height: 0 !important;` override that defeats every cached / 3rd-party version of the rule across all swatch types (color, image, label, button). The dot can never render again.

**Gallery polish**

* **F1 — Force 1:1 main image (bulletproof).** The aspect-ratio dropdown still defaults to 1:1 (you can change it for power-user use cases like portrait products), but the underlying CSS now uses `aspect-ratio: var(--zymarg-vig-aspect-ratio, 1 / 1)` with an explicit fallback in all three usages (main figure, mobile_carousel slide, mobile_carousel inner main). If the variable somehow becomes unset, 1:1 remains in force. Combined with `object-fit: cover` (existing) and `width: 100%; height: 100%` on the inner `<img>`, any image that's not 1:1 is cleanly cropped to a centered square.

* **F2 — Keyboard navigation now scrolls the thumb strip into view.** Previously when the gallery had more thumbs than the visible strip area, pressing → repeatedly would correctly switch the main image to thumb 6, 7, 8 etc. but the strip's scroll position never updated, so the active thumb (now beyond the visible area) became invisible. Fix: new `scrollThumbsToActive()` helper called from `switchToIndex()` that uses direct `el.scrollLeft / scrollTop` math (NOT `scrollIntoView()` — that walks all scrollable ancestors including the page itself, risking page jumps) to **center the active thumb in its strip without ever scrolling the page**. Works for both vertical and horizontal thumb strips; respects `prefers-reduced-motion`.

* **F3 — Mobile main image swipe (touch) for ALL layouts.** v1.3.2's swipe was only enabled in `mobile_carousel` layout via the `.zymarg-vig-carousel` scroll-snap strip. For `horizontal_below`, `horizontal_above`, `mobile_stacked` — the main image was a single static figure with no swipe. v1.3.3 adds a new `bindMainSwipe()` JS handler that attaches `touchstart/move/end` to the `.zymarg-vig-main` figure and calls `navigate()` on horizontal swipes ≥ 50px (with ≤ 60px vertical tolerance so accidental vertical scrolls don't trigger). Works on every layout, every breakpoint, including touch-enabled laptops.

* **F4 — Image counter rendered INSIDE the figure (always relative to the visible image).** v1.3.2 placed the counter inside `.zymarg-vig-main-wrap`, which had subtle positioning issues in `horizontal_above` layout (column-reverse flips). v1.3.3 moves the counter span into `templates/gallery/main-image.php` so it's a child of the `<figure class="zymarg-vig-main">` itself. The figure has `position: relative` and matches the visible image bounds exactly; the counter's absolute coords are now unambiguous regardless of layout. For `mobile_carousel` (where the figure is hidden and a carousel takes over), a SECOND counter is rendered inside `.zymarg-vig-carousel` (which gets `position: relative`). JS's `updateImageCounter()` now uses `.each()` to keep both counters in sync; CSS `@media` shows whichever is visible at the current breakpoint.

* **F5 — Mobile carousel cleanup: no thumb rail, no dots.** Per ZYMARG product-owner decision, `mobile_carousel` layout is now a clean "swipe carousel + counter only" — no stacked thumbs strip below, no dot indicators. Two new CSS rules with `!important` lock both `.zymarg-vig-thumbs--vertical` and `.zymarg-vig-dots` to `display: none` when the layout is `mobile_carousel`. The image counter `1 / N` replaces the dots since it's more informative.

* **F6 — Thumbnail strip width never exceeds main image width.** Previously in horizontal layouts (above / below) on tablet and mobile the thumb strip stretched to the full container width even when the main image (with `aspect-ratio: 1/1`) was narrower, creating an off-balanced look. v1.3.3 adds `width: 100%; max-width: 100%; box-sizing: border-box; flex-wrap: nowrap` to all 6 horizontal-thumb-strip rules (desktop / tablet / mobile × below / above) so the strip honors its parent's width and overflows horizontally with hidden scrollbar instead of stretching beyond it. Plus `max-width: 100%; box-sizing: border-box` on the layout container itself.

* **F7 — Layout audit fixes.** Reviewed all 6 desktop × 6 tablet × 4 mobile layout combinations. Two small gaps fixed:
  - Tablet `vertical_left` / `vertical_right` now have `gap: 16px` between thumb strip and main image (matching desktop spacing).
  - Mobile `mobile_stacked` now has `gap: 12px` between stacked images for visual breathing room.

  Other layouts verified working: desktop sticky-main is correctly gated to `min-width: 1025px` (not inherited at tablet); grid layouts collapse to 1-column at tablet via existing rule; tablet sticky control isn't needed (desktop-only by spec).

**Architecture note**

Counter rendering moved from layout templates to `main-image.php` (the leaf template), so all three layouts (vertical-thumbs, stacked, grid) get the counter automatically. JS `updateImageCounter()` was generalized from `.first()` to `.each()` so the per-layout structure (single counter inside figure, OR figure-counter + carousel-counter for mobile_carousel) is transparent to the navigation code. Total `gallery.js` growth: 844 → 971 LOC.

**Files changed**

* `assets/js/gallery.js` (+ .min) — F2 `scrollThumbsToActive`, F3 `bindMainSwipe`, F4 multi-counter sync
* `assets/css/gallery.css` (+ .min) — B1 toggle fix, F1 aspect-ratio fallback, F5 carousel cleanup, F6 width constraints, F7 layout gaps, F4 carousel position-relative
* `assets/css/add-to-cart.css` (+ .min) — B2 sale dot belt-and-braces removal
* `templates/gallery/main-image.php` — F4 counter rendered inside figure
* `templates/gallery/layouts/vertical-thumbs.php` — F4 pass counter args + carousel-counter
* `templates/gallery/layouts/stacked.php` — F4 pass counter args (first image only)
* `templates/gallery/layouts/grid.php` — F4 pass counter args (first image only)
* `woo-swatches-elementor.php` — Version 1.3.3
* `readme.txt` — Stable tag, Changelog, Upgrade Notice

**Migration**

Drop-in replacement for v1.3.2. No DB schema changes, no settings reset. After install: hard-refresh (Ctrl+F5), Elementor → Tools → Regenerate CSS, Hostinger Cache Manager → Purge All. The hard-refresh + cache clear is **mandatory** for B2 (sale dot) — the dot may still appear if the browser serves a cached pre-v1.3.3 `add-to-cart.css`.

= 1.3.2 =
**Patch: 2 critical fixes + 6 gallery features + 2 UX upgrades.**

Drop-in replacement for v1.3.1, no DB migration. Update strongly recommended for everyone on v1.3.0 / v1.3.1.

**Bug fixes**

* **B1 — Smart heading + savings span disappeared after page load on variable on-sale products.** Server-side render correctly emitted the smart heading ("LIMITED TIME OFFER" / etc.) plus the "Save Xৎ (Y%)" savings line, but `price.js` blew them away on every WooCommerce variation event. Root cause: the JS used `$widget.html(html)` to update prices, which destroyed sibling elements that the server rendered alongside the price block (`.zymarg-price-heading`, `.zymarg-price-shipping-hint`, `.zymarg-price-savings`). v1.3.2 refactors `price.js` to **surgical DOM updates** via a new `applyPriceState()` function that only touches `.zymarg-price-current`, `.zymarg-price-was`, `.zymarg-sale-badge`, `.zymarg-price-savings` — heading and shipping-hint elements are never touched across found_variation / reset_data events. New `buildSavingsText()` helper extracted so both initial-restore and per-variation paths produce the same formatted savings string.

* **B2 — Sale dot still showing on swatches even with the v1.2.3 toggle off.** The "Show Sale Dot on Swatches" feature is retired. The renderer now hard-codes `is_on_sale => false` on every swatch data array regardless of any saved DB option value, so the `wse-on-sale` class can never be added. The settings UI toggle is removed from `WC → Settings → WooSwatches`. The `wse_show_sale_dot` option key is intentionally NOT cleaned up from the DB (preserves prior preference for any future re-introduction). The CSS rules for `.wse-on-sale::after` stay in `add-to-cart.css` as harmless no-ops, and the `is_term_on_sale()` helper remains available for custom integrations that want to opt back in via a filter.

**Features**

* **F1 — Fieldset padding inside swatches widget.** Adds `body.wse-stylesheet-enabled fieldset.wse-fieldset { padding: 10px; }` to `swatches.css`. Scoped to `.wse-fieldset` so unrelated `<fieldset>` elements on the page (user-account forms, checkout, etc.) are not affected.

* **F2 — WebKit scrollbar fully hidden on the gallery thumbnail strip.** Previously the vertical strip showed a 4px tinted scrollbar and the horizontal/mobile-carousel strips showed the browser default. v1.3.2 hides them entirely via `::-webkit-scrollbar { width: 0; height: 0; display: none }` plus `scrollbar-width: none` (Firefox) and `-ms-overflow-style: none` (IE / legacy Edge). Strips remain scrollable; only the chrome is hidden.

* **F3 — "Horizontal thumbs above main" added to the Mobile Layout dropdown.** Previously only Desktop and Tablet had this option. Mobile dropdown now offers all 4 layouts: mobile_carousel (default), mobile_stacked, horizontal_below, horizontal_above. Implemented via `flex-direction: column-reverse` on the layout wrapper at the mobile breakpoint.

* **F4 — Real mobile carousel: main image actually swipes now.** v1.3.0 / v1.3.1 mobile_carousel layout claimed CSS-only swipe via `scroll-snap-type: x mandatory` on `.zymarg-vig-main-wrap` — but the wrap only contained ONE image, so there was nothing to swipe through. v1.3.2 adds a new `<div class="zymarg-vig-carousel">` element that renders ALL variation images as `<figure class="zymarg-vig-carousel-slide">` siblings in a horizontal scroll-snap strip. Hidden on desktop / tablet (the single `.zymarg-vig-main` hero is shown there); the carousel becomes the visible scroll container at mobile bp. Full bidirectional sync with the thumb strip via a new RAF-throttled scroll observer in `gallery.js` (scrolling the carousel updates the active thumb + dot indicator + counter; clicking a thumb scrolls the carousel to that slide). `loading="eager"` on slide 0, `loading="lazy"` on the rest. iOS inertial scrolling enabled via `-webkit-overflow-scrolling: touch`. Pure CSS swipe — no touch handlers fighting the browser's native scroll thread.

* **F5 — Full keyboard navigation on the thumbnail strip.** Previously only ArrowLeft / ArrowRight worked. v1.3.2 adds:
  - **ArrowUp** / **ArrowDown** (so vertical-strip layouts work as users expect)
  - **Home** — jump to the first image
  - **End** — jump to the last image
  - **Enter** / **Space** when focus is on the main figure — opens the lightbox
  - **Roving tabindex**: `switchToIndex()` now maintains `tabindex="0"` on the new active thumb and `tabindex="-1"` on all others, plus moves focus to the new active thumb after a click. Result: arrow keys keep firing without the user needing to re-tab. Defensive: skips intercepting keys inside form inputs.

* **F6 — Image counter overlay (mobile + tablet only).** New "1 / 3" style counter rendered at bottom-left of the main image, hidden on desktop (where thumbnails are visible so a counter would be redundant). New controls:
  - **Display** section (Content tab): "Show image counter" switcher (default ON) + "Counter format" text field (default `{current} / {total}` — supports any custom format like "Image 1 of 3" or "1 of 3 photos").
  - **Style** section: "Image Counter" with background color, text color, typography group, padding (Dimensions), border-radius slider, and a position dropdown with 4 corners (bottom-left, bottom-right, top-left, top-right) via prefix_class.
  - The counter text auto-updates on every navigation event (thumb click, swipe, arrow key, dot click, lightbox prev/next) via `updateImageCounter()`.

**UX upgrades**

* **S1 — Lightbox swipe gestures (mobile).** Touch swipe inside the lightbox stage now navigates prev / next. Touch handlers track horizontal delta on `touchstart` / `touchmove` / `touchend`; a swipe must be ≥ 50px horizontal travel and ≤ 60px vertical travel to register (so accidental vertical scroll attempts don't trigger navigation). Matches Apple / Sephora / Amazon mobile lightbox UX.

* **S3 — Mouse drag-to-scroll on the thumbnail strip (desktop).** Click + drag the thumb strip to scroll horizontally or vertically (axis follows CSS flex-direction). 5px drag threshold distinguishes a drag from a click — single thumb clicks still work normally. The post-drag click is swallowed via a `wse-was-dragging` flag so users don't accidentally jump to a thumb they didn't intend to select. Apple Store / Nike pattern.

**Architecture note**

`gallery.js` was refactored from ~498 LOC to ~840 LOC. Every input source (thumb click, arrow key, dot click, carousel scroll, lightbox prev/next, swipe gesture, keyboard) now routes through a single `switchToIndex(state, index, listOverride, opts)` function. This eliminates the previous duplicate-state bugs where (e.g.) clicking a thumb updated the dots but not the counter, or a carousel scroll updated the counter but not the active thumb. New helpers: `updateImageCounter`, `scrollCarouselToIndex`, `buildCarouselSlideHtml`, `bindCarouselScroll`, `bindThumbDragScroll`, `bindLightboxSwipe`. RAF-throttling on the carousel scroll observer keeps the iOS scroll thread smooth.

**Files changed**

* `assets/js/price.js` (+ .min) — B1 surgical DOM updates
* `assets/js/gallery.js` (+ .min) — F4/F5/F6/S1/S3 (498 → 844 LOC)
* `assets/css/swatches.css` (+ .min) — F1 fieldset padding
* `assets/css/gallery.css` (+ .min) — F2 hide scrollbars, F3 mobile horizontal_above, F4 carousel/counter styles, F6 counter positions
* `includes/class-swatch-renderer.php` — B2 hard-disable sale dot
* `includes/class-settings.php` — B2 remove toggle UI
* `includes/class-activator.php` — B2 remove default option
* `widgets/class-widget-variation-image-gallery.php` — F3 mobile dropdown option, F4 mobile_carousel_enabled wiring, F6 controls + Style section
* `templates/gallery/layouts/vertical-thumbs.php` — F4 carousel block, F6 counter span

**Migration**

Drop-in replacement for v1.3.1. No DB schema changes, no settings reset. After install: hard-refresh (Ctrl+F5) and Elementor → Tools → Regenerate CSS to flush the editor preview. New widgets get sensible defaults (counter ON, position bottom-left, format `{current} / {total}`); existing widget instances inherit the same defaults via the `??` fallback chain.

= 1.3.1 =
**Patch: gallery now actually renders.**

Critical fix for v1.3.0. The new Variation Image Gallery widget (Widget 4) shipped with a template-output-discard bug: the layout templates (`vertical-thumbs.php`, `stacked.php`, `grid.php`) called the static `WSE_Widget_Variation_Image_Gallery::include_template()` helper without echoing its return value. Since the helper uses `ob_start()` + `ob_get_clean()` and **returns** the rendered HTML as a string (matching the price-widget pattern), the strings were silently discarded by PHP. Net effect: the outer `<div class="zymarg-vig">` wrapper rendered, the layout `<div class="zymarg-vig-layout">` rendered, but every image and thumbnail inside dropped on the floor — gallery looked completely empty in both the Elementor editor and the live frontend.

**Fix**

Added `echo` to all 4 `include_template()` calls inside the 3 layout templates:

* `templates/gallery/layouts/vertical-thumbs.php` — line 41 (thumbnail loop) + line 58 (main image)
* `templates/gallery/layouts/stacked.php` — line 33 (main-image loop, every image full-size)
* `templates/gallery/layouts/grid.php` — line 31 (main-image loop, 2-column grid)

Each call also gets a `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped` annotation since the returned HTML is already escaped at the leaf templates (`main-image.php`, `thumbnail.php` use `esc_url`, `esc_attr`, `esc_html` on every dynamic value).

**Migration**

Drop-in replacement for v1.3.0. No DB schema changes, no settings reset. After install: hard-refresh (Ctrl+F5) and Elementor → Tools → Regenerate CSS to flush the editor preview. The gallery widget will now render images correctly on both the editor canvas and the live single-product page.

= 1.3.0 =
**Minor: Widget 4 — ZYMARG Variation Image Gallery (the big one).**

Brand-new product-image gallery widget that automatically flips to show the matching variation's image when the customer picks a swatch. Built from a fresh study of how Nike, Apple, Adidas, ASOS, Zara, H&M, Sephora, Allbirds, Amazon, and Shopify Dawn all handle this — synthesized into 5 layout patterns and 1 mobile hybrid.

**Layouts (responsive — pick one per device)**

* **Vertical thumbs LEFT + main right** *(default desktop, Apple / Nike / Sephora pattern)*
* **Vertical thumbs RIGHT + main left** *(mirrored variant)*
* **Horizontal thumbs BELOW main** *(Adidas / Zara / WooCommerce default pattern)*
* **Horizontal thumbs ABOVE main**
* **Stacked vertical** *(Allbirds / H&M minimal — every image rendered full-size, no thumb strip)*
* **Grid 2-column** *(Shopify Dawn — all images visible at once)*
* **Mobile hybrid (default)**: swipeable carousel main + stacked vertical thumbs below — gives mobile users both swipe-to-browse and tap-thumb-to-jump
* **Per-device thumb visibility toggles** — separate "Show thumbnails" switchers for Desktop / Tablet / Mobile so you can hide thumbs on small screens without changing the layout

**Variation sync**

* Listens to WooCommerce's `found_variation` and `reset_data` jQuery events on the parent variations form on the same page (auto-paired by product ID via the existing Form Registry, no manual config)
* Maps variation IDs to their `image_id` (and falls back to the parent gallery if the variation has no override). Includes a `wse_variation_image_ids` filter for 3rd-party integrations that want to inject extra images per variation (multi-image-per-variation support roadmap)
* Cross-fade / slide / instant transition options with CLS-safe duration (≤500ms)
* All images preloaded into the same DOM at server-render time so swatch clicks never trigger network requests

**Interactions**

* **Hover-zoom lens** (desktop only — Amazon-style magnifier with cursor-tracked transform-origin on a layered hi-res image, disabled on touch devices since pinch-zoom works natively)
* **Click-to-lightbox** with shared body-level modal: focus restore on close, Esc / arrow / backdrop-click to close, image counter, prev/next arrows, keyboard nav
* **Mobile carousel** with swipe-friendly snap layout + dot indicators wired to scroll position
* **Thumbnail keyboard nav** (focused thumb → arrow keys cycle through siblings)
* **Sticky main image on desktop** (Apple / Sephora pattern — main stays in viewport while customer scrolls description)
* **Sale badge overlay** with editable text and 4 corner positions
* **Lazy-load thumbs below the fold** for fewer initial requests on mobile

**Style tab — full control over every visual element (~50 controls)**

1. **Main Image** — padding (responsive), border-radius (drives `--zymarg-vig-radius`), border, box-shadow, image fit (cover / contain)
2. **Thumbnails (Normal / Hover / Active tabs)** — size, gap, radius, border, opacity, hover lift, active border color/width/opacity. Sizes are responsive sliders driving `--zymarg-vig-thumbs-size` / `-gap` per device
3. **Zoom Lens** *(condition: hover-zoom enabled)* — lens size (drives `--zymarg-vig-lens-size`, read by gallery.js at runtime), shape (rounded square / circle), background, border color and width
4. **Sale Badge** *(condition: badge enabled)* — position (4 corners via prefix-class), background, text color, full typography group, padding (responsive), border-radius, box-shadow
5. **Carousel Dots (mobile)** — size, gap, color, active color (all driven by new CSS vars `--zymarg-vig-dot-*`)
6. **Lightbox** *(condition: lightbox enabled)* — backdrop color, close/arrow icon color, button background, hover background, counter color
7. **Animation** — transition type (cross-fade / slide / instant), duration in ms (≤500ms keeps image swaps out of CLS budget)

**Image / aspect-ratio controls**

* Aspect ratio: 1:1 / 4:5 / 3:4 / 16:9 / Auto (uses original)
* Main image size: any registered WP image size (default `woocommerce_single`)
* Thumbnail size: any registered WP image size (default `woocommerce_gallery_thumbnail`)
* Placeholder color while images load (default ZYMARG Surface Container `#eaedff`)

**Architecture**

* CSS-variable-first design — every visual property exposed as `--zymarg-vig-*` on the widget root so Elementor Style controls are simple `--var: {{VALUE}}` setters with full editor live-preview
* All visual rules gated by `body.wse-stylesheet-enabled` (the existing global stylesheet kill-switch)
* Server-side render pre-builds the variation→images map and renders the active main image directly, so non-JS users still see one of the variation images
* Reduced-motion media query disables transitions and hover-lift for `prefers-reduced-motion: reduce`
* Brand-locked default palette (Primary `#9500a5`, Surface Container `#eaedff`, Outline Variant `#d8bfd3`) — no off-brand colors anywhere

**Files added / changed**

* `widgets/class-widget-variation-image-gallery.php` — Widget 4 class (1372 LOC, ~50 Style controls)
* `templates/gallery/main-image.php` — hero figure (used by every layout)
* `templates/gallery/thumbnail.php` — single thumb button
* `templates/gallery/layouts/vertical-thumbs.php` — vertical thumbs left/right
* `templates/gallery/layouts/stacked.php` — Allbirds / H&M pattern
* `templates/gallery/layouts/grid.php` — Shopify Dawn pattern
* `assets/css/gallery.css` (+ .min) — base layout, vars, sale-badge positions, sticky, reduced-motion
* `assets/css/gallery-lightbox.css` (+ .min) — lightbox modal, dots indicator
* `assets/js/gallery.js` (+ .min) — variation sync, hover-zoom, lightbox, mobile dots, keyboard nav
* `includes/class-plugin.php` — Widget 4 registration
* `includes/class-assets.php` — gallery handles registration

**Migration**

Drop-in replacement for v1.2.3. No DB schema changes. After install, hard-refresh (Ctrl+F5) and clear caches (Elementor → Tools → Regenerate CSS, Hostinger Cache Manager → Purge All). Drop the new "ZYMARG Variation Image Gallery" widget from Elementor's panel onto your single-product template (typically replacing your current product-images element).

= 1.2.3 =
**Patch: 6 bug fixes + 4 features + 9 Tier 0 text controls.**

This release addresses six issues spotted during live ZYMARG site testing of v1.2.2 plus implements the first wave ("Tier 0") of the senior-developer "advanced Elementor control over every text element" feedback. **No DB migration**, drop-in replacement for v1.2.2.

**Bug fixes**

* **Issue 1a — Stepper buttons disabled on simple products.** Root cause: WooCommerce's `get_max_purchase_quantity()` returns `-1` as the "no max" sentinel. v1.2.0 stepper template did not strip `max="-1"` from the rendered `<input>`, so the stepper JS read it as a real upper bound. With val=1 and max=-1, the check `val >= max` was always true, so `[+]` was disabled forever on simple products. Belt-and-braces fix: template strips `max` when value is `-1` or empty; JS `readBounds()` defensively treats `-1` as "no max" too.
* **Issue 5 — F5 responsive per-type swatch widths not working.** Same root cause as v1.2.2 Issue 4 image-label fix: F5 inline CSS at specificity (0,2,1) was beaten by Elementor's per-widget Swatch Size `{{WRAPPER}}` selectors at (0,5,0). Added `!important` on width / height / min-width across all three breakpoints. Per-widget overrides still possible via Elementor Custom CSS with `!important`.
* **Issue 6 — Smart heading missing on variable on-sale products.** Back-compat bug in `resolve_heading_text()`: the fallback chain was hardcoded as `?? 'no'` but the control's actual default is `'yes'` for the sale state. For widget instances saved BEFORE the heading controls existed (pre-v1.2.0 widgets), settings array does not contain the `heading_*_show` keys, so the `?? 'no'` fallback ran and suppressed the heading. v1.2.3 each map entry carries a `show_default` matching the control's actual default, so legacy widget instances inherit the editor's would-be default.
* **Issue 3 — Sale dot showing even when tooltip OFF.** New global setting **WC → Settings → WooSwatches → Show Sale Dot on Swatches** (default ON for back-compat). Independent from the tooltip setting since they're semantically different features (sale-state indicator vs hover label). When OFF, the renderer skips computing `is_term_on_sale()` so the sale-dot CSS hook has nothing to attach to.

**Features**

* **Issue 2 — Quantity input width capped at 160px.** Bumped the slider range from `{px: 40-160}` to `{px: 40-600, %: 10-100, em: 2-30}`. Added `%` and `em` units so the qty field can pair naturally with wide layouts.
* **Issue 3+4 — Add to cart Full Width responsive + stacked layout.** Two new switcher controls: Full Width Button — Tablet (≤1024px) and Full Width Button — Mobile (≤768px). When any device's full-width is on, `.wse-qty-atc-row` switches to `flex-direction: column` with the stepper on top and the full-width button stacked below. Cascade follows Elementor's standard breakpoint order (mobile rule overrides tablet, tablet overrides desktop).
* **Issue 7 — "Show Price Under Image Swatches" feature built from scratch.** v1.2.2 had a Widget 1 control with this label but **no implementation** — the toggle did nothing. v1.2.3 builds it end-to-end: server-side computes the lowest display price for each option value via a new `get_variation_price_html_for_value()` helper; image template always renders `<span class="wse-swatch-image-price">` when a price is available; CSS gates visibility on the parent `.wse-attr-block.wse-show-image-price` class added by Widget 1 when the toggle is on. Sale-aware (renders `<del>regular</del> <ins>sale</ins>` markup when on sale). Hidden automatically when image-label position is `hover` or `hidden` (orphaned price would look misplaced without label context).

**Tier 0 — Editable text overrides (9 new controls)**

Per senior-developer review, this batch makes the most-visible hardcoded user-facing strings editable per Widget instance. Defaults match the previous values so existing instances render identically.

Widget 1 → Content tab → Swatches → "Text Overrides" sub-heading:
1. **`clear_text`** — Reset link text (default `Clear`)
2. **`choose_option_placeholder`** — WC dropdown placeholder (default `Choose an option`)
3. **`selected_value_prefix`** — Optional prefix before the selected option name. e.g. `Selected: ` → `Selected: Blue`. Empty by default.
4. **`oos_label_suffix`** — Out-of-stock screen-reader suffix (default `(unavailable)`)

Widget 2 → Content tab → Quantity → "Accessibility & Title Text" sub-heading:
5. **`qty_input_aria_label`** — Quantity field aria-label (default `Quantity`)
6. **`qty_decrease_aria_label`** — `[-]` button aria-label (default `Decrease quantity`)
7. **`qty_increase_aria_label`** — `[+]` button aria-label (default `Increase quantity`)
8. **`qty_decrease_title`** — `[-]` button hover-title (empty by default)
9. **`qty_increase_title`** — `[+]` button hover-title (empty by default)

**Architecture note for Tier 0**

Widget 2 controls flow naturally — `$settings` is in scope inside `templates/quantity-stepper.php` via `include`. Widget 1 templates (`wrapper.php`, `label.php`) are called from `WSE_Swatch_Renderer` (hooked on a WC filter, decoupled from Widget 1's `render()`). v1.2.3 wires Widget 1 text overrides via temporary filters added at the start of `render()` and removed at the end. Templates use `apply_filters()` with the hardcoded value as fallback so non-Widget-1 callers (legacy / direct render paths) still work without breaking.

**Files changed**

* `templates/quantity-stepper.php` — Issue 1a max=-1 guard, Tier 0 aria/title attrs
* `assets/js/add-to-cart.js` (+ .min) — Issue 1a defensive `readBounds()`
* `widgets/class-widget-price.php` — Issue 6 `show_default` fallbacks
* `includes/class-assets.php` — Issue 5 `!important` on F5 inline CSS
* `includes/class-settings.php` — Issue 3 sale-dot toggle
* `includes/class-activator.php` — Issue 3 default
* `includes/class-swatch-renderer.php` — Issue 3 toggle respected, Issue 7 price helper
* `widgets/class-widget-add-to-cart.php` — Issues 2, 3, 4 + Tier 0 W2 controls
* `assets/css/add-to-cart.css` (+ .min) — Issues 3, 4 stacked layout rules
* `widgets/class-widget-swatches.php` — Issue 7 visibility class + Tier 0 W1 controls + filter wiring
* `templates/swatches/image.php` — Issue 7 price `<span>` render
* `templates/swatches/wrapper.php` — Tier 0 Clear-text filter
* `templates/swatches/label.php` — Tier 0 OOS-suffix filter
* `templates/add-to-cart/variable.php` — Tier 0 Choose-an-option filter
* `assets/css/swatches.css` (+ .min) — Issue 7 price-display CSS

**Migration**

Drop-in replacement for v1.2.2. No DB schema changes. Hard-refresh + clear caches after install (Elementor → Tools → Regenerate CSS, Hostinger Cache Manager → Purge All).

= 1.2.2 =
**Patch: 4 issues fixed from live ZYMARG site testing — icon resilience + stepper dead-code removal + image-label CSS specificity fix.**

Senior-developer code review combined with browser DevTools diagnostic data from the live site identified four related issues with v1.2.1 on the production environment. All four trace to two underlying root causes: (1) Elementor's icon-data manager missing the "minus"/"plus" keys on the user's installed version, and (2) my F4 image-label CSS not winning the specificity battle against Elementor's per-widget Swatch Size control.

**Issue 1: Minus icon not showing in quantity stepper**
**Issue 2: Elementor icon library not loading in the icon picker**
**Issue 3: Quantity stepper not working on simple products (worked on variable)**

Three layers of defence against the Elementor `font-icon-svg/e-icons.php` "Undefined array key 'minus'" warning that polluted the stepper button output:

1. **Default control values changed from `eicon-minus`/`eicon-plus` to empty.** New widget instances render the inline-SVG fallback by default and never reach Icons_Manager. Existing widget instances that explicitly chose an Elementor icon via the picker still get that rendering through Icons_Manager.
2. **`@`-suppression on the `Icons_Manager::render_icon()` call** so warnings don't escape onto the page even when `display_errors = on`.
3. **Detect Elementor / PHP warning patterns** (`Warning:`, `Notice:`, `Undefined array key`, `Trying to access array offset`) in the captured output buffer and fall through to the inline-SVG fallback. Belt-and-braces — covers any future Elementor warning format change.

Issue 3 (simple-product stepper) was a downstream symptom of the warning text breaking the button DOM combined with a long-standing dead-code path in `add-to-cart.js`: a function `initQuantityStepper()` that bound click handlers to `.wse-qty-plus / .wse-qty-minus` selectors which no theme actually emits. Our stepper template emits `.wse-qty-btn--minus / --plus`. The dead handlers never fired — the real stepper logic in `initQtyStepper()` (different name) handled the work but was sometimes masked by the icon warning issue. v1.2.2 deletes the dead `initQuantityStepper()` entirely. The remaining single source-of-truth handler in `initQtyStepper()` works identically on simple and variable products.

**Issue 4: Image swatch variation label not visible below the image**

Browser DevTools showed the label HTML was rendering correctly into the DOM but the parent `<li>` was constrained to 32×32 px. The CSS specificity audit identified Widget 1's per-instance Style → Swatch Size control as the culprit:

```
.elementor-24 .elementor-element.elementor-element-6920a34 
  .wse-swatch.wse-swatch-color, 
.elementor-24 .elementor-element.elementor-element-6920a34 
  .wse-swatch.wse-swatch-image 
{ width: 32px; height: 32px; }
```

Specificity (0, 5, 0) — five classes via the Elementor `{{WRAPPER}}` placeholder. My v1.2.1 F4 rule was:

```
body.wse-stylesheet-enabled 
  .wse-attr-block[data-type="image"] .wse-swatch-image 
{ width: auto; height: auto; flex-direction: column; ... }
```

Specificity (0, 4, 1) — four classes plus the body element. Elementor's rule beat mine by one class on width/height, so image swatches stayed pinned at 32×32 and the label rendered into a too-small parent. Other F4 properties (flex-direction, padding, gap, align-items) cascaded fine because Elementor's `swatch_size` doesn't touch them — hence the half-broken layout that the live screenshots showed.

Fix: add `!important` to the four structural F4 properties that need to outrank per-widget Elementor styles (`width`, `height`, `flex-direction`, `overflow`). Other properties continue to cascade normally — no `!important` escalation beyond what's strictly needed.

Bonus polish:
* Inline image-swatch label gets `min-height: 1.2em` so it reserves space even mid-variation-update.
* Removed `text-overflow: ellipsis` from labels (was clipping at `max-width: 80px` without a visible signal that text was being trimmed).
* Added `min-width: var(--wse-swatch-image-img-w, 56px)` so image swatch parent accommodates longer label text.
* Suppress the `swatches-tooltip` hover affordance when `image-label-pos` is `below` or `above` — with the inline label visible, a redundant hover tooltip just feels noisy. Tooltip stays enabled for color/label/button swatches and for image swatches with `hover`/`hidden` positions.

**Action items on YOUR side after install (these are environmental — v1.2.2 cannot do them for you)**

1. **Update Elementor and Elementor Pro to current stable.** The `Undefined array key "minus"` warning is a known issue on certain Elementor / Elementor Pro version combinations where their icon-data map drifted between releases.
2. **Clear all caches in this order:**
   * WP Admin → Elementor → Tools → **Regenerate CSS & Data** (click both buttons)
   * Hostinger Cache Manager → **Purge All**
   * LiteSpeed Cache (if active) → **Purge All**
   * Browser hard-refresh: **Ctrl+Shift+R / Cmd+Shift+R**
3. **Verify v1.2.2 is on disk** — Plugins page should show Version 1.2.2. If still 1.2.1 or earlier, re-upload the new zip via Plugins → Add New → Upload Plugin → "Replace current with uploaded".

**Files changed**

* `widgets/class-widget-add-to-cart.php` — icon control defaults emptied (Issues 1+2)
* `templates/quantity-stepper.php` — `@`-suppress + warning detection (Issues 1+2+3)
* `assets/js/add-to-cart.js` — `initQuantityStepper()` dead code removed (Issue 3)
* `assets/js/add-to-cart.min.js` — regenerated
* `assets/css/swatches.css` — F4 `!important` + label visibility + tooltip suppression (Issue 4)
* `assets/css/swatches.min.css` — regenerated
* `woo-swatches-elementor.php` — Version 1.2.2 + `WSE_VERSION` constant
* `readme.txt` — Stable tag + Changelog + Upgrade Notice + new Troubleshooting section

**Migration**

Drop-in replacement for v1.2.1. No DB schema changes. Existing widget instances retain their settings. The icon-default change only affects widget instances that left the icon at the v1.2.0/1.2.1 default — those will switch to the inline-SVG fallback automatically (which is the recommended default going forward). Widget instances that explicitly picked an Elementor icon retain that choice.

= 1.2.1 =
**Polish release: 3 bug fixes + 5 features + 6 polish enhancements + 5 ZYMARG Price widget improvements.**

This release addresses real-world issues surfaced from live ZYMARG site testing of v1.2.0 plus a broader UX polish pass across all three widgets.

**Bug fixes (B1–B3)**

* B1 — Simple-product +/- stepper buttons now respond reliably. Replaced the v1.2.0 IIFE init reassignment hack (`var _wseInit = init; init = function() { ... }`) with a clean composite `initAll()` so stepper handlers bind on simple-product pages where the v1.2.0 timing differed from variable products.
* B2 — Decrease icon never disappears. Captures `Icons_Manager::render_icon()` output via output buffer; falls through to the hand-drawn inline SVG when empty. CSS forces explicit width/height/font-size + `currentColor` fill on every icon child.
* B3 — Resilience against Elementor icon picker library failing to load. Hand-drawn SVG fallback always renders even if the picker stays broken; recommended icon list expanded with safe defaults.

**Features (F1–F5)**

* F1 — New "Stepper width mode" control on Widget 2: Auto / Custom / Full Width. Full mode makes the stepper fill its parent column with the qty input growing via flex:1 between fixed-size buttons.
* F2 — Sticky Add to Cart toggles moved from per-widget to global admin: WC → Settings → WooSwatches → Sticky Add to Cart. Mobile defaults to ON. Per-widget controls show a pointer to the new admin location.
* F3 — Per-type attribute name visibility above swatches. By default, the "color" / "size" label row is shown only for Color swatch types and hidden for Image / Label / Button / Dropdown (per ZYMARG product spec). New "Show Label for non-color types" override toggle on Widget 1.
* F4 — Variation name labels under image swatches with 4 position options: Below (default per ZYMARG product spec), Above, On hover (tooltip), Hidden. Labels are always rendered; CSS drives the layout based on the selected position.
* F5 — Responsive per-type swatch widths (global + per-widget override). New "Swatch Sizes" admin section with 12 width inputs: Color / Image / Label / Button × Desktop / Tablet / Mobile. Inline `<style>` block injected on `wp_head` priority 100; per-widget Style controls keep their override capability via `{{WRAPPER}}` selector specificity.

**Polish (S1–S6)**

* S1 — Selected-state image swatch labels become bold and adopt the ZYMARG Primary purple (#9500a5) for clearer selection cues.
* S2 — On-sale swatches show a small ZYMARG Primary purple dot in the top-right corner. `class-swatch-renderer.php` gains `is_term_on_sale()` helper.
* S3 — Smooth scroll-to-form on mobile (≤ 768 px) after swatch click. Customers see the price update / variation match / Add to Cart button without losing context.
* S4 — Local Attribute Swatches admin metabox preview tiles now use the same rounded corners and hover lift as the frontend, giving store owners a faithful preview of their saved values.
* S5 — Stepper button hover state lifts to ZYMARG Primary Fixed (#ffd6fb) with Primary purple border and On Primary Fixed (#36003d) text. Active uses Inverse Primary (#fea9ff).
* S6 — Auto-flush of swatch transients on global settings save (`woocommerce_update_options_woo_swatches`). Eliminates the "I changed a setting and the page didn't update for 24 hours" support ticket.

**ZYMARG Price widget enhancements (P1, P4, P5, P9, P10)**

* P1 — "You save X (Y%)" indicator inline next to the price when on sale. Three formats: `Save {amount} ({percent}%)`, amount-only, percent-only. Toggleable.
* P4 — Loading skeleton (shimmer animation) on the price block during variation lookup. Toggleable. Uses ZYMARG Surface and Surface Container as gradient stops.
* P5 — Sale badge variants. Position: inline-after (default), inline-before, floating top-right corner with rotation, or block-above. Content: custom text, percent (-30%), amount (Save 100৳), or percent-text (30% off).
* P9 — Free shipping threshold hint. Renders below the price with a checkmark glyph: "Free shipping over 2,000৳". Threshold and template text both customisable.
* P10 — Smart adaptive heading above the price with 4 states:
  * **On sale** — default text "Limited Time Offer", ZYMARG Primary purple
  * **Sale ending ≤ 24h** — auto-overrides the sale heading with `Ends in {hours} hours!` (auto-computed from the variation's / product's `date_on_sale_to`), font-weight: 700 for urgency
  * **Regular price** — empty by default (most stores don't display a heading on regular price); editable
  * **Out of stock** — default text "Currently Unavailable", ZYMARG Outline grey
  * Each state has its own toggle + text input. Server-side state computation; client-side updates would require an additional render path that is not included in v1.2.1 (variation switching uses the cached regular-state heading; reload to recompute the state for the current variation).

**Migration**

Drop-in replacement for v1.2.0. No DB schema changes. The activator detects v1.1.x → v1.2.1 upgrades and pins the back-compat default for the v1.2.0 inline-price toggle. Sticky toggles on existing v1.2.0 widget instances are silently superseded by the new global option (defaults: desktop=no, tablet=no, mobile=yes — same as the v1.2.0 widget defaults). Hard-refresh after install.

= 1.2.0 =
**Minor: Widget 3 (ZYMARG Price) introduced + quantity stepper redesign in Widget 2.**

This release splits price display into its own dedicated, fully-stylable Elementor widget and ships a touch-friendly +/- quantity stepper inside Widget 2 (Add to Cart).

**New: Widget 3 — ZYMARG Price**

* Standalone Elementor widget (`zymarg-price`) under the WooSwatches category. Place anywhere on a product page — main column, sticky cart, gallery sidebar — multiple instances on the same page all sync to the same canonical form.
* **Simple products**: renders the price always; sale-aware. If on sale, the sale price is the prominent number and the regular price renders next to it as a strikethrough subscript.
* **Variable products, no variation selected**: renders ONLY the lowest active price across all variations (per ZYMARG product spec). When any variation on the product is on sale, the lowest regular price across all variations is rendered next to the lowest active price as a strikethrough subscript, and the Sale badge appears (option (ii) per spec).
* **Variable products, variation selected via Widget 1**: when the customer picks a swatch, `price.js` listens to the canonical form's `found_variation` event fired by `wc-add-to-cart-variation.js` and re-renders this widget with that specific variation's price + sale formatting. `reset_data` restores the lowest-baseline.
* **Default display style** (Content tab → Display): Lowest price (default), Lowest with "From" prefix, or Price range.
* **Regular price position** (Content tab → Display): Inline subscript (default), Inline beside, Below, or Hide.
* **Sale badge**: optional badge with configurable text. Shows when ANY variation on the product is on sale, even on the lowest-baseline view.
* **Style controls**: typography + colour for current price (regular and on-sale separately), typography + colour + opacity for regular ("was") price, full sale-badge styling (typography, text colour, background, padding, border radius), responsive layout gap and alignment. All values flow through CSS custom properties so themes and child themes can override at runtime without specificity wars.
* Currency formatting (decimal separator, thousand separator, decimals, currency symbol, currency position) is read live from WooCommerce's option store on both server-side and client-side renders, so currency-switcher plugins (WPML, Aelia, etc.) that hook the underlying WC functions are respected automatically.
* LFI guard on every template include via `realpath()` allow-list — same protection as the existing `WSE_Swatch_Renderer` template system.

**Changed: Widget 2 — Quantity stepper redesign**

* Replaced WooCommerce's bare quantity input with a `[-] [qty] [+]` stepper for touch-friendly increment/decrement. Manual numeric entry is still available; the middle `<input>` keeps its WC-expected name, classes, and value handling so cart logic is unchanged.
* New Content tab controls (under Quantity section): Show +/- Stepper Buttons toggle, Decrease icon (Elementor icon picker), Increase icon (Elementor icon picker).
* New Style tab section "Quantity Stepper Buttons" with full control: total stepper width, button size, icon size, gap, normal/hover/disabled state colours, border, border radius. All responsive.
* Click handlers respect per-variation `min_qty` / `max_qty` updates from WC's variation matcher — the stepper buttons disable when the variation imposes new bounds.
* `grouped` product templates left untouched (the per-row qty inputs in a grouped product table are a separate UX problem; parked for a future release).

**Changed: Widget 2 — "Show Inline Price" toggle**

* New control under Content → Button section. Default for fresh installs: **OFF** (Widget 3 owns price). Default for upgrades from any prior v1.1.x release: **ON** (back-compat). Existing configured Widget 2 instances keep their previous behaviour.
* When OFF, Widget 2 still renders the `.woocommerce-variation-price` div (so WC's variation engine writes into it without errors) — it's just CSS-hidden via `body.wse-stylesheet-enabled .wse-no-inline-price .woocommerce-variation-price { display: none; }`. Availability and description divs stay visible.

**Migration**

* Drop-in replacement for v1.1.6. No DB schema changes.
* On activation, the activator detects an upgrade from any version `< 1.2.0` and pins `wse_widget2_inline_price_default` to `'yes'`, so existing Widget 2 instances continue to show their inline price exactly as in v1.1.x. New Widget 2 instances added after the upgrade will default to `'no'` so Widget 3 owns price display going forward.
* For ZYMARG (or any user wanting Widget 3 to own price on existing pages): edit each Widget 2 in Elementor and toggle Show Inline Price → Off. Manual one-time per page.
* No template overrides require updating.

**Files changed / added**

* New: `widgets/class-widget-price.php`, `templates/price/{simple.php, variable.php, parts/price-current.php, parts/price-was.php}`, `templates/quantity-stepper.php`, `assets/css/price.css` (+ `.min`), `assets/js/price.js` (+ `.min`).
* Modified: `includes/class-plugin.php` (Widget 3 registration + price params in WSEParams), `includes/class-assets.php` (handle map + register), `includes/class-activator.php` (default + migration), `widgets/class-widget-add-to-cart.php` (stepper + inline-price controls), `templates/add-to-cart/{simple.php, variable.php}` (stepper integration + form class), `assets/css/add-to-cart.css` (+ `.min`) (stepper + inline-price hide), `assets/js/add-to-cart.js` (+ `.min`) (stepper handlers).

= 1.1.6 =
**Patch: Six rendering bugs found via live ZYMARG site testing — admin asset paths, missing CSS, missing template, duplicate dropdowns.**

This release fixes a cluster of bugs that surfaced once the plugin was used on a real Dokan Pro / Astra / Hostinger stack against products with local (non-taxonomy) custom attributes. Five of them have a single shared root cause — files referenced by the wrong path on disk — and the remaining two are missing CSS rules and a missing template-name mapping.

**Bug #1 — Color swatches rendered as a grey square with a permanent checkmark instead of the actual colour**
Two combined causes:
1. The admin metabox couldn't save colours because the admin JS and CSS for the Local Attribute Swatches metabox were enqueued from `assets/js/admin-local-attributes.min.js` and `assets/css/admin.css` — paths that don't exist on disk (admin assets live in `admin/`, and only unminified `.js` files are shipped). In production (`SCRIPT_DEBUG=false`, default) this 404'd, the wp-color-picker UI never initialised, the per-option colour inputs stayed empty, and `_wse_local_swatches` was saved without colour values. The renderer then fell back to its `#e0e0e0` default, producing a grey tile.
2. The checkmark overlay (#2 below) rendered always-on, masking the missing colour with a tick.
After this fix the picker initialises, colours save correctly, and the swatch renders the actual hex value.

**Bug #2 — Image and color swatches showed a permanent checkmark on every tile, not just the selected one**
The `<span class="wse-checkmark">` element is rendered on every color and image swatch, with the intent that CSS gates its visibility on `.wse-swatch.selected`. That CSS rule was missing entirely from `assets/css/swatches.css` (and its minified twin), so the checkmark span rendered always-on regardless of selection state. v1.1.6 adds the missing rule plus an explicit `display:none` on image swatches per ZYMARG product preference (the image itself is sufficient indication; the overlaid checkmark obscures it).

**Bug #3 — Selected label/button swatch had a hard-coded blue (`#0066cc`) background pill**
`assets/css/swatches.css` painted `.wse-swatch-label.selected` and `.wse-swatch-button.selected` with `background: var(--wse-swatch-active-color)` and `color: #fff`, producing the "blue pill" effect on selection. Per ZYMARG product preference, v1.1.6 overrides the selected state to carry no colour change at all — selection is now indicated by `font-weight: 700` and a neutral `currentColor` border only.

**Bug #4 — Button-type swatches rendered an empty `<ul>` (nothing visible)**
The renderer's loop calls `include_template($type . '.php', ...)`, so for `type='button'` it tried to load `templates/swatches/button.php` — a file that has never existed in the plugin. `locate_template()` returned empty, the LFI guard silently failed the include, and button-typed attributes produced no swatch markup on every product page. Fixed by routing `button` through `label.php`, which already supports the button variant via the `.wse-swatch-label--button` modifier class (driven by `$swatch['type']`).

**Bug #5 — Dropdown (`select`) attributes rendered TWO dropdowns, both required to select before Add to Cart enabled**
When a local attribute was set to type "Dropdown", the renderer's early-exit path for non-swatch types (`return '<div class="wse-native-attr variations">' . $html . '</div>'`) ignored the `wse_renderer_emit_select` filter that Widget 1 sets to `false` during its render. So Widget 1 emitted a passthrough copy of the native `<select>` while Widget 2 (canonical form) emitted its own — two dropdowns with the same `name`, two independent variation matchers. v1.1.6 respects the filter on the early-exit path: when emit_select is false (Widget 1's render), the renderer returns empty for non-swatch types, leaving the canonical form as the sole owner of the dropdown.

**Bug #6 — Local Attribute Swatches metabox: Upload button did nothing; Color and Image fields shown together**
Same root cause as Bug #1: `class-local-attributes.php` enqueued admin CSS from `assets/css/admin.css` (404 — file is at `admin/admin.css`) and admin JS from `assets/js/admin-local-attributes.min.js` (404 — only `admin/admin-local-attributes.js` exists). With the JS not loading, the click handler `$(document).on('click', '.wse-local-upload-btn', ...)` never registered, so the Upload button was inert. With the CSS not loading, the `.wse-hidden { display:none !important }` rule never applied, so the type-switcher's visibility gating failed and both Color and Image field groups rendered simultaneously regardless of saved type. `class-term-meta.php` had the same path bugs and got the same fix for symmetry — the global-attribute term editor's Upload button was silently broken in production for the same reason.

**Files changed**
* `includes/class-swatch-renderer.php` — Bug #4 (button → label template normalisation), Bug #5 (emit_select check on early-exit path).
* `includes/class-local-attributes.php` — Bug #1 + #6 (admin CSS+JS path corrections).
* `includes/class-term-meta.php` — Bug #6 mirror (admin CSS+JS path corrections for global attribute term editor).
* `assets/css/swatches.css` and `assets/css/swatches.min.css` — Bug #2 (`.wse-checkmark` visibility gating + image-swatch hide), Bug #3 (override selected label/button styling).

**Migration**
* Drop-in replacement for v1.1.5. No DB migration required.
* After installing, hard-refresh (Ctrl+Shift+R / Cmd+Shift+R) the live product page and any product edit screen to flush cached CSS and JS.
* If you had previously configured local-attribute colour or image swatches and they appeared not to save, re-open the product, set the swatch values again with the now-working picker UI, and click Update. Existing saved data is unaffected.
* No changelog item from v1.1.5 is being reverted.

= 1.1.5 =
**Patch: View Cart link hiding now uses CSS body class — wins all races.**

v1.1.4 stripped WooCommerce's injected `<a class="added_to_cart wc-forward">` link via a JS `added_to_cart` listener, but the strip ran reactively *after* WC's injection, which lost the race on some sites (browser cache, timing variations). v1.1.5 switches to a CSS-first approach that applies the rule **before** WC's `<script>` ever runs — there is no timing window where the link is visible.

**Implementation:**
* `class-plugin.php` adds a `body_class` filter that appends `wse-hide-view-cart` to `<body>` whenever the global `wse_show_view_cart_link` option is `no`.
* `assets/js/add-to-cart.js` adds the same body class via JS at DOMReady whenever any Add to Cart widget on the page has its per-widget `data-show-view-cart="no"` override set.
* `assets/css/add-to-cart.css` matches `body.wse-hide-view-cart a.added_to_cart.wc-forward` (and the various other places WC renders the View cart anchor) with `display:none !important; visibility:hidden !important; pointer-events:none !important;` — uncatchable by any timing race.
* The v1.1.4 JS DOM strip stays as defense-in-depth so removed elements don't accumulate in memory.

After installing, hard-refresh the live product page (Ctrl+Shift+R / Cmd+Shift+R) to flush the cached CSS. The View cart link will be hidden in every location: WC's top notice, inline beside the button, mini-cart fragment, and the toast.

= 1.1.4 =
**Patch: View Cart link is now correctly hidden everywhere when the toggle is off.**

When the "Show View Cart Link" setting (global or per-widget) is set to OFF, an inline `<a class="added_to_cart wc-forward">View cart</a>` link still appeared next to the Add to Cart button after a successful AJAX add. Root cause: WooCommerce's own frontend `wc-add-to-cart.js` binds an `added_to_cart` event listener that injects this link client-side, **after** my server-side `wc_add_to_cart_message_html` filter has already run. My v1.1.3 server-side filter could not reach this DOM injection.

**Fix:**
* New `added_to_cart.wse-strip-view-cart` listener in `assets/js/add-to-cart.js` removes the WC-injected link on the next tick (using `setTimeout(0)` so WC's listener runs first, then we strip).
* `applyPerWidgetViewCartHiding()` was renamed to `applyViewCartHiding()` and now triggers when EITHER the global `wse_show_view_cart_link` setting is off OR any widget has `data-show-view-cart="no"` (previously only the per-widget override triggered cleanup).
* Selector list expanded to also catch `a.added_to_cart.wc-forward` outside of widget wrappers (covers themes that render the link in non-standard locations).
* The cleanup runs at three trigger points: DOMReady, after fragment apply, and after `added_to_cart` (catches WC's client-side injection). All three paths are idempotent.

After installing, hard-refresh (Ctrl+Shift+R / Cmd+Shift+R) the live product page to flush cached JS. The View cart link will be hidden in all three locations: WC's top notice, inline beside the button, and the bottom-right toast.

= 1.1.3 =
**Patch: orphan Presenter Mode actually works, per-widget View Cart toggle, cleaner button text.**

**Fix: Orphan Presenter Mode now functional**
* When Presenter Mode is enabled on a widget that has no canonical sibling on the page, the auto-synthesised hidden form now binds WooCommerce's full variation engine (`$.fn.wc_variation_form()`) so swatch clicks correctly populate `variation_id` and the presenter button submits correctly.
* The synthetic form's hidden `<select>` elements now contain one `<option value="...">` for every distinct value referenced by the product's variations — previously a single empty option meant `jQuery.val(x)` had no matching option to mark selected, silently breaking cross-widget sync.

**New: Per-widget "Show View Cart Link" toggle**
* New Behavior control on the Add to Cart widget: **Inherit from settings / Yes / No**. Default is Inherit (uses the global WC Settings → WooSwatches → Display value).
* When set to **No**, the widget strips the `<a class="wc-forward">View cart</a>` anchor everywhere it appears: inline next to the Add to Cart button (WC's `wc_add_to_cart_message_html`), the bottom-right toast notification, the WooCommerce mini-cart fragment, and any pre-existing notices already rendered into the DOM (handled at DOMReady so refreshing the page doesn't show a stale link).
* Lets merchants disable the "View cart" link per widget instance without affecting the global setting.

**UX: Cleaner button success text**
* The localized i18n string `Added to cart!` is now `Added to cart` (no exclamation mark).
* JS fallback in the success state changed from `Added ✓` to `Added to cart`.
* Defensive CSS rule (`assets/css/add-to-cart.css`) suppresses any `::before` / `::after` pseudo-element a theme or fragment may inject onto the `.wse-atc-button` (and its loading / added / error variants) — eliminates the stray "✓" or icon some themes (Astra Pro, Flatsome) add next to the button text.
* The toast's own ✓ icon is unaffected — that's a real `<span>`, not a pseudo-element.

= 1.1.2 =
**Critical hotfix: Dokan Pro Order Min Max TypeError on variable products.**

Fixes a regression introduced in v1.1.1 where the Add to Cart submit handler returned without calling `event.preventDefault()` when the variation-required guard tripped. On Dokan Pro stacks with the Order Min Max module enabled, this caused a fatal PHP TypeError:

```
WeDevs\DokanPro\Modules\OrderMinMax\Frontend\CartRestriction::validate_add_to_cart():
Argument #4 ($variation_id) must be of type int, string given
```

Root cause: Dokan Pro's `validate_add_to_cart()` callback declares a strict-typed `int $variation_id` parameter. When v1.1.1's submit handler bailed early without preventing the form's default action, the form POSTed to the server, WooCommerce's `WC_Form_Handler::add_to_cart_handler_variable()` processed it, passed an empty string to the validation filter (no variation selected), and Dokan threw a fatal.

**Fix (JS):**
* `assets/js/add-to-cart.js` now calls `event.preventDefault()` unconditionally at the top of the canonical-form submit handler, before any guard check. The form can no longer submit via standard POST under any circumstance — worst case is "click does nothing" which matches expected UX when no variation is selected.

**Fix (PHP, defensive):**
* `includes/class-plugin.php` adds a new `coerce_variation_id_post()` callback hooked to `init` priority 1. It casts `$_POST['variation_id']` to `int` for non-AJAX, non-REST requests, so any third-party validator with strict typing receives an `int` even if a future regression or third-party JS lets the form POST through.

Both fixes ship together so the protection is belt-and-suspenders: the JS prevents POST submissions, and the PHP coercion catches any that still slip through (cached JS, theme overrides, etc.).

= 1.1.1 =
**Hotfix release: Dokan/multi-vendor compatibility, FOUC fix, sticky-without-presenter, "Added to cart" toast.**

This release addresses live-site issues reported on a Dokan Pro / Astra / Pantheon stack and refines three v1.1.0 design choices that proved problematic in real-world usage.

**New: "Added to cart" toast notification**
* Small bottom-right toast confirms successful add-to-cart on every code path (canonical Add to Cart widget, archive AJAX, presenter sticky bar). Auto-dismisses in ~3.5 seconds. Mobile-aware (full-width on ≤ 767px, lifts above the sticky presenter when one is active).
* Toggleable via WooCommerce → Settings → WooSwatches → Display → "Show 'Added to Cart' Toast" (default ON).
* Respects the existing "View Cart" link toggle: shows the link when enabled, omits it when not.

**New: Multi-vendor compatibility mode (Dokan / WCFM / WC Vendors / MultiVendorX)**
* New setting: WooCommerce → Settings → WooSwatches → Display → "Multi-vendor Compatibility Mode" (default Auto).
  * Auto: enabled when one of these multi-vendor plugins is active server-side.
  * On: always run cart-state verification on AJAX errors.
  * Off: never run verification (v1.1.0 behaviour).
* When active, the AJAX add-to-cart re-checks the cart hash whenever WooCommerce returns `error: true`. If the cart actually changed, the request is treated as success and the toast fires. Fixes the long-standing "Something went wrong (but the product is in the cart)" issue on Dokan stacks.
* Re-introduces the v1.0.5 cart-hash heuristic, but smarter: only kicks in when WC reports an error AND a multi-vendor plugin is detected, so non-vendor stores still see real errors honestly.

**Fix: Sticky add-to-cart works on a single widget**
* v1.1.0 only exposed the Sticky toggles when Presenter Mode was on, which required two widgets and broke single-widget setups. v1.1.1 makes the Sticky on Desktop / Tablet / Mobile toggles always available on the Add to Cart widget.
* The sticky CSS classes now apply to the canonical Add to Cart wrapper too, not just `.wse-presenter`. A single Add to Cart widget with Sticky on Mobile = On now correctly pins the entire form to the viewport bottom on mobile.
* Presenter Mode moves under an "Advanced" heading with clearer guidance — it's only meant for secondary widgets on pages that already have a primary Add to Cart elsewhere.

**Fix: Orphan presenter auto-synthesis**
* Anyone who enables Presenter Mode on a page without a canonical Add to Cart widget would previously get a broken button (form="wse-form-X" pointing at a non-existent form, AJAX request with no payload, "Something went wrong"). v1.1.1 detects this at DOMReady and synthesises a hidden canonical form with all required hidden inputs and selects so the button still works.
* Editor warning added: when Presenter Mode is toggled on, the widget shows an inline notice explaining that it requires a primary Add to Cart elsewhere on the page (or the auto-synthesis will kick in as a fallback).

**Fix: No more flash of duplicate swatches on page load**
* When both Widget 1 (Swatches) and Widget 2 canonical (Add to Cart) were on the same page, both rendered visible swatches and the JS dedup at DOMReady caused a brief flash of two swatch sets. v1.1.1 hides the canonical form's swatches by default via CSS, and JS only reveals them when no Widget 1 is on the page (Widget 2-alone scenario). Result: zero flash on first paint.

**Fix: Variation-required guard**
* The submit handler now bails when a variations form has no resolved `variation_id`, letting WooCommerce's native validator surface its "Please select options" notice instead of firing an empty AJAX request that produces "Something went wrong".
* CSS now enforces the disabled visual state of the Add to Cart button when WC's variation engine has marked it disabled or `wc-variation-selection-needed`, so themes that override button styling can't accidentally make a non-functional button look enabled.

**Fix: Robust AJAX payload assembly**
* `submitAtcForm()` now builds the request payload from explicit sources (form `data-product_id`, `input.variation_id`, `input.qty`, `select[name^=attribute_]`, HTML5 `form="..."`-linked selects) rather than relying on `$form.serializeArray()` alone. Survives nested-form HTML, orphan-presenter setups, and third-party plugins that mangle form structure.

**Migration**
* Drop-in replacement for v1.1.0. No DB migration required.
* If you toggled Presenter Mode in v1.1.0 expecting it to give you a sticky add-to-cart, switch back: turn Presenter Mode OFF and turn the Sticky toggles ON directly on your single Add to Cart widget.
* Multi-vendor sites: the default Auto setting will detect Dokan/WCFM/WC Vendors and turn on cart verification automatically. Override via WC → Settings → WooSwatches → Display if you want explicit control.

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

== Roadmap ==

Forward-looking items not yet scheduled to a specific release:

* Multiple images per variation — a first-class admin UI on top of the existing `wse_variation_image_ids` filter so vendors can attach several images per variation directly.
* Pinch-zoom on the mobile lightbox — a `transform: scale()` model with bounded panning, layered over the OS-native pinch.
* Variation-aware Quick View modal that reuses the gallery widget.

== Upgrade Notice ==

= 1.6.0 =
IMPORTANT: Sticky Add to Cart settings have moved from WooCommerce → Settings → WooSwatches INTO the Add to Cart widget (Content → Behavior). The global panel is removed. After updating, re-enable sticky per-widget in Elementor (defaults to sticky ON for mobile only). Adds deep customization controls (container background incl. gradient, border, radius, padding, margin — all responsive) across all four widgets, plus a sticky-bar minimum-height control. Removes the v1.5.0 per-swatch savings pill. No DB migration. Hard-refresh + Regenerate CSS after install.


