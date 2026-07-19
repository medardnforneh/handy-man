<?php

declare(strict_types=1);

namespace App\Domain\Offers;

/**
 * How an offer came to exist (doc 02). Phase 2 ships `customer_direct` (a customer picks a
 * provider); system_dispatch and provider_bid arrive in Phase 8.
 */
enum OfferOrigin: string
{
    case CustomerDirect = 'customer_direct';
    case SystemDispatch = 'system_dispatch';
    case ProviderBid = 'provider_bid';
}
