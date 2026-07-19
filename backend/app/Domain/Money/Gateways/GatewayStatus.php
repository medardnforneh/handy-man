<?php

declare(strict_types=1);

namespace App\Domain\Money\Gateways;

/**
 * The normalised outcome of a gateway operation, independent of any provider's vocabulary. Each
 * adapter maps its own codes onto these (doc 03).
 */
enum GatewayStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
