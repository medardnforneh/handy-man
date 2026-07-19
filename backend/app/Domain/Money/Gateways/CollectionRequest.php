<?php

declare(strict_types=1);

namespace App\Domain\Money\Gateways;

/**
 * A request to collect money from a payer via mobile money (USSD push / redirect). `reference` is our
 * own idempotent id (the payment intent's id) that the gateway echoes back on its webhook.
 */
final readonly class CollectionRequest
{
    public function __construct(
        public string $reference,
        public int $amountMinor,
        public string $currency,
        public string $msisdn,
        public string $description,
    ) {}
}
