<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Tests\Support;

use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;

/**
 * Stub `TaxRateInterface` for tests. Just enough surface to satisfy the
 * `OstaxCalculator::calculate(float, TaxRateInterface)` signature.
 *
 * Signatures match Sylius v1.14's interface (nullable setters everywhere).
 */
final class StubTaxRate implements TaxRateInterface
{
    public function getId(): int
    {
        return 1;
    }

    public function getCode(): ?string
    {
        return 'stub';
    }

    public function setCode(?string $code): void
    {
    }

    public function getName(): ?string
    {
        return 'Stub';
    }

    public function setName(?string $name): void
    {
    }

    public function getAmount(): float
    {
        return 0.0;
    }

    public function setAmount(?float $amount): void
    {
    }

    public function getAmountAsPercentage(): float
    {
        return 0.0;
    }

    public function isIncludedInPrice(): bool
    {
        return false;
    }

    public function setIncludedInPrice(?bool $includedInPrice): void
    {
    }

    public function getCategory(): ?TaxCategoryInterface
    {
        return null;
    }

    public function setCategory(?TaxCategoryInterface $category): void
    {
    }

    public function getCalculator(): ?string
    {
        return 'ostax';
    }

    public function setCalculator(?string $calculator): void
    {
    }

    public function getLabel(): ?string
    {
        return 'Stub';
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return null;
    }

    public function setStartDate(?\DateTimeInterface $startDate): void
    {
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return null;
    }

    public function setEndDate(?\DateTimeInterface $endDate): void
    {
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return null;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): void
    {
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return null;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): void
    {
    }
}
