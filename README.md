# SilverShop Currency Format

Set the shop **base currency in the CMS** (Settings → Shop) and render prices **locale-aware** for it, for
[SilverShop](https://github.com/silvershop/silvershop-core).

Out of the box, silvershop's base currency is **YAML-only** (`ShopConfigExtension.base_currency`, default `NZD`) and
`ShopCurrency` renders every price with a fixed `$` symbol regardless of that currency. This module fills the gap for
the common **single-currency shop**: pick your currency in the CMS and prices format correctly for it — the right
symbol, placement, spacing, grouping and decimal separators, and the currency's own number of decimals.

## What it adds

- A **Currency** selector in **Settings → Shop** (searchable, all 294 ISO 4217 currencies from `symfony/intl`,
  shown as `EUR - € (Euro)`; scoped to `ShopConfigExtension.supported_currencies` when that is configured).
- A **Currency display** setting — `Symbol` (locale default, may disambiguate as `US$` / `JP¥`), `Narrow symbol`
  (force the plain `$` / `¥`), or `ISO code` (`USD`). Same vocabulary as JavaScript's
  `Intl.NumberFormat` `currencyDisplay`.
- **Locale-aware price formatting** via PHP `intl` (ICU) — e.g. on an `nl_NL` site `€ 1.234,50`, on `de_DE`
  `1.234,50 €`, and `¥1,234` for JPY (no decimals). Formatting follows the **site locale** (`i18n::get_locale()`);
  the currency only decides the symbol and the number of decimals.
- Prices render for the selected currency **everywhere** they appear — product listings, detail, price ranges, cart,
  receipts, invoices — via an `Injector` override of silvershop's currency field.

## Non-invasive by design

Leave the selector on **System default** and nothing changes: prices render exactly as stock silvershop/core does
(`ShopCurrency::Nice()`), and the `base_currency` YAML default is untouched. The module only alters behaviour once a
currency is explicitly chosen. Installs that already configure currency via YAML are unaffected.

## Installation

```bash
composer require silvershop/currency-format
```

Then run `dev/build?flush=all`. `ext-intl` is recommended (it powers the locale-aware formatting); without it the
module falls back to a simple `symbol + amount`.

## How it works

- **`LocaleCurrency`** extends core's `ShopCurrency` and is bound via `Injector` to **both** the `Currency` DBField
  cast and the concrete `ShopCurrency` service — so every price uses it, including product **price ranges**, which
  core builds with `ShopCurrency::create()`. When no currency is selected it defers to `parent::Nice()`, a
  transparent drop-in that renders exactly as stock silvershop/core. The per-locale `NumberFormatter` is cached per
  request, so formatting adds negligible overhead versus core.
- The selection is made **authoritative** through core's `updateSiteCurrency` extension hook:
  `CurrencyConfigExtension::updateSiteCurrency()` feeds `SiteConfig.BaseCurrency` into
  `ShopConfigExtension::get_site_currency()`, so orders, conversion **and** display all agree — **no config is mutated
  at runtime**. A blank selection leaves core's `base_currency` default untouched. On a silvershop/core without the
  hook the module still formats prices (display follows the field); only the logical base currency then stays with the
  YAML config.

## Notes

- The comma / space / symbol placement come from the **locale**, not the currency: to display `€ 1.234,50` the site
  must run the `nl_NL` locale. A currency picked under `en_US` renders the US way (`€1,234.50`).
- This is the single-currency stepping-stone; a full multi-currency solution (storefront switcher, live exchange
  rates, per-currency price overrides) is a separate, larger concern.

## Licence

BSD-3-Clause.
