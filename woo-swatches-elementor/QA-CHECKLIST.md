# ZYMARG Variation Swatches for Elementor — QA Regression Checklist

A repeatable manual test matrix run before each release. Its purpose is to
catch the class of bug where a fix for one product type / swatch type /
breakpoint silently breaks another (e.g. "fixed for variable, broke for
simple"). Run the **Smoke** column every release; run the **Full** column
for any release that touches the listed area.

**Environment baseline:** WordPress 6.4+, WooCommerce 8.0+, Elementor 3.20+,
Astra theme, Dokan Pro (multi-vendor). After installing a build: deactivate →
upload zip → activate → **Elementor → Tools → Regenerate CSS & Data** →
hard-refresh (Ctrl/Cmd+Shift+R).

**Test products to keep on the dev site:**
- `SIMPLE` — a simple product (e.g. "Hoodie with Logo").
- `VARIABLE-1ATTR` — variable product, single attribute (Color).
- `VARIABLE-NATTR` — variable product, multiple attributes (Color + Size).
- `VARIABLE-SALE` — variable product on sale, where different variations have
  different discount %.
- `GROUPED` — a grouped product.
- `EXTERNAL` — an external/affiliate product.
- At least one product with a **Product Video URL** set (one each: YouTube,
  Vimeo, direct .mp4).

---

## 1. Swatches — selection & multi-attribute sync (v1.4.8)

| # | Test | Steps | Expected | Smoke | Full |
|---|------|-------|----------|:-----:|:----:|
| 1.1 | Single-attribute select | On `VARIABLE-1ATTR`, click a Color swatch | Swatch gets selected border; selected-value text shows the term name; price/gallery/ATC update | ✅ | ✅ |
| 1.2 | Multi-attribute first pick keeps state | On `VARIABLE-NATTR`, click Color = Red | "Red" stays shown next to Color heading **and** the Red swatch keeps its selected border (does not clear) | ✅ | ✅ |
| 1.3 | Multi-attribute second pick | Then click Size = Large | "Large" shows next to Size; "Red" still shown; both borders persist; ATC enables | ✅ | ✅ |
| 1.4 | Clear one attribute | Click the Color "Clear" link | Only Color clears; Size selection + label remain intact | | ✅ |
| 1.5 | Toggle-off by re-click | Click the already-selected swatch | That attribute deselects; others unaffected | | ✅ |
| 1.6 | Cross-attribute availability | Pick a Color that disables some Sizes | Unavailable sizes show the `filtered-out` greyed style | | ✅ |
| 1.7 | Add to cart correctness | Complete a full selection, Add to cart | Correct variation lands in cart | ✅ | ✅ |
| 1.8 | Keyboard nav | Arrow keys across a swatch group | Focus moves + auto-selects (WAI-ARIA radiogroup) | | ✅ |

## 2. Sticky Add-to-Cart bar — no empty gap (v1.4.9 → v1.4.11, A1)

Run on **mobile** breakpoint where sticky is enabled. The gap source is empty
elements (e.g. themed `<p class="warranty_info">`) injected into the cart form.

| # | Test | Expected | Smoke | Full |
|---|------|----------|:-----:|:----:|
| 2.1 | `SIMPLE` sticky | No empty white space between content and the qty/ATC row | ✅ | ✅ |
| 2.2 | `VARIABLE-NATTR` sticky | No empty gap above the qty/ATC row | ✅ | ✅ |
| 2.3 | `GROUPED` sticky | No empty gap | | ✅ |
| 2.4 | `EXTERNAL` sticky | No empty gap | | ✅ |
| 2.5 | Bar top edge | Bar sits flush (0 top padding) — no gap at the very top of the bar | | ✅ |
| 2.6 | Non-sticky unaffected | Scroll to top: the normal in-flow price/availability/description area still renders as before | | ✅ |
| 2.7 | Stepper icons intact | The +/- glyphs in the sticky stepper are visible (regression guard for the A1 `:empty` rules — must NOT hide stepper icon spans) | ✅ | ✅ |
| 2.8 | Sticky is per-widget (v1.6.0) | Sticky D/T/M + scroll-trigger are set in the Add to Cart widget → Content → Behavior (NOT in WC settings, which no longer has the panel). Toggling them per device works | ✅ | ✅ |
| 2.9 | Scroll-trigger | With scroll-trigger ON for a device, the bar stays hidden until the customer scrolls past the in-flow Add to Cart widget | | ✅ |
| 2.10 | Sticky min-height | Setting a sticky Minimum Height grows the bar on the chosen breakpoint | | ✅ |

## 3. Admin — attribute term UI for swatch types (v1.4.12, A4)

| # | Test | Steps | Expected | Smoke | Full |
|---|------|-------|----------|:-----:|:----:|
| 3.1 | Global attr, Color type | Products → Attributes: set `Color` Type = Color. Edit a product → add existing `Color` attribute | Value(s) shows the select2 multi-select + "Select all" / "Select none" / "Create value" | ✅ | ✅ |
| 3.2 | Create value inline | Click "Create value", add a new term | New term is created and pre-selected | | ✅ |
| 3.3 | Save persists | Save attributes + save product | Picked + new terms persist on reload | ✅ | ✅ |
| 3.4 | Image/Label/Button types | Repeat 3.1 with Type = Image, then Label, then Button | Same term UI appears for each | | ✅ |
| 3.5 | Built-in select type | An attribute left as Type = Select | Still works exactly as WC default | | ✅ |
| 3.6 | Local (per-product) attribute | Add a custom (non-global) product attribute with pipe-separated values | WC's plain textarea renders; **not** broken by our hook | | ✅ |

## 4. Price widget (v1.2.x) + transition animation (C1)

| # | Test | Expected | Smoke | Full |
|---|------|----------|:-----:|:----:|
| 4.1 | Variable price updates | Picking a variation updates the price in place | ✅ | ✅ |
| 4.2 | Heading / shipping hint preserved | Smart heading + free-shipping hint don't disappear after page load or on variation change | | ✅ |
| 4.3 | Regular price position | "subscript" and "beside" both render the struck regular price correctly | | ✅ |
| 4.4 | Animation: fade (default) | On variation change, the new price fades in | ✅ | ✅ |
| 4.5 | Animation: slide | Set animation = "Slide up + fade"; price slides up + fades | | ✅ |
| 4.6 | Animation: none | Set animation = "No animation"; price swaps instantly | | ✅ |
| 4.7 | No flash on load | On page load the price does NOT visibly animate (init reset is suppressed) | | ✅ |
| 4.8 | Reduced motion | OS "reduce motion" on → no animation regardless of setting | | ✅ |

## 5. Customization / style controls (v1.6.0)

All dimensional controls must offer Desktop / Tablet / Mobile in the Elementor control.

| # | Test | Expected | Smoke | Full |
|---|------|----------|:-----:|:----:|
| 5.1 | Swatches → Widget Container | Background (color + gradient) is responsive D/T/M; border, radius, padding, margin, max-width, alignment all apply | ✅ | ✅ |
| 5.2 | Swatches → Attribute Block | Background, border, radius, padding, margin apply to each `.wse-attr-block` | | ✅ |
| 5.3 | Swatches → Swatches Container | Gap-between-swatches, padding, margin, fieldset padding, Clear-link color/typography apply | ✅ | ✅ |
| 5.4 | Add to Cart → Widget Container | Gradient background, margin, max-width apply (plus existing controls) | | ✅ |
| 5.5 | Price → Widget Container | Background/gradient, border, radius, padding, margin, shadow, max-width apply to `.zymarg-price` | | ✅ |
| 5.6 | Gallery → Widget Container | Container background/border/radius/padding/margin + thumb-strip + main-area bg/padding apply | | ✅ |
| 5.7 | Savings pill gone | No "Show Savings Pill" control anywhere; no `-N%` badge renders on any swatch | ✅ | ✅ |

## 6. Gallery + product video (v1.3.x–v1.4.x, B2)

| # | Test | Expected | Smoke | Full |
|---|------|----------|:-----:|:----:|
| 6.1 | Variation image swap | Picking a swatch swaps the gallery to the variation's images | ✅ | ✅ |
| 6.2 | Reverse sync | Clicking a variation thumbnail selects the matching swatch | | ✅ |
| 6.3 | Video button shows | Product with a video URL + toggle on → "Watch video" pill appears bottom-left | ✅ | ✅ |
| 6.4 | YouTube opens | Click → overlay opens, YouTube embed autoplays | ✅ | ✅ |
| 6.5 | Vimeo opens | Same for a Vimeo URL | | ✅ |
| 6.6 | MP4 opens | Direct .mp4 → native `<video>` plays with controls | | ✅ |
| 6.7 | Close stops playback | Close button / backdrop click / ESC closes overlay AND stops audio (embed node removed) | ✅ | ✅ |
| 6.8 | Lazy load | The iframe/video is NOT in the DOM until the button is first clicked (check DevTools / Network) | | ✅ |
| 6.9 | No video configured | Product without a video URL → no button, no errors | | ✅ |
| 6.10 | Lightbox still works | Clicking the main image still opens the lightbox (video overlay didn't break it) | | ✅ |

## 7. Cross-cutting

| # | Test | Expected | Smoke | Full |
|---|------|----------|:-----:|:----:|
| 7.1 | Multiple widgets, one product | Swatches + Price + Gallery + Add-to-Cart on one page all stay in sync | ✅ | ✅ |
| 7.2 | Dokan vendor product | Add-to-cart works on a Dokan vendor product (no "Something went wrong") | | ✅ |
| 7.3 | Console clean | No JS errors in the browser console on a product page | ✅ | ✅ |
| 7.4 | Stylesheet toggle off | WC → Settings → WooSwatches → disable plugin stylesheet: theme CSS takes over, no fatal layout break | | ✅ |
| 7.5 | RTL | On an RTL locale, swatches + price render right-to-left | | ✅ |

## 8. Presets (v1.7.0)

Test in Elementor editor on a page with one of each WooSwatches widget. Open the widget's Style tab and find the "ZYMARG Presets" section at the top.

| # | Test | Expected | Smoke | Full |
|---|------|----------|:-----:|:----:|
| 8.1 | Section renders | "ZYMARG Presets" section appears at top of Style tab on Swatches / Add to Cart / Price / Gallery widgets | ✅ | ✅ |
| 8.2 | Save new preset | Configure widget; click "Save current settings as new preset…"; enter a name → preset is saved and selected in the dropdown | ✅ | ✅ |
| 8.3 | Apply preset | On a different widget instance, pick the saved preset → click "Apply selected preset" → settings transfer; preview re-renders | ✅ | ✅ |
| 8.4 | Update current | Change a few settings, click "Update current preset" → confirms, then re-apply elsewhere shows the new state | | ✅ |
| 8.5 | Delete preset | Click "Delete selected preset" → preset removed from dropdown across all widgets of that type | | ✅ |
| 8.6 | Auto-apply on insert | In a widget, set "Auto-apply on new widget" to a saved preset; drag a NEW widget of that type onto the canvas → its settings match the preset | ✅ | ✅ |
| 8.7 | Auto-apply OFF (default) | With "Auto-apply on new widget" set to "— None (off) —", new widget inserts use plugin defaults (no auto-changes) | ✅ | ✅ |
| 8.8 | Per widget type isolation | Saving a preset on Swatches doesn't affect Price/Gallery dropdowns | | ✅ |
| 8.9 | Capability gate | Log in as a non-admin user (e.g. shop_manager) → editor either hides the panel actions or shows a permission error in the status line | | ✅ |
| 8.10 | Sale badge gone | Edit Price widget Style tab → no "Sale Badge" section; Content tab → no sale-badge controls | ✅ | ✅ |
| 8.11 | Front-end no badge | On an on-sale product page, no `.zymarg-sale-badge` is rendered by the Price widget (Gallery widget's badge stays unaffected) | ✅ | ✅ |

---

## Release sign-off

- [ ] Smoke column passes on SIMPLE + VARIABLE-NATTR + VARIABLE-SALE
- [ ] `php -l` clean on all changed PHP files
- [ ] `node --check` clean on all changed JS (and `.min.js` rebuilt)
- [ ] Version bumped in `woo-swatches-elementor.php` header, `WSE_VERSION`, and `readme.txt` Stable tag
- [ ] Changelog + Upgrade Notice updated
- [ ] No PHP notices/warnings in `wp-content/debug.log` on a product page
