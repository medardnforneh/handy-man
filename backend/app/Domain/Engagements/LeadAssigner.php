<?php

declare(strict_types=1);

namespace App\Domain\Engagements;

use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Party;

/**
 * When an engagement forms for an INDIVIDUAL provider, that individual is their own workforce, so a
 * `lead` assignment is created automatically (doc 02 uniform-assignment rule). A company provider
 * instead has a dispatcher assign workers (P2-08). Shared by both engagement entry points — offer
 * acceptance (P2-06) and quote acceptance (P2.5-05).
 */
final class LeadAssigner
{
    public function assignIfIndividual(Engagement $engagement, string $providerPartyId): void
    {
        $party = Party::query()->with('user')->findOrFail($providerPartyId);

        if (! $party->isIndividual() || $party->user === null) {
            return; // company → a dispatcher assigns workers (P2-08)
        }

        Assignment::query()->create([
            'engagement_id' => $engagement->id,
            'worker_user_id' => $party->user->id,
            'assigned_by_user_id' => $party->user->id, // self-assigned lead
            'role' => AssignmentRole::Lead->value,
            'status' => AssignmentStatus::Assigned->value,
            'assigned_at' => now(),
        ]);
    }
}
