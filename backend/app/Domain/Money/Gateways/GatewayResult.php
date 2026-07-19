<?php

declare(strict_types=1);

namespace App\Domain\Money\Gateways;

/**
 * The normalised result of a collection/payout/status call. `externalRef` is the gateway's own
 * reference (stored on the intent for reconciliation); `paymentUrl` is set for redirect-style flows.
 */
final readonly class GatewayResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public GatewayStatus $status,
        public ?string $externalRef,
        public array $raw = [],
        public ?string $paymentUrl = null,
        public ?string $failureCode = null,
    ) {}
}
