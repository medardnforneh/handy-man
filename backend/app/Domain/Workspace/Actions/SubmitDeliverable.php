<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\ConversationManager;
use App\Domain\Workspace\DeliverableStatus;
use App\Domain\Workspace\MessageKind;
use App\Domain\Workspace\Narrator;
use App\Models\Deliverable;
use App\Models\Engagement;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * The provider submits a deliverable (build plan P4-08). The submission is narrated into the
 * workspace thread (`deliverable_submitted`) by the server, in this transaction (rule #11).
 */
final class SubmitDeliverable
{
    public function __construct(
        private readonly ConversationManager $conversations,
        private readonly Narrator $narrator,
        private readonly Outbox $outbox,
    ) {}

    public function handle(
        User $provider,
        Engagement $engagement,
        string $title,
        ?string $mediaUrl = null,
        ?string $milestoneId = null,
    ): Deliverable {
        return DB::transaction(function () use ($provider, $engagement, $title, $mediaUrl, $milestoneId): Deliverable {
            $deliverable = Deliverable::query()->create([
                'engagement_id' => $engagement->id,
                'milestone_id' => $milestoneId,
                'title' => $title,
                'media_url' => $mediaUrl,
                'status' => DeliverableStatus::Submitted->value,
                'submitted_at' => now(),
            ]);

            $conversation = $this->conversations->ensureForEngagement($engagement);
            $this->narrator->narrate($conversation, MessageKind::DeliverableSubmitted, [
                'deliverable_id' => $deliverable->id,
                'title' => $title,
            ], senderUserId: $provider->id);

            $this->outbox->publish('deliverable.submitted', [
                'deliverable_id' => $deliverable->id,
                'engagement_id' => $engagement->id,
            ]);

            return $deliverable;
        });
    }
}
