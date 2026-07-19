<?php

declare(strict_types=1);

namespace App\Domain\Engagements\Policies;

use App\Models\Engagement;
use App\Models\Membership;
use App\Models\User;

/**
 * Who may staff an engagement (build plan P2-08). This is org-internal RBAC (doc 10 §1) — a real
 * permission carried by memberships, NOT the fact-gated customer/provider access model:
 *
 *   - individual provider  → only the provider themselves (they are their own workforce);
 *   - organization provider → an ACTIVE owner/admin/dispatcher member of that org.
 *
 * A worker-role member cannot reassign; a member of another org cannot touch this engagement.
 */
final class EngagementPolicy
{
    /** Membership roles that carry dispatch authority. */
    private const DISPATCH_ROLES = ['owner', 'admin', 'dispatcher'];

    public function assignWorker(User $actor, Engagement $engagement): bool
    {
        // Individual provider: the provider user is their own dispatcher.
        if ($actor->party_id === $engagement->provider_party_id) {
            return true;
        }

        // Organization provider: an active member with dispatch authority in THAT org.
        return Membership::query()
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->whereIn('role', self::DISPATCH_ROLES)
            ->whereHas('organization', fn ($q) => $q->where('party_id', $engagement->provider_party_id))
            ->exists();
    }

    /** Removing an assignment takes the same dispatch authority as creating one. */
    public function removeAssignment(User $actor, Engagement $engagement): bool
    {
        return $this->assignWorker($actor, $engagement);
    }
}
