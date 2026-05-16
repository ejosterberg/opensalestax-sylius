<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Tests\Unit\DependencyInjection;

use OpenSalesTax\Sylius\Cache\OstaxCache;
use OpenSalesTax\Sylius\Calculator\OstaxCalculator;
use OpenSalesTax\Sylius\Client\OstaxClient;
use OpenSalesTax\Sylius\Config\OstaxConfig;
use OpenSalesTax\Sylius\DependencyInjection\OpenSalesTaxSyliusExtension;
use OpenSalesTax\Sylius\Strategy\OstaxTaxationStrategy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class OpenSalesTaxSyliusExtensionTest extends TestCase
{
    #[Test]
    public function alias_is_opensalestax_sylius(): void
    {
        $this->assertSame('opensalestax_sylius', (new OpenSalesTaxSyliusExtension())->getAlias());
    }

    #[Test]
    public function load_registers_all_five_services(): void
    {
        $container = $this->buildContainer();

        $this->assertTrue($container->hasDefinition('opensalestax_sylius.config'));
        $this->assertTrue($container->hasDefinition('opensalestax_sylius.client'));
        $this->assertTrue($container->hasDefinition('opensalestax_sylius.cache'));
        $this->assertTrue($container->hasDefinition('opensalestax_sylius.calculator.ostax'));
        $this->assertTrue($container->hasDefinition('opensalestax_sylius.strategy.ostax'));
    }

    #[Test]
    public function calculator_is_tagged_for_sylius_registry(): void
    {
        $container = $this->buildContainer();
        $tags = $container->getDefinition('opensalestax_sylius.calculator.ostax')->getTags();

        $this->assertArrayHasKey('sylius.tax_calculator', $tags);
        $this->assertSame('ostax', $tags['sylius.tax_calculator'][0]['calculator']);
    }

    #[Test]
    public function strategy_is_tagged_for_sylius_registry(): void
    {
        $container = $this->buildContainer();
        $tags = $container->getDefinition('opensalestax_sylius.strategy.ostax')->getTags();

        $this->assertArrayHasKey('sylius.taxation_strategy', $tags);
        $this->assertSame('ostax', $tags['sylius.taxation_strategy'][0]['type']);
    }

    #[Test]
    public function config_class_receives_all_seven_arguments(): void
    {
        $container = $this->buildContainer([
            'opensalestax_sylius' => [
                'engine_url' => 'http://engine.local',
                'api_key' => 'k',
                'timeout_seconds' => 10.0,
                'fail_hard' => true,
                'default_category' => 'clothing',
                'nexus_states' => ['MN'],
                'cache_ttl_seconds' => 7200,
            ],
        ]);

        $args = $container->getDefinition('opensalestax_sylius.config')->getArguments();
        $this->assertCount(7, $args);
        $this->assertSame('http://engine.local', $args[0]);
        $this->assertSame('k', $args[1]);
        $this->assertSame(10.0, $args[2]);
        $this->assertTrue($args[3]);
        $this->assertSame('clothing', $args[4]);
        $this->assertSame(['MN'], $args[5]);
        $this->assertSame(7200, $args[6]);
    }

    #[Test]
    public function full_container_compile_resolves_services(): void
    {
        $container = $this->buildContainer();
        $container->compile();

        // Public services should be resolvable.
        $this->assertInstanceOf(OstaxConfig::class, $container->get('opensalestax_sylius.config'));
        $this->assertInstanceOf(OstaxCache::class, $container->get('opensalestax_sylius.cache'));
        $this->assertInstanceOf(OstaxClient::class, $container->get('opensalestax_sylius.client'));
        $this->assertInstanceOf(OstaxCalculator::class, $container->get('opensalestax_sylius.calculator.ostax'));
        $this->assertInstanceOf(OstaxTaxationStrategy::class, $container->get('opensalestax_sylius.strategy.ostax'));
    }

    /**
     * @param array{opensalestax_sylius: array<string, mixed>}|null $merchantConfig
     */
    private function buildContainer(?array $merchantConfig = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new OpenSalesTaxSyliusExtension();

        $cfg = $merchantConfig ?? ['opensalestax_sylius' => ['engine_url' => 'http://engine.local']];
        /** @var array<string, mixed> $bundleCfg */
        $bundleCfg = $cfg['opensalestax_sylius'];
        $extension->load([$bundleCfg], $container);

        return $container;
    }
}
