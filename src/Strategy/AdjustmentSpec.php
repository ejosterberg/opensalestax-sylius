<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Strategy;

/**
 * Lightweight value-object passed to `AdjustableInterface::addAdjustment()`
 * when this bundle is loaded WITHOUT Sylius's own `AdjustmentFactory`
 * available.
 *
 * This stays intentionally decoupled from `Sylius\Component\Order\Model\AdjustmentInterface`
 * so the bundle can be consumed without a hard `sylius/sylius` dep at composer-
 * autoload time. When Sylius IS available, the merchant should override the
 * `opensalestax_sylius.calculator.ostax` service to inject a real
 * `AdjustmentFactoryInterface` and produce real adjustments via Sylius's
 * own model. (See `docs/full-sylius-integration.md` for the merchant-side
 * recipe — added in v0.2.)
 *
 * Until then, this object presents the minimal duck-typed shape Sylius's
 * built-in adjustment-aware code paths read from: `getType()`, `getAmount()`,
 * `getLabel()`, `isNeutral()`. Sylius's `AdjustableInterface::addAdjustment()`
 * stores the object as-is on the in-memory unit; persistence to the database
 * still requires a real `AdjustmentInterface` instance, which is why the v1.0
 * merchant-facing docs flag the AdjustmentFactory-injection step.
 */
final class AdjustmentSpec
{
    public const TAX_ADJUSTMENT = 'tax';

    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly int $amount,
        public readonly bool $neutral = false,
    ) {
    }

    public static function tax(string $label, int $amount): self
    {
        return new self(self::TAX_ADJUSTMENT, $label, $amount);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function isNeutral(): bool
    {
        return $this->neutral;
    }
}
