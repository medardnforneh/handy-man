<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use App\Models\Conversation;
use App\Models\Engagement;
use App\Models\Party;

/**
 * Ensures a job's conversation exists and its participants are enrolled (doc 06). Called when an
 * engagement forms. Idempotent — safe to call more than once.
 */
final class ConversationManager
{
    public function ensureForEngagement(Engagement $engagement): Conversation
    {
        $job = $engagement->job()->firstOrFail();

        $conversation = Conversation::query()->firstOrCreate(['job_id' => $job->id]);

        // Customer side: the person who created the job.
        $this->addParticipant($conversation, $job->customer_party_id, $job->created_by_user_id);

        // Provider side: an individual provider is their own participant; a company enrolls the
        // assigned worker(s) as they're dispatched (P2-08 / later).
        $providerParty = Party::query()->with('user')->find($engagement->provider_party_id);
        if ($providerParty !== null && $providerParty->isIndividual() && $providerParty->user !== null) {
            $this->addParticipant($conversation, $providerParty->id, $providerParty->user->id);
        }

        return $conversation;
    }

    public function addParticipant(Conversation $conversation, string $partyId, string $userId): void
    {
        $conversation->participants()->firstOrCreate(
            ['user_id' => $userId],
            ['party_id' => $partyId, 'joined_at' => now()],
        );
    }
}
