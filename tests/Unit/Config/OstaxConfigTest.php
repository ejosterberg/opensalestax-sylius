<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Tests\Unit\Config;

use OpenSalesTax\Sylius\Config\OstaxConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OstaxConfigTest extends TestCase
{
    #[Test]
    public function happy_path_freezes_all_fields(): void
    {
        $cfg = new OstaxConfig(
            engineUrl: 'http://10.32.161.126:8080',
            apiKey: 'secret-key',
            timeoutSeconds: 5.0,
            failHard: false,
            defaultCategory: 'general',
            nexusStates: ['MN', 'WI'],
            cacheTtlSeconds: 3600,
        );

        $this->assertSame('http://10.32.161.126:8080', $cfg->engineUrl);
        $this->assertSame('secret-key', $cfg->apiKey);
        $this->assertSame(5.0, $cfg->timeoutSeconds);
        $this->assertFalse($cfg->failHard);
        $this->assertSame('general', $cfg->defaultCategory);
        $this->assertSame(['MN', 'WI'], $cfg->nexusStates);
        $this->assertSame(3600, $cfg->cacheTtlSeconds);
    }

    #[Test]
    public function trims_trailing_slash_from_engine_url(): void
    {
        $cfg = $this->makeConfig(engineUrl: 'http://engine.local:8080/');
        $this->assertSame('http://engine.local:8080', $cfg->engineUrl);
    }

    #[Test]
    public function api_key_empty_string_normalizes_to_null(): void
    {
        $cfg = $this->makeConfig(apiKey: '');
        $this->assertNull($cfg->apiKey);
    }

    #[Test]
    public function nexus_states_uppercase_and_dedupe(): void
    {
        $cfg = $this->makeConfig(nexusStates: ['mn', 'WI', 'mn', ' ia ']);
        $this->assertSame(['MN', 'WI', 'IA'], $cfg->nexusStates);
    }

    #[Test]
    public function nexus_filter_disabled_when_states_empty(): void
    {
        $cfg = $this->makeConfig(nexusStates: []);
        $this->assertFalse($cfg->nexusFilterEnabled());
        $this->assertTrue($cfg->stateInNexus('MN'));
        $this->assertTrue($cfg->stateInNexus('CA'));
    }

    #[Test]
    public function nexus_filter_enforces_allowlist(): void
    {
        $cfg = $this->makeConfig(nexusStates: ['MN', 'WI']);
        $this->assertTrue($cfg->nexusFilterEnabled());
        $this->assertTrue($cfg->stateInNexus('MN'));
        $this->assertTrue($cfg->stateInNexus('mn'));
        $this->assertFalse($cfg->stateInNexus('CA'));
    }

    #[Test]
    public function cache_enabled_when_ttl_positive(): void
    {
        $this->assertTrue($this->makeConfig(cacheTtlSeconds: 60)->cacheEnabled());
        $this->assertFalse($this->makeConfig(cacheTtlSeconds: 0)->cacheEnabled());
    }

    #[Test]
    public function rejects_empty_engine_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('engine_url is required');
        $this->makeConfig(engineUrl: '   ');
    }

    /** @return iterable<string, array{string}> */
    public static function badUrls(): iterable
    {
        yield 'no scheme'       => ['10.32.161.126:8080'];
        yield 'ftp scheme'      => ['ftp://engine.local'];
        yield 'file scheme'     => ['file:///etc/passwd'];
        yield 'totally garbage' => ['not a url at all'];
    }

    #[Test]
    #[DataProvider('badUrls')]
    public function rejects_bad_engine_url(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->makeConfig(engineUrl: $url);
    }

    #[Test]
    public function rejects_zero_timeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('timeout_seconds must be > 0');
        $this->makeConfig(timeoutSeconds: 0.0);
    }

    #[Test]
    public function rejects_negative_timeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->makeConfig(timeoutSeconds: -1.5);
    }

    #[Test]
    public function rejects_unknown_default_category(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('default_category "snake_oil"');
        $this->makeConfig(defaultCategory: 'snake_oil');
    }

    #[Test]
    public function accepts_all_valid_categories(): void
    {
        foreach (['general', 'clothing', 'groceries', 'prescription_drugs', 'prepared_food', 'digital_goods'] as $cat) {
            $cfg = $this->makeConfig(defaultCategory: $cat);
            $this->assertSame($cat, $cfg->defaultCategory);
        }
    }

    #[Test]
    public function rejects_invalid_state_codes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid state code');
        $this->makeConfig(nexusStates: ['MN', 'XYZ', 'CA']);
    }

    #[Test]
    public function rejects_negative_cache_ttl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cache_ttl_seconds must be >= 0');
        $this->makeConfig(cacheTtlSeconds: -1);
    }

    /**
     * @param list<string>|null $nexusStates
     */
    private function makeConfig(
        string $engineUrl = 'http://engine.local:8080',
        ?string $apiKey = null,
        float $timeoutSeconds = 5.0,
        bool $failHard = false,
        string $defaultCategory = 'general',
        ?array $nexusStates = null,
        int $cacheTtlSeconds = 3600,
    ): OstaxConfig {
        return new OstaxConfig(
            engineUrl: $engineUrl,
            apiKey: $apiKey,
            timeoutSeconds: $timeoutSeconds,
            failHard: $failHard,
            defaultCategory: $defaultCategory,
            nexusStates: $nexusStates ?? [],
            cacheTtlSeconds: $cacheTtlSeconds,
        );
    }
}
