<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Tests\Support;

final class StubOrderItemUnit
{
    /** @var list<object> */
    public array $adjustments = [];

    public function __construct(
        public readonly int $total,
    ) {
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getAdjustedTotal(string $type): int
    {
        // No promotion adjustments in stubs — return base total.
        return $this->total;
    }

    public function addAdjustment(object $adjustment): void
    {
        $this->adjustments[] = $adjustment;
    }
}
