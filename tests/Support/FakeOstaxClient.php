<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Sylius\Tests\Support;

use OpenSalesTax\Responses\CalculateResponse;
use OpenSalesTax\Responses\HealthResponse;
use OpenSalesTax\Sylius\Client\OstaxClient;

/**
 * In-memory `OstaxClient` for tests. Records every call + returns a
 * canned response (or throws). Avoids touching Guzzle / the real SDK.
 */
final class FakeOstaxClient extends OstaxClient
{
    public int $callCount = 0;

    /** @var array{zip5: string, amount: string, category: string} */
    public array $lastCall = ['zip5' => '', 'amount' => '', 'category' => ''];

    /**
     * @param array<string, mixed> $cannedResponse engine "tax_total" + optional fields
     */
    public function __construct(
        private readonly array $cannedResponse,
        private readonly ?\Throwable $throw = null,
    ) {
        // Bypass parent ctor — we don't need a real SDK client. The parent
        // constructor's only side-effect is constructing the SDK; since we
        // override calculate() and health() entirely, we can skip it.
        // (No-op — intentionally do not call parent::__construct.)
    }

    public function calculate(string $zip5, string $amount, string $category): CalculateResponse
    {
        $this->callCount++;
        $this->lastCall = ['zip5' => $zip5, 'amount' => $amount, 'category' => $category];

        if ($this->throw !== null) {
            throw $this->throw;
        }

        $taxTotalRaw = $this->cannedResponse['tax_total'] ?? '0';
        $taxTotal = is_scalar($taxTotalRaw) ? (string) $taxTotalRaw : '0';

        return CalculateResponse::fromArray([
            'subtotal' => $amount,
            'tax_total' => $taxTotal,
            'lines' => [],
            'disclaimer' => 'test',
        ]);
    }

    public function health(): HealthResponse
    {
        return HealthResponse::fromArray([
            'status' => 'ok',
            'version' => 'test',
            'database_connected' => true,
        ]);
    }
}
