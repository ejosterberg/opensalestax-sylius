# opensalestax-sylius

> Sylius bundle that replaces Sylius's built-in tax calculation
> with destination-based US sales tax computed by a self-hosted
> [OpenSalesTax](https://github.com/ejosterberg/open-sales-tax-website) engine.

[![CI](https://github.com/ejosterberg/opensalestax-sylius/actions/workflows/ci.yml/badge.svg)](https://github.com/ejosterberg/opensalestax-sylius/actions/workflows/ci.yml)
[![License: Apache-2.0 OR GPL-2.0-or-later](https://img.shields.io/badge/license-Apache--2.0%20OR%20GPL--2.0--or--later-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/packagist/php-v/ejosterberg/opensalestax-sylius.svg)](composer.json)

Free, self-hostable, no per-transaction fees, no SaaS lock-in.
The merchant runs both Sylius and the OpenSalesTax engine on
their own infrastructure.

## Status

**v0.1.0-alpha.1** — initial release. Single-line tax calculation
through a Sylius `TaxCalculationStrategy`, USD/US gating,
fail-soft engine error handling, optional per-state nexus filter.

## Install

```bash
composer require ejosterberg/opensalestax-sylius
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...existing bundles...
    OpenSalesTax\Sylius\OpenSalesTaxSyliusBundle::class => ['all' => true],
];
```

Add a config file at `config/packages/opensalestax_sylius.yaml`:

```yaml
opensalestax_sylius:
    engine_url:       '%env(OSTAX_ENGINE_URL)%'   # e.g. http://10.32.161.126:8080
    api_key:          '%env(default::OSTAX_API_KEY)%'
    timeout_seconds:  5.0
    fail_hard:        false
    default_category: general
    nexus_states:     []         # e.g. ['MN', 'WI']  — empty = collect everywhere
    cache_ttl_seconds: 3600
```

Then in Sylius admin (`Configuration → Channels → <your channel>
→ Tax Calculation Strategy`), select `OpenSalesTax`. Place a
test order shipping to a US ZIP — destination-based tax appears
on the order.

## How it works

1. Sylius invokes `OstaxTaxationStrategy::applyTaxes()` once per
   order during checkout recalculation.
2. The strategy reads the destination ZIP / state / currency /
   country off the order's shipping address.
3. For each `OrderItemUnit`, it asks `OstaxCalculator` for the
   tax amount. The calculator gates on USD-only, US-only,
   nexus-state filter, then calls the OST engine via
   `POST /v1/calculate`.
4. Per-unit tax is added as an adjustment of type `tax` labeled
   `OpenSalesTax`.

Cache: each (zip, category, cents) tuple is memoized in Symfony's
`cache.app` PSR-6 pool for `cache_ttl_seconds` (default 1 hour).

## Behavior matrix

| Order shape | What happens |
|-------------|--------------|
| USD, US ship-to, valid 5-digit ZIP, in-nexus | Engine called; tax adjustment added |
| Non-USD currency | 0.0 tax — Sylius's other strategies / built-in `TaxRate` rows handle it |
| Non-US ship-to country | 0.0 tax (constitution §5) |
| Missing or invalid ZIP | 0.0 tax |
| `nexus_states` set + ship-to state not on list | 0.0 tax |
| Engine unreachable, `fail_hard: false` (default) | 0.0 tax + warning logged |
| Engine unreachable, `fail_hard: true` | Throws — surfaces as a Sylius checkout error |

## What this bundle does NOT do

- File or remit collected tax (calculation only — constitution §6)
- Validate addresses or autocomplete (out-of-scope §10)
- Handle non-USD currency or non-US ship-to (constitution §5)
- Validate exemption certificates against state DOR (§10)
- Marketplace-facilitator special handling (§10)
- Run a standalone HTTP / webhook server (constitution §2 — purely
  in-process; outbound calls to the engine only)

## Configuration reference

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `engine_url` | string | (required) | Base URL of the OST engine. Must be `http://` or `https://`. |
| `api_key` | string\|null | `null` | Optional API key (sent as `X-API-Key` header). |
| `timeout_seconds` | float | `5.0` | Per-request timeout. |
| `fail_hard` | bool | `false` | When `true`, engine errors throw and surface as Sylius errors. Default: fail-soft (return 0, log warning). |
| `default_category` | enum | `general` | OST category sent for line items with no per-product mapping. One of `general`, `clothing`, `groceries`, `prescription_drugs`, `prepared_food`, `digital_goods`. |
| `nexus_states` | string[] | `[]` | Allowlist of US state codes (e.g. `['MN', 'WI']`). Empty = collect everywhere. |
| `cache_ttl_seconds` | int | `3600` | Cache TTL for engine results. `0` disables caching. |

## Compatibility

| Sylius | OST engine | This bundle |
|--------|------------|-------------|
| `^1.13` | `v0.14+` (v1 API) | `0.1.x` |

## License

Dual-licensed under your choice of:

- [Apache License 2.0](LICENSE-APACHE.txt), OR
- [GNU GPL 2.0 or later](LICENSE-GPL.txt)

See [`LICENSE`](LICENSE) for the SPDX declaration. The dual
licensing exists to keep the OpenSalesTax portfolio's licensing
footprint consistent across ecosystems.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md). DCO sign-off required on
every commit.

## Disclaimer

This bundle calculates US sales tax. It does not file returns,
remit collected tax, or validate exemption certificates against
state DOR systems. The merchant remains responsible for filing
and remittance.
