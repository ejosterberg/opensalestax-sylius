<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Calculator;

use OpenSalesTax\Exceptions\OpenSalesTaxException;
use OpenSalesTax\Sylius\Cache\OstaxCache;
use OpenSalesTax\Sylius\Client\OstaxClient;
use OpenSalesTax\Sylius\Config\OstaxConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;

/**
 * Sylius `CalculatorInterface` implementation that defers to the
 * OpenSalesTax engine.
 *
 * Sylius's calculator contract is intentionally narrow:
 *   `calculate(float $base, TaxRateInterface $rate): float`
 *
 * We need additional context (destination ZIP, OST category) to call the
 * engine. That context flows in through the calling
 * `OstaxTaxationStrategy`, which sets per-call context via
 * {@see self::setOrderContext()} before invoking `calculate()`. This
 * threading-the-needle exists because Sylius's own `DefaultCalculator` is
 * also context-free (it just multiplies amount by rate); we keep the
 * interface contract intact.
 *
 * Constitution gates §5 / §8 are enforced inside `calculate()`. When any
 * gate trips (non-USD, non-US, no nexus, etc.), the calculator returns
 * `0.0` so Sylius's own pipeline takes over.
 *
 * Money convention: Sylius passes the line base in **cents** (per
 * `OrderItemUnit::$total`). We forward to the engine as a decimal-dollar
 * string and return the engine's response converted back to cents (the
 * unit Sylius's own calculators return).
 */
final class OstaxCalculator implements CalculatorInterface
{
    private const SUPPORTED_CURRENCY = 'USD';
    private const SUPPORTED_COUNTRY = 'US';
    private const ZIP_REGEX = '/^\d{5}(-\d{4})?$/';

    private LoggerInterface $logger;

    /** @var array{zip5?: string, state?: string, currency?: string, country?: string, category?: string}|null */
    private ?array $orderContext = null;

    public function __construct(
        private readonly OstaxClient $client,
        private readonly OstaxCache $cache,
        private readonly OstaxConfig $config,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Sets per-order context before `calculate()` fires for each line.
     * Called by `OstaxTaxationStrategy::applyTaxes()`.
     *
     * Resetting (passing null) returns the calculator to a "no context"
     * state — subsequent `calculate()` calls return 0.0 since we cannot
     * make safe engine calls without a destination.
     *
     * @param array{zip5?: string, state?: string, currency?: string, country?: string, category?: string}|null $context
     */
    public function setOrderContext(?array $context): void
    {
        $this->orderContext = $context;
    }

    /**
     * Sylius `CalculatorInterface::calculate()` — returns the tax amount
     * for `$base` (in cents) at the configured `$rate`.
     *
     * Behavior:
     *   - No context set → 0.0 (strategy didn't prime us; safe default)
     *   - Non-USD currency → 0.0 (constitution §5)
     *   - Non-US country → 0.0 (constitution §5)
     *   - Invalid ZIP → 0.0
     *   - Out-of-nexus state → 0.0
     *   - Engine error + fail-soft → 0.0 + log warning (constitution §8)
     *   - Engine error + fail-hard → re-throw (`OpenSalesTaxException`)
     *
     * The `$rate` argument is intentionally ignored — the engine resolves
     * the rate from the destination ZIP. Sylius still passes its
     * configured rate (typically 0%) so the calculator signature
     * matches; we just don't use it.
     */
    public function calculate(float $base, TaxRateInterface $rate): float
    {
        unset($rate); // engine resolves rate from ZIP

        return $this->calculateForContext($base, $this->orderContext);
    }

    /**
     * Strategy-facing calculation entrypoint. Identical semantics to the
     * Sylius interface method but takes the context inline (no setter dance).
     * Used by `OstaxTaxationStrategy::applyTaxes()` to keep the strategy's
     * loop free of stateful priming.
     *
     * @param array{zip5?: string, state?: string, currency?: string, country?: string, category?: string}|null $ctx
     */
    public function calculateForContext(float $base, ?array $ctx): float
    {
        if ($base <= 0.0) {
            return 0.0;
        }
        if ($ctx === null) {
            return 0.0;
        }

        // Gate 1: USD only
        if (($ctx['currency'] ?? null) !== self::SUPPORTED_CURRENCY) {
            return 0.0;
        }

        // Gate 2: US ship-to only
        if (($ctx['country'] ?? null) !== self::SUPPORTED_COUNTRY) {
            return 0.0;
        }

        // Gate 3: ZIP must be present and valid
        $zipRaw = (string) ($ctx['zip5'] ?? '');
        if (preg_match(self::ZIP_REGEX, $zipRaw) !== 1) {
            return 0.0;
        }
        $zip5 = substr($zipRaw, 0, 5);

        // Gate 4: per-state nexus filter. When a filter is configured and
        // the destination state isn't on it, skip the engine call entirely.
        // An empty/missing state with the filter ENABLED is fail-closed
        // (out of nexus) — matches the WooCom v0.5.0 sibling pattern.
        $state = (string) ($ctx['state'] ?? '');
        if ($this->config->nexusFilterEnabled() && !$this->config->stateInNexus($state)) {
            return 0.0;
        }

        $category = (string) ($ctx['category'] ?? $this->config->defaultCategory);
        if ($category === '') {
            $category = $this->config->defaultCategory;
        }

        $cents = (int) round($base);
        $cached = $this->cache->get($zip5, $category, $cents);
        if ($cached !== null) {
            return self::dollarsToCents($cached);
        }

        $amount = self::centsToDecimalString($cents);
        try {
            $response = $this->client->calculate($zip5, $amount, $category);
        } catch (OpenSalesTaxException $e) {
            return $this->handleError($e);
        } catch (\Throwable $e) {
            // Defensive — anything not derived from OpenSalesTaxException is
            // unexpected and points at a programming or config bug, not a
            // transient engine error. Treat the same as engine errors so
            // checkout never breaks.
            return $this->handleError($e);
        }

        $taxTotal = $response->taxTotal;
        $this->cache->set($zip5, $category, $cents, $taxTotal);

        return self::dollarsToCents($taxTotal);
    }

    private function handleError(\Throwable $e): float
    {
        $this->logger->warning(
            sprintf('OpenSalesTax engine error: %s: %s', $e::class, $e->getMessage()),
            ['exception' => $e],
        );

        if ($this->config->failHard) {
            throw $e instanceof \Exception ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        }

        return 0.0;
    }

    private static function centsToDecimalString(int $cents): string
    {
        $abs = abs($cents);
        $dollars = intdiv($abs, 100);
        $remainder = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
        $sign = $cents < 0 ? '-' : '';

        return $sign . $dollars . '.' . $remainder;
    }

    private static function dollarsToCents(string $decimalDollars): float
    {
        // The engine returns decimal strings like "0.83". Multiply by 100 +
        // round to the nearest cent (banker's rounding would be weirder).
        return round((float) $decimalDollars * 100.0);
    }
}
