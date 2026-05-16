<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * `opensalestax_sylius:` config tree.
 *
 *     opensalestax_sylius:
 *         engine_url: '%env(OSTAX_ENGINE_URL)%'   # required, http(s)
 *         api_key:    '%env(OSTAX_API_KEY)%'      # optional
 *         timeout_seconds: 5                      # default 5.0
 *         fail_hard: false                        # default false (fail-soft)
 *         default_category: general               # one of the 6 OST categories
 *         nexus_states: []                        # empty = collect everywhere
 *         cache_ttl_seconds: 3600                 # 60-min cache by default
 *
 * Validation that exceeds what Symfony's config tree expresses (URL scheme,
 * state-code regex, etc.) is enforced in `OstaxConfig` at construction time.
 * The tree below catches the obvious typos (string vs int, unknown key) so
 * the merchant gets a clear error at container build time.
 */
final class Configuration implements ConfigurationInterface
{
    public const VALID_CATEGORIES = [
        'general',
        'clothing',
        'groceries',
        'prescription_drugs',
        'prepared_food',
        'digital_goods',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('opensalestax_sylius');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $root */
        $root = $tree->getRootNode();

        $root
            ->children()
                ->scalarNode('engine_url')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Base URL of the OpenSalesTax engine. Must be http(s).')
                ->end()
                ->scalarNode('api_key')
                    ->defaultNull()
                    ->info('Optional API key. Sent as the X-API-Key header.')
                ->end()
                ->floatNode('timeout_seconds')
                    ->defaultValue(5.0)
                    ->min(0.001)
                    ->info('Per-request timeout in seconds.')
                ->end()
                ->booleanNode('fail_hard')
                    ->defaultFalse()
                    ->info('When true, engine errors throw and surface as Sylius errors. Default: fail-soft (return 0, log warning).')
                ->end()
                ->enumNode('default_category')
                    ->values(self::VALID_CATEGORIES)
                    ->defaultValue('general')
                    ->info('OST category sent for line items with no explicit per-product mapping.')
                ->end()
                ->arrayNode('nexus_states')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->info('Allowlist of US state codes (e.g. ["MN", "WI"]). Empty = collect everywhere.')
                ->end()
                ->integerNode('cache_ttl_seconds')
                    ->defaultValue(3600)
                    ->min(0)
                    ->info('Cache TTL for engine results. 0 disables caching.')
                ->end()
            ->end()
        ;

        return $tree;
    }
}
