<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Tests\Unit;

use OpenSalesTax\Sylius\Cache\OstaxCache;
use OpenSalesTax\Sylius\Calculator\OstaxCalculator;
use OpenSalesTax\Sylius\Client\OstaxClient;
use OpenSalesTax\Sylius\Config\OstaxConfig;
use OpenSalesTax\Sylius\OpenSalesTaxSyliusBundle;
use OpenSalesTax\Sylius\Strategy\OstaxTaxationStrategy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Loads the bundle into a minimal Symfony kernel and asserts the full
 * service graph compiles + resolves. Smoke test for the DI plumbing
 * end-to-end (extension load → config tree → service definitions →
 * compile → public-id resolution).
 */
final class BundleSmokeTest extends TestCase
{
    #[Test]
    public function bundle_boots_into_a_minimal_symfony_kernel(): void
    {
        $kernel = new SmokeKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            $this->assertInstanceOf(OstaxConfig::class, $container->get('opensalestax_sylius.config'));
            $this->assertInstanceOf(OstaxClient::class, $container->get('opensalestax_sylius.client'));
            $this->assertInstanceOf(OstaxCache::class, $container->get('opensalestax_sylius.cache'));
            $this->assertInstanceOf(OstaxCalculator::class, $container->get('opensalestax_sylius.calculator.ostax'));
            $this->assertInstanceOf(OstaxTaxationStrategy::class, $container->get('opensalestax_sylius.strategy.ostax'));
        } finally {
            $kernel->shutdown();
        }
    }
}

/**
 * Minimal kernel that registers only our bundle.
 *
 * @internal
 */
final class SmokeKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [new OpenSalesTaxSyliusBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('opensalestax_sylius', [
                'engine_url' => 'http://engine.local:8080',
            ]);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/opensalestax-sylius-smoke/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/opensalestax-sylius-smoke/log';
    }
}
