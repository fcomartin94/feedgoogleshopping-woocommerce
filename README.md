# Feed Export for Google Shopping — WooCommerce Plugin

A custom WordPress/WooCommerce plugin that generates a public XML product feed
compatible with Google Merchant Center — the same feature as the
`feedgoogleshopping-prestashop` module in this portfolio, rebuilt for a different
platform architecture (WordPress hooks/actions instead of PrestaShop's Module class
and front controllers).

## Why this project

Same motivation as the PrestaShop version: this demonstrates writing real plugin
code, not just configuring a store. Building the same feature twice, on two different
platforms, shows the underlying problem (Google Shopping feed generation) is
understood independently of any one platform's APIs.

## Stack

- WordPress 6.4 + WooCommerce (Docker)
- Plugin registered via WordPress's `add_action`/`rewrite rule` system (no external
  dependencies)

## Progress Log

### Step 1 — Local environment (Docker)

Reused the same Docker Compose pattern as `volt-circuit-prestashop-seo`: a `mysql`
service with a healthcheck, and a `wordpress:6.4-apache` service depending on it.
Exposed on **port 8081** (Volt & Circuit already uses 8080, so this avoids a clash if
both projects are running at the same time).

```bash
docker compose up -d
```

**Result**: both containers started successfully. `fgs-wc-mysql` healthy,
`fgs-wc-wordpress` up on port 8081.

### Step 2 — WordPress install, and a version mismatch found early

Ran the WordPress install wizard (site title, admin user/password, email) against
the initial `wordpress:6.4-apache` image. Went to **Plugins → Add New**, searched
"WooCommerce" — the plugin listing showed **"Incompatible with your version of
WordPress"**, along with several other well-known plugins (Yoast SEO, Google
Analytics for WooCommerce, etc.) flagged the same way.

**Root cause**: `wordpress:6.4-apache` pins an outdated WordPress core version;
current WooCommerce requires a newer one (the admin itself was already prompting
"WordPress 7.0.1 is available").

**Fix**: rather than updating WordPress core after the fact through the admin UI
(a working fix, but it would leave the Docker setup pinned to a version that's
already wrong on a fresh install), changed `docker-compose.yml` to use
`wordpress:latest` instead, so a fresh clone of this repo always gets a
WooCommerce-compatible WordPress version out of the box:

```bash
docker compose down -v
docker compose up -d
```

(`-v` also drops the just-created WordPress install, requiring the setup wizard to
be re-run — acceptable at this stage since no content had been created yet.)

Re-ran the WordPress install wizard on the corrected `wordpress:latest` image — no
version warnings this time. Installed and activated WooCommerce from **Plugins → Add
New**, completed its onboarding wizard (country, currency).

### Step 3 — Test catalog

Created a small catalog through the WooCommerce admin UI (not scripted, since this is
just 3 products — unlike the 13-product bulk seed in `volt-circuit-prestashop-seo`,
which justified a script):

| Product | Type | Variations | Price | Stock |
|---|---|---|---|---|
| Test Wireless Headphones | Variable | Color: Black / White | 59.99 | 50 each |
| Test USB-C Charger | Simple | — | 19.99 | 100 |
| Test Phone Case | Variable | Model: iPhone 15 / iPhone 15 Pro | 14.99 | 30 each |

This gives the feed generator both simple products and variable products with
attribute-based variations to handle correctly — mirroring the same product-type
diversity the PrestaShop module was tested against in Volt & Circuit.

### Step 4 — Plugin implementation

Single-file plugin, `feedgoogleshopping.php`, using WordPress's native plugin header
comment (`Plugin Name`, `Requires Plugins: woocommerce`) so WordPress recognizes it
and enforces the WooCommerce dependency automatically.

Key design decisions, mirroring the PrestaShop module's architecture on WordPress's
own idioms:

- **`add_rewrite_rule()`** registers the public feed URL (`/feed/google-shopping/`) —
  the WordPress equivalent of PrestaShop's front controller routing.
- **`register_activation_hook()`** calls `flush_rewrite_rules()` on activation, which
  is required in WordPress for a newly-added rewrite rule to actually take effect
  without the admin manually re-saving Settings → Permalinks.
- Feed generation uses WooCommerce's own `wc_get_products()` and `WC_Product` /
  `WC_Product_Variation` classes — reading price, stock, SKU, and images through
  WooCommerce's CRUD API rather than raw `$wpdb` queries, the same principle applied
  in the PrestaShop module (using `Product`/`StockAvailable` instead of raw SQL where
  possible).
- Variable products are expanded into one feed `<item>` per variation
  (`$product->get_children()`), matching Google Shopping's expectation of one entry
  per sellable variant — same approach as the PrestaShop module.

### Step 5 — Installing and testing

```bash
docker cp plugin/feedgoogleshopping fgs-wc-wordpress:/var/www/html/wp-content/plugins/feedgoogleshopping
```

Activated from **Plugins → Installed Plugins**. WordPress correctly enforced the
`Requires Plugins: woocommerce` dependency declared in the header.

**Bug found and fixed: feed URL returned 404 with default WordPress permalinks.**
Loading `/feed/google-shopping/` returned a plain Apache 404. Diagnosis: WordPress
ships with **Settings → Permalinks** set to "Plain" by default (`?p=123`-style URLs),
which disables `mod_rewrite`-based custom rewrite rules like the one this plugin
registers — the rule was correctly added to WordPress's rewrite table, but never
actually matched because "Plain" mode ignores it entirely.

**Fix**: changed **Settings → Permalinks** to "Post name" (WordPress's standard
pretty-permalink structure), which both enables rewrite-rule matching and triggers a
rules flush on save. After that, the feed URL worked immediately.

**Takeaway**: worth calling out to a client that ships with default WordPress
settings — a custom rewrite-rule-based feature will silently 404 until permalinks are
configured, which is easy to miss if you only test with pretty permalinks already
enabled locally.

**Result**: feed generated correctly — 5 items (2 headphones variations, 1 charger,
2 phone case variations), correct tax-consistent prices, correct per-variation stock
availability, and correctly using WooCommerce's native query-string variation URLs
(`?attribute_color=Black`) rather than any `#fragment` — WooCommerce already handles
this correctly by default, unlike the PrestaShop `#fragment` issue documented in the
sibling project. `g:mpn` is correctly omitted when no SKU is set (expected, since test
products weren't given SKUs). `g:description` and `g:image_link` render empty for
products without a short description or featured image set — this reflects incomplete
test data, not a plugin defect; left as-is since the goal was validating the feed
logic, not building a polished test catalog.

## Screenshots

See `/screenshots`:
- `plugin_active.png` — plugin listed and active in the WordPress admin
- `feed_output.png` — the generated XML feed, rendered in the browser

<!-- Next: push both projects to GitHub and add to the portfolio. -->
