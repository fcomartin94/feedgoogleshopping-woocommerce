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

- WordPress (Docker, `wordpress:latest` — the pinned `6.4-apache` image predates the
  WooCommerce version required here, so the setup tracks the latest core image instead
  of a fixed older version)
- WooCommerce
- Plugin registered via WordPress's `add_action`/rewrite rule system, no external
  dependencies

## Implementation

Single-file plugin, `feedgoogleshopping.php`, using WordPress's native plugin header
(`Plugin Name`, `Requires Plugins: woocommerce`) so WordPress enforces the WooCommerce
dependency automatically rather than failing silently if it's missing.

Mirrors the PrestaShop module's architecture on WordPress's own idioms:

- `add_rewrite_rule()` registers the public feed URL (`/feed/google-shopping/`) — the
  WordPress equivalent of PrestaShop's front controller routing. WordPress resolves
  incoming URLs against a rewrite-rules table persisted in the database, not
  recalculated on every request; `register_activation_hook()` calls
  `flush_rewrite_rules()` once, at plugin activation, which is the correct point to
  persist a newly added rule — doing it on every page load would be expensive, and
  never doing it would leave the rule registered only in memory, never reaching the
  table WordPress actually routes against.
- Feed generation uses WooCommerce's own `wc_get_products()` and `WC_Product`/
  `WC_Product_Variation` classes — reading price, stock, SKU, and images through
  WooCommerce's CRUD API rather than raw `$wpdb` queries, the same principle applied in
  the PrestaShop module (using `Product`/`StockAvailable` instead of raw SQL where
  possible).
- Variable products are expanded into one feed `<item>` per variation via
  `get_children()`, matching Google Shopping's expectation of one entry per sellable
  variant — the same approach as the PrestaShop module, applied to WooCommerce's own
  model of variations as child posts related to a parent product post.

Depends on pretty permalinks being enabled (`Settings → Permalinks`, e.g. "Post name")
rather than WordPress's default "Plain" mode — under "Plain", incoming URLs are
resolved by literal query string and the rewrite-rules table this plugin registers
against is never consulted at all, so the feed route wouldn't resolve regardless of how
the plugin registers it.

## Testing

Installed into a WordPress/WooCommerce test store seeded with a small catalog covering
both simple and variable products (with color/model variations), to exercise both feed
paths. The generated feed correctly produces one `<item>` per variation with consistent
tax pricing, correct per-variation stock availability, and WooCommerce's native
query-string variation URLs (`?attribute_color=Black`) — unlike PrestaShop, which
requires the `#fragment` workaround documented in the `feedgoogleshopping-prestashop`
project, WooCommerce already builds crawlable, server-resolvable variation URLs by
default.

## Screenshots

See `/screenshots`:
- `plugin_active.png` — plugin listed and active in the WordPress admin
- `feed_output.png` — the generated XML feed, rendered in the browser
