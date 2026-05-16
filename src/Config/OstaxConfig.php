<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Config;

use OpenSalesTax\Sylius\DependencyInjection\Configuration;

/**
 * Frozen, validated bundle configuration. Built once by the DI container
 * from the merchant's `opensalestax_sylius:` block and injected into every
 * service that needs configuration.
 *
 * Validation here exceeds what Symfony's config tree can express:
 *  - URL parses + scheme is http(s)
 *  - State codes are uppercase 2-letter ISO 3166-2 (US states)
 *  - default_category is one of the OST engine's 6 known categories
 *
 * Throws `\InvalidArgumentException` on any invalid value — fail fast at
 * container build time so misconfiguration never reaches checkout.
 */
final class OstaxConfig
{
    private const ALLOWED_SCHEMES = ['http', 'https'];
    private const STATE_CODE_REGEX = '/^[A-Z]{2}$/';

    public readonly string $engineUrl;
    public readonly ?string $apiKey;
    public readonly float $timeoutSeconds;
    public readonly bool $failHard;
    public readonly string $defaultCategory;
    /** @var list<string> Uppercase 2-letter US state codes; empty = no filter. */
    public readonly array $nexusStates;
    public readonly int $cacheTtlSeconds;

    /**
     * @param list<string>|array<int, string> $nexusStates
     */
    public function __construct(
        string $engineUrl,
        ?string $apiKey,
        float $timeoutSeconds,
        bool $failHard,
        string $defaultCategory,
        array $nexusStates,
        int $cacheTtlSeconds,
    ) {
        $engineUrl = trim($engineUrl);
        if ($engineUrl === '') {
            throw new \InvalidArgumentException(
                'opensalestax_sylius: engine_url is required and cannot be empty.',
            );
        }

        $parsed = parse_url($engineUrl);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new \InvalidArgumentException(
                "opensalestax_sylius: engine_url is not a valid URL: \"{$engineUrl}\".",
            );
        }
        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new \InvalidArgumentException(
                "opensalestax_sylius: engine_url scheme must be http: or https: (got \"{$scheme}\").",
            );
        }

        if ($timeoutSeconds <= 0.0 || !is_finite($timeoutSeconds)) {
            throw new \InvalidArgumentException(
                "opensalestax_sylius: timeout_seconds must be > 0 (got {$timeoutSeconds}).",
            );
        }

        if (!in_array($defaultCategory, Configuration::VALID_CATEGORIES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'opensalestax_sylius: default_category "%s" is not one of [%s].',
                $defaultCategory,
                implode(', ', Configuration::VALID_CATEGORIES),
            ));
        }

        $normalizedStates = [];
        $invalid = [];
        foreach ($nexusStates as $raw) {
            if (!is_string($raw)) {
                $invalid[] = '(non-string)';
                continue;
            }
            $up = strtoupper(trim($raw));
            if ($up === '') {
                continue;
            }
            if (preg_match(self::STATE_CODE_REGEX, $up) !== 1) {
                $invalid[] = $raw;
                continue;
            }
            if (!in_array($up, $normalizedStates, true)) {
                $normalizedStates[] = $up;
            }
        }
        if ($invalid !== []) {
            throw new \InvalidArgumentException(sprintf(
                'opensalestax_sylius: invalid state code(s) in nexus_states: [%s]. Use uppercase 2-letter codes (e.g. "MN", "WI").',
                implode(', ', array_map(static fn ($s) => '"' . $s . '"', $invalid)),
            ));
        }

        if ($cacheTtlSeconds < 0) {
            throw new \InvalidArgumentException(
                "opensalestax_sylius: cache_ttl_seconds must be >= 0 (got {$cacheTtlSeconds}).",
            );
        }

        $this->engineUrl = rtrim($engineUrl, '/');
        $this->apiKey = ($apiKey === null || $apiKey === '') ? null : $apiKey;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->failHard = $failHard;
        $this->defaultCategory = $defaultCategory;
        $this->nexusStates = $normalizedStates;
        $this->cacheTtlSeconds = $cacheTtlSeconds;
    }

    public function nexusFilterEnabled(): bool
    {
        return $this->nexusStates !== [];
    }

    public function stateInNexus(string $stateCode): bool
    {
        if (!$this->nexusFilterEnabled()) {
            return true;
        }

        return in_array(strtoupper($stateCode), $this->nexusStates, true);
    }

    public function cacheEnabled(): bool
    {
        return $this->cacheTtlSeconds > 0;
    }
}
