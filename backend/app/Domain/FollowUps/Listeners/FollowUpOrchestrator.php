<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Listeners;

use App\Domain\FollowUps\ChannelLadder;
use App\Domain\FollowUps\FollowUpKind;
use App\Domain\FollowUps\FollowUpScheduler;
use App\Events\OutboxMessagePublished;
use App\Models\Engagement;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Support\Carbon;

/**
 * Turns domain events into follow-ups (build plan P7-02/07, doc 07 rule 1: schedule on event, cancel
 * on event). Subscribes to the outbox seam, so a follow-up is scheduled only for a committed event —
 * and idempotently, since the scheduler dedupes. A follow-up whose reason has evaporated is cancelled
 * by the counter-event, never left to fire.
 */
final class FollowUpOrchestrator
{
    public function __construct(
        private readonly FollowUpScheduler $scheduler,
        private readonly ChannelLadder $ladder,
    ) {}

    public function handle(OutboxMessagePublished $event): void
    {
        match ($event->type) {
            'engagement.completed' => $this->onEngagementCompleted($event->payload),
            'review.submitted' => $this->onReviewSubmitted($event->payload),
            'warranty.issued' => $this->onWarrantyIssued($event->payload),
            'deliverable.submitted' => $this->onDeliverableSubmitted($event->payload),
            'deliverable.accepted', 'deliverable.rejected' => $this->onDeliverableReviewed($event->payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onDeliverableSubmitted(array $payload): void
    {
        $deliverableId = $payload['deliverable_id'] ?? null;
        $engagement = $this->engagement($payload['engagement_id'] ?? null);
        $customer = $engagement !== null ? $this->customer($engagement) : null;
        if (! is_string($deliverableId) || $customer === null) {
            return;
        }

        // Warn the customer 24h before auto-approval (at 48h of a 72h window).
        $warnAt = now()->addHours(max((int) config('deliverables.auto_approve_hours', 72) - 24, 1));
        $this->scheduler->schedule(
            FollowUpKind::AutoApproveWarning, $customer, $this->ladder->pick($customer), $warnAt,
            'deliverable', $deliverableId, engagementId: $engagement->id,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onDeliverableReviewed(array $payload): void
    {
        $deliverableId = $payload['deliverable_id'] ?? null;
        if (is_string($deliverableId)) {
            $this->scheduler->cancelByPrefix("auto_approve_warning:deliverable:{$deliverableId}", 'reviewed');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onEngagementCompleted(array $payload): void
    {
        $engagement = $this->engagement($payload['engagement_id'] ?? null);
        $customer = $engagement !== null ? $this->customer($engagement) : null;
        if ($engagement === null || $customer === null) {
            return;
        }

        $channel = $this->ladder->pick($customer);
        $this->scheduler->schedule(
            FollowUpKind::ReviewRequest, $customer, $channel, now()->addHours(2),
            'engagement', $engagement->id, engagementId: $engagement->id,
        );
        $this->scheduler->schedule(
            FollowUpKind::ReviewReminder, $customer, $channel, now()->addDays(3),
            'engagement', $engagement->id, engagementId: $engagement->id,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onReviewSubmitted(array $payload): void
    {
        $engagement = $this->engagement($payload['engagement_id'] ?? null);
        if ($engagement === null) {
            return;
        }

        // Only the customer's own submission cancels the customer's review nudges.
        if (($payload['author_party_id'] ?? null) !== $engagement->job()->firstOrFail()->customer_party_id) {
            return;
        }

        $this->scheduler->cancelByPrefix("review_request:engagement:{$engagement->id}", 'review_submitted');
        $this->scheduler->cancelByPrefix("review_reminder:engagement:{$engagement->id}", 'review_submitted');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onWarrantyIssued(array $payload): void
    {
        $warrantyId = $payload['warranty_id'] ?? null;
        if (! is_string($warrantyId)) {
            return;
        }

        $warranty = Warranty::query()->find($warrantyId);
        $engagement = $warranty !== null ? $this->engagement($warranty->engagement_id) : null;
        $customer = $engagement !== null ? $this->customer($engagement) : null;
        if ($warranty === null || $customer === null) {
            return;
        }

        $this->scheduler->schedule(
            FollowUpKind::WarrantyExpiring, $customer, $this->ladder->pick($customer),
            Carbon::parse((string) $warranty->expires_at)->subDays(14),
            'warranty', $warranty->id, warrantyId: $warranty->id,
        );
    }

    private function engagement(mixed $id): ?Engagement
    {
        return is_string($id) ? Engagement::query()->with('job')->find($id) : null;
    }

    private function customer(Engagement $engagement): ?User
    {
        return User::query()->find($engagement->job()->firstOrFail()->created_by_user_id);
    }
}
