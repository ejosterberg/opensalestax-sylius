# Current state — opensalestax-sylius

> Snapshot updated each phase. Last updated 2026-05-15
> (post-v0.1.0-alpha.1).

## Shipped

### v0.1.0-alpha.1 (2026-05-15) — initial release

- Symfony bundle skeleton (`OpenSalesTaxSyliusBundle` extending
  `Bundle`; DI extension registering 5 services)
- `Configuration` class defining the `opensalestax_sylius:`
  config tree with 7 settings
- `OstaxConfig` validated value-object (URL scheme + state-code
  + category enum + non-negative timeout/TTL validation)
- `OstaxClient` thin facade over `ejosterberg/opensalestax` SDK
- `OstaxCache` PSR-6-backed memoization (60-min default TTL,
  optional pool injection)
- `OstaxCalculator` implementing Sylius's `CalculatorInterface`,
  with USD/US/ZIP/nexus gates, fail-soft default
- `OstaxTaxationStrategy` implementing Sylius's
  `TaxCalculationStrategyInterface`, walking order item units
  and applying tax adjustments
- `AdjustmentSpec` lightweight value-object adapter so the
  bundle works without a hard `sylius/sylius` composer require
- Unit tests covering config validation, cache behavior,
  calculator gates, and strategy orchestration
- CI matrix: PHP 8.2 / 8.3 / 8.4 — phpunit + phpstan + psalm
  + composer audit + DCO sign-off check
- Dual-license declaration (Apache-2.0 OR GPL-2.0-or-later)
- Sample merchant config at
  `tests/Application/config/packages/opensalestax_sylius.yaml`

## Not shipped (queued for next phase)

### v0.2 — per-product category mapping

Persist per-product OST categories in Sylius's product
taxonomy. Today, every line uses `default_category`.

### v0.2 — live Sylius VM integration test

Stand up a Sylius dev install on a Proxmox VM (range 900-999),
install the bundle from a path repo, place a test order with
US/55401 ship-to, assert the engine breakdown lands as a
tax adjustment.

### v0.2 — refund handling

Walk Sylius's refund flow and prorate the parent order's tax
breakdown rather than re-calling the engine. Mirrors WooCom v0.4.

### v1.0 — admin UI panel

Sylius admin route showing engine connection status, recent
calculation log (ring buffer), per-channel nexus configuration.

## Schema / DB changes

None this release. The bundle stores no Sylius DB rows; its only
runtime state is the PSR-6 cache (ephemeral).

## Sibling-project map

See `../opensalestax-Odoo/portfolio/state.md` for the full
portfolio. Direct siblings:

- `opensalestax-vendure` — TS/Vendure (architecturally closest)
- `opensalestax-woocommerce` — PHP/WordPress (license pattern source)
- `opensalestax-php` — runtime SDK dependency
