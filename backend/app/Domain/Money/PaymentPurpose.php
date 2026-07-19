<?php

declare(strict_types=1);

namespace App\Domain\Money;

/**
 * Why money is being collected (doc 03). Determines the ledger posting on success.
 */
enum PaymentPurpose: string
{
    case Escrow = 'escrow';
    case LeadCredits = 'lead_credits';
}
