<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Tests\Unit\Strategy;

use Doctrine\Common\Collections\ArrayCollection;
use OpenSalesTax\Sylius\Cache\OstaxCache;
use OpenSalesTax\Sylius\Calculator\OstaxCalculator;
use OpenSalesTax\Sylius\Config\OstaxConfig;
use OpenSalesTax\Sylius\Strategy\AdjustmentSpec;
use OpenSalesTax\Sylius\Strategy\OstaxTaxationStrategy;
use OpenSalesTax\Sylius\Tests\Support\FakeOstaxClient;
use OpenSalesTax\Sylius\Tests\Support\StubOrderItemUnit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class OstaxTaxationStrategyTest extends TestCase
{
    #[Test]
    public function get_type_returns_ostax(): void
    {
        $strategy = $this->makeStrategy(new FakeOstaxClient(['tax_total' => '0']));
        $this->assertSame('ostax', $strategy->getType());
    }

    #[Test]
    public function supports_returns_true_for_usd_us_order(): void
    {
        $strategy = $this->makeStrategy(new FakeOstaxClient(['tax_total' => '0']));
        $order = $this->mockOrder(currency: 'USD', country: 'US');

        $this->assertTrue($strategy->supports($order, $this->mockZone()));
    }

    #[Test]
    public function supports_returns_false_for_non_usd_order(): void
    {
        $strategy = $this->makeStrategy(new FakeOstaxClient(['tax_total' => '0']));
        $order = $this->mockOrder(currency: 'EUR', country: 'US');

        $this->assertFalse($strategy->supports($order, $this->mockZone()));
    }

    #[Test]
    public function supports_returns_false_for_non_us_country(): void
    {
        $strategy = $this->makeStrategy(new FakeOstaxClient(['tax_total' => '0']));
        $order = $this->mockOrder(currency: 'USD', country: 'CA');

        $this->assertFalse($strategy->supports($order, $this->mockZone()));
    }

    #[Test]
    public function supports_returns_true_when_country_unknown(): void
    {
        $strategy = $this->makeStrategy(new FakeOstaxClient(['tax_total' => '0']));
        $order = $this->mockOrder(currency: 'USD', country: null);

        $this->assertTrue($strategy->supports($order, $this->mockZone()));
    }

    #[Test]
    public function apply_taxes_calls_engine_per_unit_and_adds_adjustments(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.83']);
        $strategy = $this->makeStrategy($client);

        $unit1 = new StubOrderItemUnit(total: 1000);
        $unit2 = new StubOrderItemUnit(total: 2000);
        $order = $this->mockOrder(units: [$unit1, $unit2]);

        $strategy->applyTaxes($order, $this->mockZone());

        $this->assertCount(1, $unit1->adjustments);
        $this->assertCount(1, $unit2->adjustments);

        /** @var AdjustmentSpec $adj1 */
        $adj1 = $unit1->adjustments[0];
        $this->assertInstanceOf(AdjustmentSpec::class, $adj1);
        $this->assertSame(AdjustmentSpec::TAX_ADJUSTMENT, $adj1->getType());
        $this->assertSame('OpenSalesTax', $adj1->getLabel());
        // Engine returned 0.83 → 83 cents.
        $this->assertSame(83, $adj1->getAmount());
    }

    #[Test]
    public function apply_taxes_skips_units_with_zero_base(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.83']);
        $strategy = $this->makeStrategy($client);

        $zeroUnit = new StubOrderItemUnit(total: 0);
        $paidUnit = new StubOrderItemUnit(total: 1000);
        $order = $this->mockOrder(units: [$zeroUnit, $paidUnit]);

        $strategy->applyTaxes($order, $this->mockZone());

        $this->assertCount(0, $zeroUnit->adjustments);
        $this->assertCount(1, $paidUnit->adjustments);
        $this->assertSame(1, $client->callCount, 'Engine called only for non-zero unit');
    }

    #[Test]
    public function apply_taxes_skips_units_when_engine_returns_zero(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.00']);
        $strategy = $this->makeStrategy($client);

        $unit = new StubOrderItemUnit(total: 1000);
        $order = $this->mockOrder(units: [$unit]);

        $strategy->applyTaxes($order, $this->mockZone());

        $this->assertCount(0, $unit->adjustments, 'No adjustment for zero tax');
    }

    #[Test]
    public function apply_taxes_resets_calculator_context_in_finally(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.83']);
        $config = $this->makeConfig();
        $cache = new OstaxCache(null, $config);
        $calc = new OstaxCalculator($client, $cache, $config, new NullLogger());
        $strategy = new OstaxTaxationStrategy($calc, $config, new NullLogger());

        $unit = new StubOrderItemUnit(total: 1000);
        $order = $this->mockOrder(units: [$unit]);

        $strategy->applyTaxes($order, $this->mockZone());

        // After applyTaxes, calculator's interface call should return 0
        // because context was reset.
        $rate = new \OpenSalesTax\Sylius\Tests\Support\StubTaxRate();
        $this->assertSame(0.0, $calc->calculate(1000.0, $rate));
    }

    #[Test]
    public function apply_taxes_strips_us_prefix_from_province_code(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.83']);
        $strategy = $this->makeStrategy($client, $this->makeConfig(nexusStates: ['MN']));

        $unit = new StubOrderItemUnit(total: 1000);
        $order = $this->mockOrder(units: [$unit], provinceCode: 'US-MN');

        $strategy->applyTaxes($order, $this->mockZone());

        // Adjustment was added → MN was correctly recognized as in-nexus
        $this->assertCount(1, $unit->adjustments);
    }

    #[Test]
    public function apply_taxes_falls_back_to_billing_address(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.83']);
        $strategy = $this->makeStrategy($client);

        $unit = new StubOrderItemUnit(total: 1000);
        $order = $this->mockOrder(units: [$unit], shippingAddress: false);

        $strategy->applyTaxes($order, $this->mockZone());

        $this->assertCount(1, $unit->adjustments);
    }

    /**
     * @param iterable<int, StubOrderItemUnit> $units
     */
    private function mockOrder(
        string $currency = 'USD',
        ?string $country = 'US',
        iterable $units = [],
        ?string $provinceCode = 'US-MN',
        bool|AddressInterface $shippingAddress = true,
    ): OrderInterface {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn($currency);

        $shippingAddr = $shippingAddress === true ? $this->mockAddress($country, $provinceCode) : ($shippingAddress instanceof AddressInterface ? $shippingAddress : null);
        $billingAddr = $shippingAddress === false ? $this->mockAddress($country, $provinceCode) : null;

        $order->method('getShippingAddress')->willReturn($shippingAddr);
        $order->method('getBillingAddress')->willReturn($billingAddr);

        // Build OrderItem(s) wrapping the units. Group all units under one
        // synthetic item — Sylius's contract is `getItems()` returns
        // OrderItemInterface[]; each item exposes `getUnits()`.
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getUnits')->willReturn(new ArrayCollection(is_array($units) ? $units : iterator_to_array($units)));

        $order->method('getItems')->willReturn(new ArrayCollection([$item]));

        return $order;
    }

    private function mockAddress(?string $country, ?string $provinceCode): AddressInterface
    {
        $addr = $this->createMock(AddressInterface::class);
        $addr->method('getCountryCode')->willReturn($country);
        $addr->method('getPostcode')->willReturn('55401');
        $addr->method('getProvinceCode')->willReturn($provinceCode);

        return $addr;
    }

    private function mockZone(): ZoneInterface
    {
        return $this->createMock(ZoneInterface::class);
    }

    /**
     * @param list<string>|null $nexusStates
     */
    private function makeConfig(?array $nexusStates = null): OstaxConfig
    {
        return new OstaxConfig(
            engineUrl: 'http://engine.local',
            apiKey: null,
            timeoutSeconds: 5.0,
            failHard: false,
            defaultCategory: 'general',
            nexusStates: $nexusStates ?? [],
            cacheTtlSeconds: 0,
        );
    }

    private function makeStrategy(FakeOstaxClient $client, ?OstaxConfig $config = null): OstaxTaxationStrategy
    {
        $cfg = $config ?? $this->makeConfig();
        $cache = new OstaxCache(null, $cfg);
        $calc = new OstaxCalculator($client, $cache, $cfg, new NullLogger());

        return new OstaxTaxationStrategy($calc, $cfg, new NullLogger());
    }
}
