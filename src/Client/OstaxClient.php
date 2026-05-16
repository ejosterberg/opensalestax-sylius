<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Client;

use OpenSalesTax\Address;
use OpenSalesTax\Client as SdkClient;
use OpenSalesTax\Exceptions\OpenSalesTaxException;
use OpenSalesTax\LineItem;
use OpenSalesTax\Responses\CalculateResponse;
use OpenSalesTax\Responses\HealthResponse;
use OpenSalesTax\Sylius\Config\OstaxConfig;
use Psr\Http\Client\ClientInterface as PsrHttpClient;

/**
 * Thin facade around the `ejosterberg/opensalestax` PHP SDK that injects
 * bundle configuration. Exists for two reasons:
 *
 *   1. Container injection — the calculator/strategy depend on this class
 *      (which has a stable shape under our control); the SDK's `Client`
 *      ctor signature can evolve in minor releases without rippling
 *      through every service definition.
 *   2. Testing — anonymous-class subclasses can override `calculate()` /
 *      `health()` without needing to mock Guzzle internals.
 *
 * No request retries here — the engine is colocated (LAN), and a single
 * 5s timeout failure is a real signal worth surfacing immediately to the
 * fail-soft / fail-hard branches in `OstaxCalculator`.
 */
class OstaxClient
{
    private readonly SdkClient $sdk;

    /** Engine URL the underlying SDK was configured with — exposed for diagnostics. */
    public readonly string $engineUrl;

    public function __construct(
        OstaxConfig $config,
        ?PsrHttpClient $httpClient = null,
    ) {
        $this->engineUrl = $config->engineUrl;
        $this->sdk = new SdkClient(
            baseUrl: $config->engineUrl,
            apiKey: $config->apiKey,
            timeoutSeconds: $config->timeoutSeconds,
            httpClient: $httpClient,
        );
    }

    /**
     * Calls `POST /v1/calculate` for a single-line item at the given ZIP +
     * dollar amount + category. Returns the engine's response or throws
     * an SDK exception (caught + handled by the caller).
     *
     * @throws OpenSalesTaxException on engine error / network failure
     */
    public function calculate(string $zip5, string $amount, string $category): CalculateResponse
    {
        return $this->sdk->calculate(
            address: new Address(zip5: $zip5),
            lineItems: [new LineItem(amount: $amount, category: $category)],
        );
    }

    /**
     * Calls `GET /v1/health`. Used by the calculator at startup probe time
     * (logs a warning + continues) and by external callers wanting a
     * connectivity check.
     *
     * @throws OpenSalesTaxException on engine error / network failure
     */
    public function health(): HealthResponse
    {
        return $this->sdk->health();
    }
}
