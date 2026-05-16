<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\DependencyInjection;

use OpenSalesTax\Sylius\Cache\OstaxCache;
use OpenSalesTax\Sylius\Calculator\OstaxCalculator;
use OpenSalesTax\Sylius\Client\OstaxClient;
use OpenSalesTax\Sylius\Config\OstaxConfig;
use OpenSalesTax\Sylius\Strategy\OstaxTaxationStrategy;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads the bundle's services and pipes the merchant's `opensalestax_sylius:`
 * config block into the container as `OstaxConfig` constructor args.
 *
 * Service IDs registered (stable public surface):
 *   - opensalestax_sylius.config             → OstaxConfig (frozen value object)
 *   - opensalestax_sylius.client             → OstaxClient (engine HTTP client)
 *   - opensalestax_sylius.cache              → OstaxCache  (PSR-6-backed)
 *   - opensalestax_sylius.calculator.ostax   → OstaxCalculator
 *   - opensalestax_sylius.strategy.ostax     → OstaxTaxationStrategy
 *
 * The calculator is also tagged with `sylius.tax_calculator` so Sylius's
 * service-locator picks it up under the type alias `ostax`.
 */
final class OpenSalesTaxSyliusExtension extends Extension
{
    public function getAlias(): string
    {
        return 'opensalestax_sylius';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        // Load YAML service definitions when the file is present (preferred —
        // keeps services declarative). Falls back to inline definitions below
        // when the file is missing (e.g. some integration test setups).
        $servicesYaml = __DIR__ . '/../Resources/config/services.yaml';
        if (file_exists($servicesYaml)) {
            $loader = new YamlFileLoader($container, new FileLocator(\dirname($servicesYaml)));
            $loader->load(basename($servicesYaml));
        }

        $config = $this->processConfiguration(new Configuration(), $configs);

        // Define / override services explicitly so we don't depend on the YAML
        // loader being available — keeps the bundle self-contained.
        $configDef = $container->register('opensalestax_sylius.config', OstaxConfig::class)
            ->setPublic(true)
            ->setArguments([
                $config['engine_url'],
                $config['api_key'],
                $config['timeout_seconds'],
                $config['fail_hard'],
                $config['default_category'],
                $config['nexus_states'],
                $config['cache_ttl_seconds'],
            ]);
        $configDef->addTag('opensalestax_sylius.config');

        $container->register('opensalestax_sylius.client', OstaxClient::class)
            ->setPublic(true)
            ->setArguments([
                new Reference('opensalestax_sylius.config'),
            ]);

        $container->register('opensalestax_sylius.cache', OstaxCache::class)
            ->setPublic(true)
            ->setArguments([
                new Reference(CacheItemPoolInterface::class, ContainerBuilder::IGNORE_ON_INVALID_REFERENCE),
                new Reference('opensalestax_sylius.config'),
            ]);

        $container->register('opensalestax_sylius.calculator.ostax', OstaxCalculator::class)
            ->setPublic(true)
            ->setArguments([
                new Reference('opensalestax_sylius.client'),
                new Reference('opensalestax_sylius.cache'),
                new Reference('opensalestax_sylius.config'),
                new Reference(LoggerInterface::class, ContainerBuilder::IGNORE_ON_INVALID_REFERENCE),
            ])
            ->addTag('sylius.tax_calculator', ['calculator' => 'ostax', 'label' => 'OpenSalesTax destination-based']);

        $container->register('opensalestax_sylius.strategy.ostax', OstaxTaxationStrategy::class)
            ->setPublic(true)
            ->setArguments([
                new Reference('opensalestax_sylius.calculator.ostax'),
                new Reference('opensalestax_sylius.config'),
                new Reference(LoggerInterface::class, ContainerBuilder::IGNORE_ON_INVALID_REFERENCE),
            ])
            ->addTag('sylius.taxation_strategy', ['type' => 'ostax', 'label' => 'OpenSalesTax']);
    }
}
