<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Strategy;

use OpenSalesTax\Sylius\Calculator\OstaxCalculator;
use OpenSalesTax\Sylius\Config\OstaxConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Taxation\Strategy\TaxCalculationStrategyInterface;

/**
 * Sylius `TaxCalculationStrategyInterface` implementation that orchestrates
 * the `OstaxCalculator` over an order's units.
 *
 * Sylius invokes `applyTaxes($order, $zone)` once per order during checkout
 * recalculation. We:
 *
 *   1. Pull destination address + currency from the order
 *   2. Prime the calculator with that context (zip, state, currency, country)
 *   3. Walk every order item unit + apply the calculator's result as a tax
 *      adjustment on that unit
 *   4. Reset the calculator context (defensive — keeps state from leaking
 *      across orders if the same calculator service is reused)
 *
 * `getType()` returns `'ostax'` — the type label the merchant selects per-
 * channel in Sylius admin (Channel → Tax Calculation Strategy).
 *
 * `supports()` is a soft predicate: we return `true` whenever the order's
 * currency is USD AND the ship-to country (when known) is US. Sylius will
 * fall through to its built-in strategies when we return `false`. This is
 * the same opt-out pattern Vendure uses (constitution §5).
 */
final class OstaxTaxationStrategy implements TaxCalculationStrategyInterface
{
    public const TYPE = 'ostax';

    private const TAX_ADJUSTMENT_LABEL = 'OpenSalesTax';
    private const SUPPORTED_CURRENCY = 'USD';
    private const SUPPORTED_COUNTRY = 'US';

    private LoggerInterface $logger;

    public function __construct(
        private readonly OstaxCalculator $calculator,
        private readonly OstaxConfig $config,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function supports(OrderInterface $order, ZoneInterface $zone): bool
    {
        unset($zone); // we look at the order's address directly

        if ((string) $order->getCurrencyCode() !== self::SUPPORTED_CURRENCY) {
            return false;
        }

        $country = $this->resolveCountryCode($order);

        // When country is unknown (cart-stage with no address yet), opt in
        // anyway — the per-line gate inside `OstaxCalculator::calculate()`
        // will short-circuit each call to 0.0 until the address resolves.
        return $country === null || $country === self::SUPPORTED_COUNTRY;
    }

    public function applyTaxes(OrderInterface $order, ZoneInterface $zone): void
    {
        unset($zone); // unused for OST — engine resolves jurisdiction from ZIP

        $context = [
            'currency' => (string) $order->getCurrencyCode(),
            'country' => $this->resolveCountryCode($order) ?? '',
            'zip5' => $this->resolveZip($order) ?? '',
            'state' => $this->resolveState($order) ?? '',
            'category' => $this->config->defaultCategory,
        ];

        // Prime once via setOrderContext() so a Sylius-issued
        // CalculatorInterface::calculate() call (rare; mostly used by other
        // tax-calculation strategies that locate calculators by name) sees
        // the same context. Inline calls below use calculateForContext()
        // directly to avoid the stateful priming/reset dance.
        $this->calculator->setOrderContext($context);

        try {
            foreach ($this->orderItemUnits($order) as $unit) {
                $base = $this->resolveUnitTaxableBase($unit);
                if ($base <= 0.0) {
                    continue;
                }

                $taxAmountFloat = $this->calculator->calculateForContext($base, $context);
                $taxAmount = (int) round($taxAmountFloat);
                if ($taxAmount === 0) {
                    continue;
                }

                $this->addTaxAdjustment($unit, $taxAmount);
            }
        } finally {
            // Defensive: never leave context set on a long-lived service.
            $this->calculator->setOrderContext(null);
        }
    }

    /**
     * @return iterable<object>
     */
    private function orderItemUnits(OrderInterface $order): iterable
    {
        foreach ($order->getItems() as $item) {
            $units = method_exists($item, 'getUnits') ? $item->getUnits() : [];
            /** @var iterable<object> $units */
            foreach ($units as $unit) {
                yield $unit;
            }
        }
    }

    private function resolveUnitTaxableBase(object $unit): float
    {
        // Sylius's `OrderItemUnit` exposes `getTotal()` (in cents). Fall back
        // to `getAdjustedTotal('PROMOTION_ADJUSTMENT')` when present so we
        // tax the post-discount amount (matches Sylius default behavior).
        if (method_exists($unit, 'getAdjustedTotal')) {
            $adjusted = $unit->getAdjustedTotal('promotion_adjustment');
            if (is_int($adjusted) || is_float($adjusted)) {
                return max(0.0, (float) $adjusted);
            }
        }
        if (method_exists($unit, 'getTotal')) {
            $total = $unit->getTotal();
            if (is_int($total) || is_float($total)) {
                return max(0.0, (float) $total);
            }
        }

        return 0.0;
    }

    private function addTaxAdjustment(object $unit, int $taxAmount): void
    {
        if (!method_exists($unit, 'addAdjustment')) {
            $this->logger->warning(
                'OstaxTaxationStrategy: order item unit has no addAdjustment() method; skipping.',
                ['unit_class' => $unit::class],
            );
            return;
        }

        // Build an adjustment via the unit's adjustment factory if available;
        // otherwise leave it to the merchant's bundle wiring (Sylius-specific
        // factory injection — handled by Sylius's own service container).
        // We construct via a lightweight value-object stub to keep this
        // bundle decoupled from `Sylius\Component\Order\Factory\AdjustmentFactory`,
        // which would force a hard `sylius/sylius` dependency.
        $adjustment = AdjustmentSpec::tax(self::TAX_ADJUSTMENT_LABEL, $taxAmount);
        $unit->addAdjustment($adjustment);
    }

    private function resolveZip(OrderInterface $order): ?string
    {
        $address = $order->getShippingAddress() ?? $order->getBillingAddress();
        if ($address === null || !method_exists($address, 'getPostcode')) {
            return null;
        }
        $postcode = $address->getPostcode();

        return is_string($postcode) ? $postcode : null;
    }

    private function resolveState(OrderInterface $order): ?string
    {
        $address = $order->getShippingAddress() ?? $order->getBillingAddress();
        if ($address === null) {
            return null;
        }

        // Sylius addresses may carry the ISO 3166-2 province code on
        // `provinceCode` (e.g. "US-MN"). Strip the country prefix when present.
        if (method_exists($address, 'getProvinceCode')) {
            $code = $address->getProvinceCode();
            if (is_string($code) && $code !== '') {
                if (str_starts_with($code, 'US-')) {
                    return substr($code, 3);
                }
                return $code;
            }
        }
        if (method_exists($address, 'getProvinceName')) {
            $name = $address->getProvinceName();
            if (is_string($name) && strlen($name) === 2) {
                return strtoupper($name);
            }
        }

        return null;
    }

    private function resolveCountryCode(OrderInterface $order): ?string
    {
        $address = $order->getShippingAddress() ?? $order->getBillingAddress();
        if ($address === null || !method_exists($address, 'getCountryCode')) {
            return null;
        }
        $cc = $address->getCountryCode();

        return is_string($cc) && $cc !== '' ? strtoupper($cc) : null;
    }
}
