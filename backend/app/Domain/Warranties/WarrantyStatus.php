<?php

declare(strict_types=1);

namespace App\Domain\Warranties;

/**
 * A warranty's lifecycle (doc 06). `active` until it expires or a claim is filed (`claimed`); `void`
 * if the engagement was refunded or the customer went off-platform.
 */
enum WarrantyStatus: string
{
    case Active = 'active';
    case Claimed = 'claimed';
    case Expired = 'expired';
    case Void = 'void';
}
