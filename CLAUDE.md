# CLAUDE.md — opensalestax-sylius

> Project memory for Claude sessions on the Sylius bundle. Read
> this AND `specs/constitution.md` + `specs/handoff.md` before
> writing code.

## Mission

Ship a free, self-hostable Sylius bundle that replaces Sylius's
built-in tax calculation with destination-based US sales tax
computed by a self-hosted OpenSalesTax engine. Same value prop
as the WooCommerce / Vendure / Medusa / Saleor / Odoo siblings.

## Stack

- **Language:** PHP 8.2+
- **Framework:** Sylius `^1.13` (Symfony 6.4 / 7.x bundle)
- **Distribution:** Packagist (`ejosterberg/opensalestax-sylius`)
- **License:** Apache-2.0 OR GPL-2.0-or-later (dual — same as WooCom)
- **Tests:** PHPUnit 10 + PHPStan max + Psalm + composer audit;
  GitHub Actions matrix on PHP 8.2 / 8.3 / 8.4

## Architectural anchors

- **Symfony bundle** loaded via `config/bundles.php`. No
  standalone server. The trust boundary is the merchant's own
  Sylius host — no inbound webhook surface, no JWT, no API
  authentication.
- **`OstaxCalculator`** implements
  `Sylius\Component\Taxation\Calculator\CalculatorInterface`.
  Tagged `sylius.tax_calculator` so Sylius's tax-calculator
  registry resolves it under the type alias `ostax`.
- **`OstaxTaxationStrategy`** implements
  `Sylius\Component\Core\Taxation\Strategy\TaxCalculationStrategyInterface`.
  Tagged `sylius.taxation_strategy` so the merchant can pick
  `OpenSalesTax` per-channel in admin.
- **USD-only / US-only** — non-USD orders or non-US ship-to
  addresses return 0 from the calculator and let Sylius's
  built-in pipeline handle the fallback (constitution §5).
- **Fail-soft default** — engine errors return 0 + log warning.
  `fail_hard: true` opts into throwing (constitution §8).
- **Calculation only** — no filing, no remittance, no address
  validation (constitution §6).
- **Per-state nexus filter** — `nexus_states: ['MN', 'WI']` —
  mirrors WooCom v0.5.0 / Vendure v1.2 sibling pattern.

## What NOT to do

- Don't ship a standalone HTTP server. The bundle loads in
  Sylius's process; outbound calls only.
- Don't add webhook subscriptions, JWT verification, or any
  inbound HTTP surface.
- Don't ship a copy of the OST engine or the OST PHP SDK —
  depend on `ejosterberg/opensalestax` upstream.
- Don't accept commits without DCO sign-off (`-s` flag).
- Don't add AI co-author trailers.
- Don't use `--no-verify` / `--no-gpg-sign` on commits.
- Don't add a hard `sylius/sylius` composer require — keep it
  in `suggest`. Anyone who installs the bundle without Sylius
  shouldn't break (the calculator/strategy classes will fail at
  Sylius interface resolution time, which is the right error).

## Releasing

- Single `main` branch.
- Pre-1.0 line uses `v0.X.Y-alpha.N` / `-beta.N` / `-rc.N`
  suffixes. Promote to `v0.X.Y` when API-stable.
- Tag, push tag, GitHub release on the tag.
- Packagist auto-syncs via webhook; refresh via API after each
  PHP-tier tag (per portfolio policy).

## Sibling-project map

- `opensalestax-vendure` — TS Vendure plugin, in-process,
  same architectural shape (tax-line strategy injection)
- `opensalestax-woocommerce` (a.k.a. `opensalestax-for-woocommerce`)
  — PHP plugin, dual-licensed Apache OR GPL, same dual-license
  pattern this bundle uses
- `opensalestax-php` — the PHP SDK both this bundle and the
  WooCommerce plugin depend on
- See `../opensalestax-Odoo/portfolio/state.md` for the
  full portfolio.
