<?php

declare(strict_types=1);

namespace App\Domain\Money\Gateways;

/**
 * A request to disburse money to a payee's mobile-money wallet. `reference` is our payout id.
 */
final readonly class PayoutRequest
{
    public function __construct(
        public string $reference,
        public int $amountMinor,
        public string $currency,
        public string $msisdn,
        public string $description,
    ) {}
}
