<?php

declare(strict_types=1);

namespace App\Domain\Money;

use App\Support\Money;

/**
 * The chart of accounts (doc 03). Each kind has a normal balance following the sign convention in
 * {@see Money}: assets increase on debit; liabilities, equity and revenue on credit.
 */
enum AccountKind: string
{
    // Platform-owned assets.
    case PlatformCash = 'platform_cash';
    case GatewayReceivable = 'gateway_receivable';

    // Liabilities (we owe someone).
    case EscrowLiability = 'escrow_liability';      // owed to the eventual winner of an engagement
    case ProviderPayable = 'provider_payable';      // owed to a provider, awaiting payout
    case LeadCreditLiability = 'lead_credit_liability'; // prepaid lead credits held for a provider
    case PromoLiability = 'promo_liability';        // referral/promo credit held for a party

    // Revenue.
    case PlatformRevenue = 'platform_revenue';

    /**
     * Debit-normal accounts (assets) increase on debit; everything else (liabilities/revenue)
     * increases on credit.
     */
    public function isDebitNormal(): bool
    {
        return match ($this) {
            self::PlatformCash, self::GatewayReceivable => true,
            default => false,
        };
    }

    /**
     * Whether this account is scoped to a specific party (provider/referrer) rather than being a
     * single platform-wide account. Party-scoped accounts require a `party_id`.
     */
    public function requiresParty(): bool
    {
        return match ($this) {
            self::ProviderPayable, self::LeadCreditLiability, self::PromoLiability => true,
            default => false,
        };
    }
}
