# ADR 001 — register both `CalculatorInterface` and `TaxCalculationStrategyInterface`

> Status: **accepted** (2026-05-15, locked at v0.1.0-alpha.1)
>
> Decision-maker: captain session under Eric Osterberg

## Context

Sylius offers two tax-extension surfaces:

1. **`Sylius\Component\Taxation\Calculator\CalculatorInterface`**
   — `calculate(float $base, TaxRateInterface $rate): float`. Sylius's
   default `DefaultCalculator` simply does `$base * $rate->getAmount()`.
2. **`Sylius\Component\Core\Taxation\Strategy\TaxCalculationStrategyInterface`**
   — `applyTaxes(OrderInterface $order, ZoneInterface $zone): void`. Sylius
   ships `OrderItemsTaxesApplicator` and `OrderItemUnitsTaxesApplicator`.

A Sylius bundle can register an implementation of either, both, or
neither. The bundle's behavior depends on which the merchant selects
in admin (channel-level config).

## Question

Should this bundle register only the calculator, only the strategy,
or both?

## Options considered

### A — calculator only

- **Pro:** smallest surface area; reuses Sylius's strategy machinery.
- **Con:** Sylius's strategy invokes the calculator only after
  resolving a `TaxRate`. We don't have a `TaxRate` — the engine
  resolves rates from the destination ZIP. Forcing the merchant
  to create a placeholder `TaxRate` row just so Sylius's strategy
  has something to pass to our calculator is awkward.
- **Con:** the merchant cannot select OST as a tax-calculation strategy
  in admin (the natural mental model — "I want to use OST for this
  channel").

### B — strategy only

- **Pro:** clean merchant UX — pick "OpenSalesTax" as the channel's
  tax-calculation strategy; we handle the rest.
- **Con:** we lose the calculator-shaped seam. Some merchants might
  want to keep Sylius's standard order-level flow (e.g. Sylius's
  zone resolution) and only swap the per-rate math.
- **Con:** harder to test the per-line gate logic in isolation.

### C — both (chosen)

- Register the calculator under type alias `ostax`. Tagged
  `sylius.tax_calculator`.
- Register the strategy under type alias `ostax`. Tagged
  `sylius.taxation_strategy`.
- The strategy uses the calculator internally (composition) so
  the gating logic lives in one place.
- Merchants pick the strategy in admin; the calculator is
  available as a building-block for advanced use cases.

## Decision

**Option C — register both.**

Cost is minimal (the calculator and strategy are both thin wrappers
around the same gating + engine call). Merchant flexibility is
maximized. Mirror's the Vendure sibling's pattern of registering both
`TaxLineCalculationStrategy` and an optional `TaxZoneStrategy`.

## Consequences

- The bundle's DI extension registers two services with two tags.
- The strategy holds a reference to the calculator (constructor
  injection — explicit composition).
- Sylius's calculator-interface signature is context-free
  (`calculate(float $base, TaxRateInterface $rate): float`) but
  the engine call needs ZIP + currency + country + state.
  The strategy primes the calculator's per-order context via
  `OstaxCalculator::setOrderContext()` before the per-unit loop;
  the strategy itself uses `calculateForContext()` directly to
  avoid the stateful priming dance for its own iteration.
- The priming exists for any third-party Sylius code that
  resolves the calculator via `sylius.tax_calculator` and calls
  the interface method without going through our strategy. If
  no such code exists in the v0.2 audit, the priming can be
  removed.

## Alternatives revisited

If a v1.x admin UI introduces per-product OST category mappings,
both extension points still work. The strategy will pull the
category from the product's metadata; the calculator's
`setOrderContext()` call will include the per-line category
override.

## References

- Sylius docs: [Customizing Tax Calculation Strategy](https://docs.sylius.com/en/1.13/customization/taxation.html)
- Sibling ADRs:
  - `opensalestax-vendure/specs/decisions/` — same shape, different framework
  - `opensalestax-woocommerce` — uses WC's `woocommerce_calc_tax`
    filter, which is the WordPress analogue
