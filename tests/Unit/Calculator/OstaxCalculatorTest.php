<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Tests\Unit\Calculator;

use OpenSalesTax\Exceptions\OpenSalesTaxNetworkException;
use OpenSalesTax\Sylius\Cache\OstaxCache;
use OpenSalesTax\Sylius\Calculator\OstaxCalculator;
use OpenSalesTax\Sylius\Config\OstaxConfig;
use OpenSalesTax\Sylius\Tests\Support\FakeOstaxClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class OstaxCalculatorTest extends TestCase
{
    #[Test]
    public function returns_zero_when_no_context_set(): void
    {
        $calc = $this->makeCalculator();
        $this->assertSame(0.0, $calc->calculateForContext(1234.0, null));
    }

    #[Test]
    public function returns_zero_for_zero_or_negative_base(): void
    {
        $calc = $this->makeCalculator();
        $ctx = $this->validContext();

        $this->assertSame(0.0, $calc->calculateForContext(0.0, $ctx));
        $this->assertSame(0.0, $calc->calculateForContext(-100.0, $ctx));
    }

    #[Test]
    public function returns_zero_for_non_usd_currency(): void
    {
        $calc = $this->makeCalculator();
        $ctx = $this->validContext();
        $ctx['currency'] = 'EUR';

        $this->assertSame(0.0, $calc->calculateForContext(1234.0, $ctx));
    }

    #[Test]
    public function returns_zero_for_non_us_country(): void
    {
        $calc = $this->makeCalculator();
        $ctx = $this->validContext();
        $ctx['country'] = 'CA';

        $this->assertSame(0.0, $calc->calculateForContext(1234.0, $ctx));
    }

    #[Test]
    public function returns_zero_for_invalid_zip(): void
    {
        $calc = $this->makeCalculator();
        $ctx = $this->validContext();

        $ctx['zip5'] = '123';
        $this->assertSame(0.0, $calc->calculateForContext(1234.0, $ctx));

        $ctx['zip5'] = 'ABCDE';
        $this->assertSame(0.0, $calc->calculateForContext(1234.0, $ctx));

        $ctx['zip5'] = '';
        $this->assertSame(0.0, $calc->calculateForContext(1234.0, $ctx));
    }

    #[Test]
    public function returns_zero_when_state_outside_nexus_filter(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.83']);
        $calc = $this->makeCalculator(client: $client, config: $this->makeConfig(nexusStates: ['MN', 'WI']));

        $ctx = $this->validContext();
        $ctx['state'] = 'CA';

        $this->assertSame(0.0, $calc->calculateForContext(1234.0, $ctx));
        $this->assertSame(0, $client->callCount, 'Engine must not be called when state is outside nexus filter');
    }

    #[Test]
    public function returns_zero_when_state_unknown_with_nexus_filter_enabled(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.83']);
        $calc = $this->makeCalculator(client: $client, config: $this->makeConfig(nexusStates: ['MN']));

        $ctx = $this->validContext();
        $ctx['state'] = '';

        $this->assertSame(0.0, $calc->calculateForContext(1234.0, $ctx));
        $this->assertSame(0, $client->callCount);
    }

    #[Test]
    public function happy_path_returns_engine_tax_in_cents(): void
    {
        // Engine returns "0.83" → 83 cents.
        $client = new FakeOstaxClient(['tax_total' => '0.83']);
        $calc = $this->makeCalculator(client: $client);

        $tax = $calc->calculateForContext(1000.0, $this->validContext());

        $this->assertSame(83.0, $tax);
        $this->assertSame(1, $client->callCount);
        $this->assertSame('55401', $client->lastCall['zip5']);
        $this->assertSame('10.00', $client->lastCall['amount']);
        $this->assertSame('general', $client->lastCall['category']);
    }

    #[Test]
    public function nexus_filter_passes_when_state_in_allowlist(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.83']);
        $calc = $this->makeCalculator(client: $client, config: $this->makeConfig(nexusStates: ['MN', 'WI']));

        $ctx = $this->validContext();
        $ctx['state'] = 'MN';

        $this->assertSame(83.0, $calc->calculateForContext(1000.0, $ctx));
        $this->assertSame(1, $client->callCount);
    }

    #[Test]
    public function uses_default_category_when_context_category_is_empty(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.50']);
        $calc = $this->makeCalculator(
            client: $client,
            config: $this->makeConfig(defaultCategory: 'clothing'),
        );

        $ctx = $this->validContext();
        $ctx['category'] = '';

        $calc->calculateForContext(1000.0, $ctx);
        $this->assertSame('clothing', $client->lastCall['category']);
    }

    #[Test]
    public function explicit_category_overrides_default(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.10']);
        $calc = $this->makeCalculator(
            client: $client,
            config: $this->makeConfig(defaultCategory: 'general'),
        );

        $ctx = $this->validContext();
        $ctx['category'] = 'groceries';

        $calc->calculateForContext(500.0, $ctx);
        $this->assertSame('groceries', $client->lastCall['category']);
    }

    #[Test]
    public function fail_soft_returns_zero_on_engine_error(): void
    {
        $client = new FakeOstaxClient([], throw: new OpenSalesTaxNetworkException('connection refused'));
        $calc = $this->makeCalculator(client: $client, config: $this->makeConfig(failHard: false));

        $this->assertSame(0.0, $calc->calculateForContext(1000.0, $this->validContext()));
    }

    #[Test]
    public function fail_hard_rethrows_engine_error(): void
    {
        $client = new FakeOstaxClient([], throw: new OpenSalesTaxNetworkException('connection refused'));
        $calc = $this->makeCalculator(client: $client, config: $this->makeConfig(failHard: true));

        $this->expectException(OpenSalesTaxNetworkException::class);
        $calc->calculateForContext(1000.0, $this->validContext());
    }

    #[Test]
    public function fail_soft_swallows_unexpected_exceptions(): void
    {
        $client = new FakeOstaxClient([], throw: new \RuntimeException('something exploded'));
        $calc = $this->makeCalculator(client: $client, config: $this->makeConfig(failHard: false));

        $this->assertSame(0.0, $calc->calculateForContext(1000.0, $this->validContext()));
    }

    #[Test]
    public function uses_cache_on_second_call_with_same_key(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.83']);
        // Build a calculator with cache enabled (TTL > 0) and an in-memory
        // PSR-6 pool so the second call hits the cache.
        $cfg = new OstaxConfig(
            engineUrl: 'http://engine.local',
            apiKey: null,
            timeoutSeconds: 5.0,
            failHard: false,
            defaultCategory: 'general',
            nexusStates: [],
            cacheTtlSeconds: 3600,
        );
        $cache = new OstaxCache($this->makeInMemoryPool(), $cfg);
        $calc = new OstaxCalculator($client, $cache, $cfg, new NullLogger());

        $calc->calculateForContext(1000.0, $this->validContext());
        $calc->calculateForContext(1000.0, $this->validContext());

        $this->assertSame(1, $client->callCount, 'Engine must be called only once for identical inputs');
    }

    private function makeInMemoryPool(): \Psr\Cache\CacheItemPoolInterface
    {
        return new class () implements \Psr\Cache\CacheItemPoolInterface {
            /** @var array<string, mixed> */
            private array $store = [];

            public function getItem(string $key): \Psr\Cache\CacheItemInterface
            {
                $store = &$this->store;
                $hit = array_key_exists($key, $store);
                $value = $store[$key] ?? null;

                return new class ($key, $value, $hit, $store) implements \Psr\Cache\CacheItemInterface {
                    /** @param array<string, mixed> $store */
                    public function __construct(private readonly string $key, private mixed $value, private bool $hit, private array &$store)
                    {
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

            public function save(\Psr\Cache\CacheItemInterface $item): bool
            {
                $this->store[$item->getKey()] = $item->get();
                return true;
            }

            public function saveDeferred(\Psr\Cache\CacheItemInterface $item): bool
            {
                return $this->save($item);
            }

            public function commit(): bool
            {
                return true;
            }
        };
    }

    #[Test]
    public function set_order_context_affects_interface_calculate(): void
    {
        $client = new FakeOstaxClient(['tax_total' => '0.83']);
        $calc = $this->makeCalculator(client: $client);

        // No context set → 0
        $rate = new \OpenSalesTax\Sylius\Tests\Support\StubTaxRate();
        $this->assertSame(0.0, $calc->calculate(1000.0, $rate));

        // Prime via setOrderContext, then call interface method
        $calc->setOrderContext($this->validContext());
        $this->assertSame(83.0, $calc->calculate(1000.0, $rate));

        // Reset to null → 0
        $calc->setOrderContext(null);
        $this->assertSame(0.0, $calc->calculate(1000.0, $rate));
    }

    /**
     * @param list<string>|null $nexusStates
     */
    private function makeConfig(
        bool $failHard = false,
        string $defaultCategory = 'general',
        ?array $nexusStates = null,
    ): OstaxConfig {
        return new OstaxConfig(
            engineUrl: 'http://engine.local',
            apiKey: null,
            timeoutSeconds: 5.0,
            failHard: $failHard,
            defaultCategory: $defaultCategory,
            nexusStates: $nexusStates ?? [],
            cacheTtlSeconds: 0, // disable cache by default in calc tests
        );
    }

    private function makeCalculator(?FakeOstaxClient $client = null, ?OstaxConfig $config = null): OstaxCalculator
    {
        $cfg = $config ?? $this->makeConfig();
        $cli = $client ?? new FakeOstaxClient(['tax_total' => '0.00']);
        $cache = new OstaxCache(null, $cfg);

        return new OstaxCalculator(
            client: $cli,
            cache: $cache,
            config: $cfg,
            logger: new NullLogger(),
        );
    }

    /**
     * @return array{zip5: string, state: string, currency: string, country: string, category: string}
     */
    private function validContext(): array
    {
        return [
            'zip5' => '55401',
            'state' => 'MN',
            'currency' => 'USD',
            'country' => 'US',
            'category' => 'general',
        ];
    }
}
