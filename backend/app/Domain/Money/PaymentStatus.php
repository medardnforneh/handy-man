<?php

declare(strict_types=1);

namespace App\Domain\Money;

/**
 * Payment intent / payout lifecycle (doc 03). `pending`/`processing` are long-lived and normal —
 * the MoMo USSD prompt may sit unanswered for a while. Only terminal states resolve an intent.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Expired => true,
            default => false,
        };
    }
}
