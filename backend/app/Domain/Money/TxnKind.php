<?php

declare(strict_types=1);

namespace App\Domain\Money;

/**
 * The economic reason for a ledger transaction (doc 03 §Flows). Additive-only.
 */
enum TxnKind: string
{
    case LeadCreditPurchase = 'lead_credit_purchase';
    case LeadCreditSpend = 'lead_credit_spend';
    case EscrowCollection = 'escrow_collection';
    case GatewaySettlement = 'gateway_settlement';
    case EscrowRelease = 'escrow_release';
    case Payout = 'payout';
    case PayoutReversal = 'payout_reversal';
    case Refund = 'refund';
    case ReferralReward = 'referral_reward';
    case ReferralSpend = 'referral_spend';
    case CashSettlement = 'cash_settlement';
    case Adjustment = 'adjustment';
}
