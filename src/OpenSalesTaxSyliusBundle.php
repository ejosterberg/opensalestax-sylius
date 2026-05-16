<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius;

use OpenSalesTax\Sylius\DependencyInjection\OpenSalesTaxSyliusExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle entry point. Registered by the merchant in `config/bundles.php`:.
 *
 *     return [
 *         // ...
 *         OpenSalesTax\Sylius\OpenSalesTaxSyliusBundle::class => ['all' => true],
 *     ];
 *
 * The merchant then provides config in `config/packages/opensalestax_sylius.yaml`
 * (see the README for the full schema). Once registered, the bundle's
 * `OstaxTaxationStrategy` becomes available as a Sylius taxation strategy
 * (service id `opensalestax_sylius.strategy.ostax`) and can be selected
 * either as the default or per-channel via Sylius admin.
 *
 * Constitution §2: Sylius bundle, in-process, outbound-only. No standalone
 * server, no inbound webhook surface, no JWT.
 */
final class OpenSalesTaxSyliusBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new OpenSalesTaxSyliusExtension();
        }

        return $this->extension ?: null;
    }
}
