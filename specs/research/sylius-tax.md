# Research — Sylius tax extension points

> Research notes captured 2026-05-15 while scoping the v0.1.0
> implementation.

## Tax flow in Sylius (high level)

Sylius's tax pipeline runs at three points:

1. **Catalog rendering** — product list / detail pages display
   tax-included or tax-excluded prices based on the channel's
   `tax_calculation_strategy`. No tax adjustments are persisted;
   it's a display-time computation.
2. **Cart recalculation** — every cart mutation (add line,
   change quantity, change shipping, change address) triggers
   the channel's tax-calculation-strategy. Tax adjustments are
   applied to `OrderItemUnit` instances.
3. **Checkout finalization** — at the order's
   `cart -> new` state machine transition, taxes are recomputed
   one final time and the order persists with adjustments
   attached.

For (2) and (3) the entry point is
`Sylius\Component\Core\Taxation\Strategy\TaxCalculationStrategyInterface::applyTaxes()`.

## Two extension points

### `CalculatorInterface` — single-rate math

```php
namespace Sylius\Component\Taxation\Calculator;

interface CalculatorInterface
{
    public function calculate(float $base, TaxRateInterface $rate): float;
}
```

Sylius ships one implementation: `DefaultCalculator` —
`return $base * $rate->getAmount();`. The calculator registry
is a tagged service collection (`sylius.tax_calculator`); each
calculator has a `calculator` attribute (the type alias) and a
`label`.

**Use case for OST:** we register `OstaxCalculator` with
`calculator: 'ostax'`. Per-channel admin UI exposes the
calculator selector under "Default tax calculator." When
selected, every `TaxRate` row's calculator-name maps to our
implementation.

### `TaxCalculationStrategyInterface` — order-level orchestration

```php
namespace Sylius\Component\Core\Taxation\Strategy;

interface TaxCalculationStrategyInterface
{
    public function applyTaxes(OrderInterface $order, ZoneInterface $zone): void;
    public function getType(): string;
    public function supports(OrderInterface $order, ZoneInterface $zone): bool;
}
```

Sylius ships two: `OrderItemsTaxesApplicator` (apply at line-
item level) and `OrderItemUnitsTaxesApplicator` (apply at
unit level). The strategy registry is also a tagged service
collection (`sylius.taxation_strategy`).

**Use case for OST:** we register `OstaxTaxationStrategy` with
`type: 'ostax'`. Per-channel admin UI exposes the strategy
selector under "Tax calculation strategy." Selecting it means
Sylius invokes `OstaxTaxationStrategy::applyTaxes()` once per
order recalculation; we walk units, gate, call the engine, add
adjustments.

## Why we register BOTH

We could register just the strategy and skip the calculator —
the strategy handles the engine call directly. But:

- Some merchants might want to keep Sylius's standard order-
  level flow (e.g. tax-zone resolution) and only swap the
  per-rate math. Registering the calculator under type alias
  `ostax` lets the merchant configure their `TaxRate` rows to
  use `ostax` even when they pick a non-OST strategy.
- The calculator's per-line gating is a useful seam for
  testing without the strategy machinery.

## Money units

- `OrderItemUnit::$total` — **integer cents** (e.g. 1234 = $12.34)
- `Adjustment::$amount` — **integer cents**, signed (negative
  for discounts, positive for taxes)
- `TaxRate::$amount` — **float fraction** (e.g. 0.0825 = 8.25%)
- Calculator return value — **float**, but practically integer-
  valued cents (Sylius rounds to int when persisting)

We pass cents into the calculator, return cents; the strategy
casts to int via `round()` before calling `addAdjustment()`.

## Address resolution

`OrderInterface::getShippingAddress()` returns
`AddressInterface|null`. Fields used by the bundle:

- `getCountryCode()` — ISO 3166-1 alpha-2 (e.g. "US")
- `getProvinceCode()` — ISO 3166-2 (e.g. "US-MN") — strip the
  `US-` prefix
- `getProvinceName()` — display name; sometimes a 2-letter code
  if the merchant didn't use Sylius's province seed
- `getPostcode()` — string

We try shipping address first, fall back to billing.

## Channel coupling

Sylius's tax-calculation strategy is configured PER CHANNEL,
not globally. The bundle doesn't need to be aware of this —
Sylius picks the right strategy at order-recalculation time
based on the order's channel. Our service is just one of the
options; merchants enable it per-channel via admin.

## Why not extend Sylius's `OrderTaxesProcessor` directly?

Sylius's tax pipeline runs through a chain:

1. `OrderTaxesProcessor::process()` resolves the channel's strategy
2. Strategy is invoked
3. Strategy's calculator (if it has one) is invoked per line

We hook in at step 2. Hooking lower (e.g. via a Doctrine
event listener on `OnFlushEventArgs`) would be brittle and
miss cart-stage recalculations. Step 2 is the documented
extension point.

## References

- Sylius docs: [Taxation](https://docs.sylius.com/en/1.13/components_and_bundles/components/Taxation/index.html)
- Sylius source v1.13.0: `src/Sylius/Component/Taxation/`,
  `src/Sylius/Component/Core/Taxation/`,
  `src/Sylius/Bundle/CoreBundle/Resources/config/services/taxation.xml`
- Vendure sibling — same architectural shape, different
  framework
