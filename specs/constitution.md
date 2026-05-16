# Constitution — opensalestax-sylius

> Non-negotiable principles. Read before writing code; flag conflicts
> explicitly before deviating.

## §1. Mission

Ship a free, self-hostable **Sylius bundle** that routes Sylius's
tax-calculation pipeline through an OpenSalesTax engine instance
for destination-based US sales tax. Same merchant value
proposition as the WooCommerce / Vendure / Medusa / Saleor / Odoo
siblings: no per-transaction fees, no SaaS lock-in, merchant
runs both Sylius and OST on their own infrastructure.

## §2. Architecture (locked 2026-05-15)

- **Symfony bundle** (Composer package, type `symfony-bundle`).
  Loaded by the merchant via `config/bundles.php`. The bundle is
  pure in-process: it registers an `OstaxCalculator` (Sylius
  `CalculatorInterface`) and an `OstaxTaxationStrategy` (Sylius
  `TaxCalculationStrategyInterface`) via DI service tags.
- **No standalone HTTP server, no webhook subscriptions, no
  inbound authentication surface.** The trust boundary is the
  merchant's own Sylius host. The bundle makes only outbound
  calls to the merchant's OST engine.
- **Merchant-self-hosted via Packagist.** Distributed as
  `ejosterberg/opensalestax-sylius`; merchant `composer require`s
  it, registers the bundle, configures via Symfony's `config/packages/`.
  No hosted SaaS option in v0.x.

## §3. License

Dual: `Apache-2.0 OR GPL-2.0-or-later`. Same dual the WooCommerce
sibling uses; rationale identical (cross-ecosystem licensing
footprint consistency, optional GPL-redistributability for any
future Sylius marketplace that requires it).

DCO sign-off mandatory on every commit. No AI co-author trailers.
No `--no-verify`, no `--no-gpg-sign`.

## §4. Engine-call contract

The OST engine HTTP API (v1) is the source of truth. The bundle
calls:

- `POST /v1/calculate` — per-line tax calculation, destination ZIP
- `GET /v1/health` — for connectivity check (used at startup probe
  time + by external callers)

The bundle NEVER imports OST internals or relies on
undocumented engine behavior. The HTTP API is the contract; we
pin the engine `v1` API in our README's compatibility matrix.

## §5. USD-only / US-only

The OST engine is US-only and USD-only by design. When Sylius
invokes the calculator on a non-USD order or with a non-US
ship-to address, the calculator returns `0.0` so Sylius's
built-in tax-rate pipeline handles the fallback.

`OstaxTaxationStrategy::supports()` opts in only when the order
currency is USD AND the country (when known) is US. When the
country is unknown (cart-stage), the strategy still opts in and
the per-line gates inside the calculator handle it.

## §6. Calculation only

Never file returns, never remit collected tax, never validate
addresses or exemption certificates. The bundle computes tax;
the merchant remits. Every README, CHANGELOG entry, and admin-
facing string carries this disclaimer.

## §7. Trust boundary

Unlike the Saleor sibling (which receives signed inbound
webhooks and verifies JWTs), this bundle is **purely outbound**.
It runs inside the merchant's Sylius process; whatever code
loaded the bundle is already trusted. There is no inbound HTTP
surface, no signature verification, no auth tokens to manage on
our side.

The bundle's options (engine URL, optional API key, fail-hard
flag, etc.) come from Symfony's config tree (`opensalestax_sylius:`
in `config/packages/`), which is merchant-authored. The bundle
validates them at container build time (URL scheme allowlist,
state-code regex, etc.) and fails fast on bad input.

## §8. Fail-soft policy

When the OST engine is unreachable or returns an error, the
calculator returns `0.0` and logs a warning. Sylius's built-in
tax fallback then applies. Merchants can opt into **fail-hard**
behavior (throw, which surfaces as a Sylius checkout error) via
`opensalestax_sylius.fail_hard: true`. Default is fail-soft.

## §9. Test environment

Unit tests use anonymous-class stubs of Sylius's
`OrderInterface`, `OrderItemInterface`, `OrderItemUnitInterface`,
`AddressInterface`, `ZoneInterface`. We do NOT pull in
`sylius/sylius` as a test dep — the surface area we exercise is
the four interface methods we implement against, and stubs keep
the test runtime fast and the dependency graph small.

PHPStan + Psalm validate the implementation against the real
Sylius interface signatures (resolved via the suggested-but-not-
required `sylius/sylius` package, when installed in dev).

Integration tests against a real Sylius instance are out of
scope for v0.1.0; planned for v0.2 (live VM stand-up via the
captain's Proxmox playbook).

## §10. Out of scope

Per the engine + project constitutions:

- Tax filing / remittance
- Address validation / autocomplete
- Non-USD currency
- Non-US jurisdictions
- Tax-exempt customer certificate validation against state DOR
- Marketplace-facilitator handling (NJ / CA seller-of-record edge cases)
- Modifying upstream Sylius source
- Standalone HTTP / webhook server (Decision §2 — Sylius invokes
  us in-process; we don't receive callbacks)
- Admin UI in v0.x (configuration is YAML config; an embedded
  admin panel is a v1.x candidate)
- Per-product OST category mapping persisted in Sylius's product
  taxonomy (v0.2 — initially everything uses `default_category`)
