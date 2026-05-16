<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Cache;

use OpenSalesTax\Sylius\Config\OstaxConfig;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Wraps Symfony's PSR-6 cache pool with an OST-specific key shape.
 *
 * Sylius bundles get a `cache.app` PSR-6 pool for free. We use it for short-
 * term tax-rate memoization keyed by (zip5, category, amount-cents). 60-min
 * default TTL keeps the engine load minimal at checkout while staying short
 * enough that rate updates propagate within the next hour.
 *
 * When the merchant has no PSR-6 pool wired up (rare — only in degenerate
 * unit-test contexts), `$pool` is null and every call is a cache miss. The
 * calculator continues to function; performance just degrades.
 */
final class OstaxCache
{
    public function __construct(
        private readonly ?CacheItemPoolInterface $pool,
        private readonly OstaxConfig $config,
    ) {
    }

    /**
     * Returns the cached tax total (as a decimal string) or null on miss /
     * disabled cache. The cached payload is the engine's `tax_total` field —
     * sufficient for line-tax assertion; the per-jurisdiction breakdown is
     * NOT cached (different consumers, different invalidation cycles).
     */
    public function get(string $zip5, string $category, int $cents): ?string
    {
        if ($this->pool === null || !$this->config->cacheEnabled()) {
            return null;
        }

        $item = $this->pool->getItem(self::key($zip5, $category, $cents));
        if (!$item->isHit()) {
            return null;
        }
        $value = $item->get();

        return is_string($value) ? $value : null;
    }

    public function set(string $zip5, string $category, int $cents, string $taxTotal): void
    {
        if ($this->pool === null || !$this->config->cacheEnabled()) {
            return;
        }

        $item = $this->pool->getItem(self::key($zip5, $category, $cents));
        $item->set($taxTotal);
        $item->expiresAfter($this->config->cacheTtlSeconds);
        $this->pool->save($item);
    }

    /**
     * PSR-6 key spec forbids `{}()/\@:` in keys. Cents → string concat is
     * fine; ZIP and category are constrained to digits / lowercase a-z.
     */
    private static function key(string $zip5, string $category, int $cents): string
    {
        return 'ostax.calc.' . $zip5 . '.' . $category . '.' . $cents;
    }
}
