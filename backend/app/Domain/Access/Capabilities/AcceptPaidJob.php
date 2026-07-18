<?php

declare(strict_types=1);

namespace App\Domain\Access\Capabilities;

use App\Domain\Access\Facts\Fact;
use App\Models\User;
use InvalidArgumentException;

/**
 * The accept-paid-job gate (doc 10 §"The verification gate keys on engagement_mode", build plan
 * P0-06b / P6-03). Physical presence raises the stakes, so the required identity tier is a
 * function of the job's mode and the skill's risk tier — NOT one flat rule:
 *
 *   remote        → tier 1  (lighter check: confirmed phone + basic profile; no home-visit tier)
 *   onsite/hybrid → tier 2, or tier 3 for a high-risk skill (risk_tier 3)
 *
 * Context: ['engagement_mode' => 'onsite'|'hybrid'|'remote', 'risk_tier' => int 1..3].
 *
 * The identity tier itself is derived by the FactDeriver (the real resolver arrives with
 * verification in Phase 6); this class only decides how strong it must be and gates on it.
 */
final class AcceptPaidJob extends Capability
{
    public function key(): string
    {
        return 'accept_paid_job';
    }

    public function authorize(User $user, array $context = []): void
    {
        $requiredTier = $this->requiredTier(
            mode: (string) ($context['engagement_mode'] ?? ''),
            riskTier: (int) ($context['risk_tier'] ?? 1),
        );

        $this->require(
            user: $user,
            fact: Fact::IdentityVerified,
            resolve: ['type' => 'verification', 'deep_link' => '/provider/verify'],
            requiredLevel: $requiredTier,
        );
    }

    /**
     * The identity tier required to accept a paid job of this mode/risk.
     */
    public function requiredTier(string $mode, int $riskTier = 1): int
    {
        return match ($mode) {
            'remote' => 1,
            'onsite', 'hybrid' => $riskTier >= 3 ? 3 : 2,
            default => throw new InvalidArgumentException("Unknown engagement_mode: {$mode}"),
        };
    }
}
