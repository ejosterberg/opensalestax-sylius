<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Tests\Unit\DependencyInjection;

use OpenSalesTax\Sylius\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    #[Test]
    public function defaults_apply_when_only_engine_url_set(): void
    {
        $processed = $this->process([['engine_url' => 'http://engine.local']]);

        $this->assertSame('http://engine.local', $processed['engine_url']);
        $this->assertNull($processed['api_key']);
        $this->assertSame(5.0, $processed['timeout_seconds']);
        $this->assertFalse($processed['fail_hard']);
        $this->assertSame('general', $processed['default_category']);
        $this->assertSame([], $processed['nexus_states']);
        $this->assertSame(3600, $processed['cache_ttl_seconds']);
    }

    #[Test]
    public function rejects_missing_engine_url(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([[]]);
    }

    #[Test]
    public function rejects_unknown_default_category(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([[
            'engine_url' => 'http://engine.local',
            'default_category' => 'snake_oil',
        ]]);
    }

    #[Test]
    public function rejects_negative_timeout(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([[
            'engine_url' => 'http://engine.local',
            'timeout_seconds' => -1.0,
        ]]);
    }

    #[Test]
    public function rejects_negative_cache_ttl(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([[
            'engine_url' => 'http://engine.local',
            'cache_ttl_seconds' => -1,
        ]]);
    }

    #[Test]
    public function accepts_full_config_block(): void
    {
        $processed = $this->process([[
            'engine_url' => 'https://engine.example.com',
            'api_key' => 'secret',
            'timeout_seconds' => 10.0,
            'fail_hard' => true,
            'default_category' => 'clothing',
            'nexus_states' => ['MN', 'WI', 'IA'],
            'cache_ttl_seconds' => 7200,
        ]]);

        $this->assertSame('https://engine.example.com', $processed['engine_url']);
        $this->assertSame('secret', $processed['api_key']);
        $this->assertSame(10.0, $processed['timeout_seconds']);
        $this->assertTrue($processed['fail_hard']);
        $this->assertSame('clothing', $processed['default_category']);
        $this->assertSame(['MN', 'WI', 'IA'], $processed['nexus_states']);
        $this->assertSame(7200, $processed['cache_ttl_seconds']);
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        $tree = (new Configuration())->getConfigTreeBuilder()->buildTree();
        $processor = new Processor();

        return $processor->process($tree, $configs);
    }
}
