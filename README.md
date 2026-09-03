# BundleCraft for WooCommerce

Build product bundle promotions with tiered quantity discounts, a modern Vue-powered admin, analytics, and a customer-facing bundle builder widget.

- **Contributors:** ashawkat
- **Requires:** WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+
- **License:** GPL-2.0-or-later

## Features

- Bundle editor with product search, drag-and-drop ordering, tiered quantity discounts, text/color controls, and a live preview.
- Storefront widget (`[bundlecraft_bundle id="…"]`) with variation support, quantity steppers or select mode, discount progress, summary, and mobile sticky cart.
- Server-authoritative pricing: the browser never computes discounts. Quotes and cart additions go through the WordPress REST API, and the discount is applied as a real WooCommerce coupon.
- Analytics dashboard (coupon usage, revenue over time, cart share, top bundles) with date-range filtering.
- Diagnostics page and optional WooCommerce debug logging.
- Automatic migration from the legacy "mmb" table of the author's earlier bundle plugin.

## Repository layout

```
bundlecraft-for-woocommerce.php   Plugin bootstrap (header, constants, autoloader, hooks)
includes/                         PHP classes (namespace BundleCraft\)
  class-plugin.php                  Orchestrator: menus, enqueues, page shells
  class-install.php                 Schema, upgrades, legacy migration
  class-bundles.php                 Bundle CRUD + tier logic
  class-rest.php                    bundlecraft/v1 REST routes (admin + storefront)
  class-cart.php                    Dynamic coupon engine + cart hooks
  class-analytics.php               Dashboard queries
  class-frontend.php                Product/bundle payloads + widget rendering
  class-shortcode.php               [bundlecraft_bundle]
  class-settings.php                Option-backed settings
templates/bundle-display.php      Widget shell (payload + mount point)
src/                              Vue 3 sources (admin app, storefront widget)
scripts/build.mjs                 Vite build (two standalone IIFE bundles + CSS)
assets/build/                     Compiled bundles (committed, used by the plugin)
languages/                        Translations (bundlecraft-for-woocommerce.pot)
```

## Development

```
npm install
npm run build      # production build into assets/build/
npm run dev        # watch mode
```

The two entry points are built as self-contained IIFE bundles (no shared chunks, no ES-module imports) so WordPress can enqueue them directly.

## Releasing a distribution zip

```
npm run build
wp dist-archive . bundlecraft-for-woocommerce.zip
```

`.distignore` excludes development files (`src/`, `node_modules/`, configs) from the archive.

## Security model

- Admin REST routes require `manage_options` via `permission_callback` and the REST nonce.
- Storefront routes validate and sanitize every argument server-side; the discount amount is always recomputed from live product prices before the coupon is created.
- Dynamic coupons are restricted to the bundle's products, expire after 24 hours, and unused ones are garbage-collected daily.
