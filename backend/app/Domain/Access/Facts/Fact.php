<?php

declare(strict_types=1);

namespace App\Domain\Access\Facts;

/**
 * The verified facts that high-stakes actions gate on (doc 10). These are DERIVED facts about a
 * user/party — never pre-granted permissions and never roles. A capability names the fact it
 * needs and checks it inline at the moment of the action.
 *
 * Some facts are tiered (identity_verified has a level 0–3); {@see FactResult::$level} carries it.
 */
enum Fact: string
{
    case IdentityVerified = 'identity_verified';       // verification_documents approved to a tier
    case HasPayoutMethod = 'has_payout_method';        // a confirmed MoMo payout number
    case SkillListed = 'skill_listed';                 // at least one provider_skill
    case HasProviderProfile = 'has_provider_profile';  // a provider_profiles row exists
    case PaymentMethodReady = 'payment_method_ready';  // a usable customer payment path
}
