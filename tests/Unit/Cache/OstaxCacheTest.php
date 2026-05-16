<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Tests\Unit\Cache;

use OpenSalesTax\Sylius\Cache\OstaxCache;
use OpenSalesTax\Sylius\Config\OstaxConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class OstaxCacheTest extends TestCase
{
    #[Test]
    public function returns_null_when_pool_is_null(): void
    {
        $cache = new OstaxCache(null, $this->config(ttl: 3600));
        $this->assertNull($cache->get('55401', 'general', 1234));
    }

    #[Test]
    public function returns_null_when_cache_disabled_via_zero_ttl(): void
    {
        $pool = $this->makePool();
        $cache = new OstaxCache($pool, $this->config(ttl: 0));
        $cache->set('55401', 'general', 1234, '0.83');
        $this->assertNull($cache->get('55401', 'general', 1234));
    }

    #[Test]
    public function set_then_get_round_trip(): void
    {
        $pool = $this->makePool();
        $cache = new OstaxCache($pool, $this->config(ttl: 3600));

        $this->assertNull($cache->get('55401', 'general', 1234));
        $cache->set('55401', 'general', 1234, '0.83');
        $this->assertSame('0.83', $cache->get('55401', 'general', 1234));
    }

    #[Test]
    public function different_keys_do_not_collide(): void
    {
        $pool = $this->makePool();
        $cache = new OstaxCache($pool, $this->config(ttl: 3600));

        $cache->set('55401', 'general', 1234, '0.83');
        $cache->set('55401', 'clothing', 1234, '0.00');
        $cache->set('55402', 'general', 1234, '0.85');
        $cache->set('55401', 'general', 1235, '0.84');

        $this->assertSame('0.83', $cache->get('55401', 'general', 1234));
        $this->assertSame('0.00', $cache->get('55401', 'clothing', 1234));
        $this->assertSame('0.85', $cache->get('55402', 'general', 1234));
        $this->assertSame('0.84', $cache->get('55401', 'general', 1235));
    }

    #[Test]
    public function get_ignores_non_string_cached_value(): void
    {
        // Defensive — if some other system writes garbage to the same pool,
        // return null rather than crashing the calculator.
        $pool = new class () implements CacheItemPoolInterface {
            public function getItem(string $key): CacheItemInterface
            {
                return new class () implements CacheItemInterface {
                    public function getKey(): string
                    {
                        return 'k';
                    }

                    public function get(): mixed
                    {
                        return ['not a string'];
                    }

                    public function isHit(): bool
                    {
                        return true;
                    }

                    public function set(mixed $value): static
                    {
                        return $this;
                    }

                    public function expiresAt(?\DateTimeInterface $expiration): static
                    {
                        return $this;
                    }

                    public function expiresAfter(\DateInterval|int|null $time): static
                    {
                        return $this;
                    }
                };
            }

            public function getItems(array $keys = []): iterable
            {
                return [];
            }

            public function hasItem(string $key): bool
            {
                return true;
            }

            public function clear(): bool
            {
                return true;
            }

            public function deleteItem(string $key): bool
            {
                return true;
            }

            public function deleteItems(array $keys): bool
            {
                return true;
            }

            public function save(CacheItemInterface $item): bool
            {
                return true;
            }

            public function saveDeferred(CacheItemInterface $item): bool
            {
                return true;
            }

            public function commit(): bool
            {
                return true;
            }
        };

        $cache = new OstaxCache($pool, $this->config(ttl: 3600));
        $this->assertNull($cache->get('55401', 'general', 1234));
    }

    private function config(int $ttl): OstaxConfig
    {
        return new OstaxConfig(
            engineUrl: 'http://engine.local',
            apiKey: null,
            timeoutSeconds: 5.0,
            failHard: false,
            defaultCategory: 'general',
            nexusStates: [],
            cacheTtlSeconds: $ttl,
        );
    }

    /**
     * Tiny in-memory PSR-6 pool. Just enough for round-trip tests.
     */
    private function makePool(): CacheItemPoolInterface
    {
        return new class () implements CacheItemPoolInterface {
            /** @var array<string, mixed> */
            private array $store = [];

            public function getItem(string $key): CacheItemInterface
            {
                $store = &$this->store;
                $hit = array_key_exists($key, $store);
                $value = $store[$key] ?? null;

                return new class ($key, $value, $hit, $store) implements CacheItemInterface {
                    /** @param array<string, mixed> $store */
                    public function __construct(
                        private readonly string $key,
                        private mixed $value,
                        private bool $hit,
                        private array &$store,
                    ) {
                    }

                    public function getKey(): string
                    {
                        return $this->key;
                    }

                    public function get(): mixed
                    {
                        return $this->value;
                    }

                    public function isHit(): bool
                    {
                        return $this->hit;
                    }

                    public function set(mixed $value): static
                    {
                        $this->value = $value;
                        $this->hit = true;
                        $this->store[$this->key] = $value;
                        return $this;
                    }

                    public function expiresAt(?\DateTimeInterface $expiration): static
                    {
                        return $this;
                    }

                    public function expiresAfter(\DateInterval|int|null $time): static
                    {
                        return $this;
                    }
                };
            }

            public function getItems(array $keys = []): iterable
            {
                $out = [];
                foreach ($keys as $k) {
                    $out[$k] = $this->getItem($k);
                }
                return $out;
            }

            public function hasItem(string $key): bool
            {
                return array_key_exists($key, $this->store);
            }

            public function clear(): bool
            {
                $this->store = [];
                return true;
            }

            public function deleteItem(string $key): bool
            {
                unset($this->store[$key]);
                return true;
            }

            public function deleteItems(array $keys): bool
            {
                foreach ($keys as $k) {
                    unset($this->store[$k]);
                }
                return true;
            }

            public function save(CacheItemInterface $item): bool
            {
                $this->store[$item->getKey()] = $item->get();
                return true;
            }

            public function saveDeferred(CacheItemInterface $item): bool
            {
                return $this->save($item);
            }

            public function commit(): bool
            {
                return true;
            }
        };
    }
}
