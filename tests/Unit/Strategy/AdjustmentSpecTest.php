<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Tests\Unit\Strategy;

use OpenSalesTax\Sylius\Strategy\AdjustmentSpec;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AdjustmentSpecTest extends TestCase
{
    #[Test]
    public function tax_factory_sets_type_to_tax(): void
    {
        $adj = AdjustmentSpec::tax('OpenSalesTax', 83);

        $this->assertSame(AdjustmentSpec::TAX_ADJUSTMENT, $adj->getType());
        $this->assertSame('tax', $adj->getType());
        $this->assertSame('OpenSalesTax', $adj->getLabel());
        $this->assertSame(83, $adj->getAmount());
        $this->assertFalse($adj->isNeutral());
    }

    #[Test]
    public function direct_construction_carries_all_fields(): void
    {
        $adj = new AdjustmentSpec(type: 'shipping', label: 'Free shipping', amount: -500, neutral: true);

        $this->assertSame('shipping', $adj->getType());
        $this->assertSame('Free shipping', $adj->getLabel());
        $this->assertSame(-500, $adj->getAmount());
        $this->assertTrue($adj->isNeutral());
    }

    #[Test]
    public function fields_are_readonly(): void
    {
        $adj = AdjustmentSpec::tax('OpenSalesTax', 83);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — intentional readonly write
        $adj->amount = 999;
    }
}
