# Handoff — opensalestax-sylius

> What the next session should pick up. Refresh on every
> meaningful change. Last updated 2026-05-15.

## What just shipped

**v0.1.0-alpha.1.** Initial release. Symfony bundle that exposes
an OST-backed `CalculatorInterface` + `TaxCalculationStrategyInterface`
to Sylius. USD/US gating, fail-soft default, per-state nexus
filter. Unit tests + CI green on PHP 8.2 / 8.3 / 8.4.

## Next session — pick from this list

### 1. Live VM integration verification (highest priority)

We have not yet placed a real test order through a real Sylius
install. The unit tests exercise the strategy + calculator + config
in isolation, but the wiring through Sylius's actual checkout
needs a live verification before promoting from `-alpha.N` to a
plain `v0.1.0`.

Recipe (per `~/.claude/proxmox-playbook.md`):

1. Provision a fresh Debian 13 VM, VMID 9xx
2. Install Symfony 7 + Sylius via the official installer
3. `composer require ejosterberg/opensalestax-sylius:^0.1.0-alpha`
4. Add bundle to `config/bundles.php`
5. Create `config/packages/opensalestax_sylius.yaml` pointing at
   the engine VM (10.32.161.126:8080)
6. Set the test channel's tax-calculation-strategy to `OpenSalesTax`
7. Place an order with US/55401 ship-to, verify destination
   tax appears

Capture findings in `specs/decisions/002-live-vm-verification.md`.

### 2. Per-product category mapping

Today every line uses `opensalestax_sylius.default_category`. Real
merchants need per-product overrides (clothing vs. groceries vs.
prescription drugs). Two implementation paths:

- **(a)** Extend Sylius's `Product` entity with an `ostax_category`
  field via a Doctrine extension. More invasive; requires a
  migration the merchant runs.
- **(b)** Use Sylius's existing `TaxCategory` and add a config
  map: `ostax_category_by_tax_category: { Clothing: clothing }`.
  Less invasive; reuses Sylius's own tax taxonomy. Same shape
  the Vendure sibling uses (see `opensalestax-vendure/specs/phase-03-tax-category-mapping/`).

Lean toward (b). Open a `phase-02-category-mapping/` spec
before writing code.

### 3. Refund handling

Sylius's refund flow is more elaborate than WooCommerce's —
order-state-machine driven. Walk the refund-state transition
and either:
- Prorate the parent order's stored breakdown (fast, one-shot
  proration math)
- Re-call the engine with the refund amount (slower but
  authoritative)

WooCom v0.4 uses proration; recommend matching.

### 4. Admin UI panel (v1.0)

Sylius admin route + Twig template showing:
- Engine connection status (live `GET /v1/health` probe with caching)
- Last 50 calculations (ring-buffer log, similar to WooCom's
  CalculationLog)
- Per-channel nexus configuration override

## Captain follow-ups (not blocked on Eric for next session)

- [ ] Submit `ejosterberg/opensalestax-sylius` to Packagist.
  Eric does the 2-minute web action (per
  `~/.claude/CLAUDE.md` → Packagist auto-refresh playbook).
  After submission, the captain triggers refreshes via the
  Safe API token on every subsequent tag.

## Open items for Eric

None expected from v0.1.0-alpha.1 itself.

## Known issues / not-blocked-on-Eric debt

- `AdjustmentSpec` is a lightweight duck-typed adjustment
  passed to Sylius's `addAdjustment()`. For full Doctrine
  persistence we'd need to inject Sylius's own
  `AdjustmentFactoryInterface`. Document the merchant-side
  recipe in `docs/full-sylius-integration.md` (added in v0.2)
  and consider injecting the factory directly when Sylius is
  available — autowiring it via an alias.
- The `setOrderContext()` priming dance on the calculator
  exists because Sylius's `CalculatorInterface::calculate()`
  signature is context-free. The strategy uses
  `calculateForContext()` instead, but the priming remains as
  a defensive measure for any Sylius-internal code path that
  resolves the calculator via the type alias and calls the
  interface method directly. Worth reviewing in v0.2 whether
  the priming is still load-bearing.
