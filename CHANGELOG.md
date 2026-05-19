# Changelog

All notable changes to `opensalestax-sylius` will be documented
here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0-alpha.2] — 2026-05-19

### Changed

- **CP-8 Phase 5D: bumped `ejosterberg/opensalestax` constraint to `^0.2.0`.**
  Picks up the new `OpenSalesTax\Client::capabilities()` /
  `OpenSalesTax\Client::capabilitiesCached()` helpers for engine v0.59.0's
  `/v1/capabilities` endpoint. No merchant-visible behavior change in
  this release — the helper is available to connector code but not yet
  wired into any feature path. Constraint bump only; Test Connection
  surface enrichment deferred to v-next.

## [0.1.0-alpha.1] — 2026-05-15

### Added

- Initial release of the Sylius tax-calculation bundle.
- `OstaxCalculator` implementing `Sylius\Component\Taxation\Calculator\CalculatorInterface`.
- `OstaxTaxationStrategy` implementing `Sylius\Component\Core\Taxation\Strategy\TaxCalculationStrategyInterface`.
- `OstaxClient` wrapper around `ejosterberg/opensalestax` PHP SDK.
- `OstaxConfig` validated value-object built from the bundle's
  `opensalestax_sylius:` config block.
- `OstaxCache` PSR-6-backed memoization (60-min default TTL).
- USD-only / US-only gates (constitution §5) — non-USD or non-US
  ship-to falls back to Sylius's built-in tax handling.
- Fail-soft default (constitution §8) — engine errors return 0.0
  + log a warning. `fail_hard: true` opts into throwing.
- Per-state nexus filter (`nexus_states: ['MN', 'WI']`) — mirrors
  the WooCommerce v0.5.0 / Vendure v1.2 sibling pattern.
- Symfony bundle skeleton (`AbstractBundle`-style extension +
  config tree).
- PHPUnit + PHPStan + Psalm + composer audit CI on PHP 8.2 / 8.3 / 8.4.

### Compatibility

- Sylius `^1.13`
- OpenSalesTax engine v1 API (engine v0.14+)

[Unreleased]: https://github.com/ejosterberg/opensalestax-sylius/compare/v0.1.0-alpha.1...HEAD
[0.1.0-alpha.1]: https://github.com/ejosterberg/opensalestax-sylius/releases/tag/v0.1.0-alpha.1
