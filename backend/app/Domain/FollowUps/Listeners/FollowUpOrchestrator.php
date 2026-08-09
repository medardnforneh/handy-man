<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Listeners;

use App\Domain\FollowUps\ChannelLadder;
use App\Domain\FollowUps\FollowUpChannel;
use App\Domain\FollowUps\FollowUpKind;
use App\Domain\FollowUps\FollowUpScheduler;
use App\Events\OutboxMessagePublished;
use App\Models\Engagement;
use App\Models\Quotation;
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
            'quote.submitted' => $this->onQuoteSubmitted($payloadQuotationId = $event->payload['quotation_id'] ?? null),
            'quote.accepted' => $this->cancelQuoteFollowUps($event->payload['quotation_id'] ?? null),
            'quote.revised' => $this->onQuoteRevised($event->payload),
            default => null,
        };
    }

    /**
     * The unconverted-quote nudge (P2.5-06) — the highest-ROI message on the list; the lead is already
     * paid for. quote_pending_customer nudges the customer to decide; quote_expiring warns before the
     * quote lapses. Both cancel when the quote is accepted or revised.
     */
    private function onQuoteSubmitted(mixed $quotationId): void
    {
        $quotation = is_string($quotationId) ? Quotation::query()->find($quotationId) : null;
        $customer = $quotation !== null ? $this->quotationCustomer($quotation) : null;
        if ($quotation === null || $customer === null) {
            return;
        }

        $channel = $this->ladder->pick($customer);
        $this->scheduler->schedule(
            FollowUpKind::QuotePendingCustomer, $customer, $channel,
            now()->addHours((int) config('followups.quote_pending_hours', 24)),
            'quotation', $quotation->id, quotationId: $quotation->id,
        );
        $this->scheduler->schedule(
            FollowUpKind::QuoteExpiring, $customer, $channel,
            Carbon::parse((string) $quotation->valid_until)->subHours((int) config('followups.quote_expiring_lead_hours', 24)),
            'quotation', $quotation->id, quotationId: $quotation->id,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onQuoteRevised(array $payload): void
    {
        $this->cancelQuoteFollowUps($payload['supersedes_id'] ?? null); // the old quote is dead
        $this->onQuoteSubmitted($payload['quotation_id'] ?? null);      // the new one gets its nudges
    }

    private function cancelQuoteFollowUps(mixed $quotationId): void
    {
        if (is_string($quotationId)) {
            $this->scheduler->cancelByPrefix("quote_pending_customer:quotation:{$quotationId}", 'quote_settled');
            $this->scheduler->cancelByPrefix("quote_expiring:quotation:{$quotationId}", 'quote_settled');
        }
    }

    private function quotationCustomer(Quotation $quotation): ?User
    {
        $engagementJob = $quotation->job()->first();

        return $engagementJob !== null ? User::query()->find($engagementJob->created_by_user_id) : null;
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

        $this->scheduleMaintenanceDue($engagement, $customer, $channel);
    }

    /**
     * The maintenance nudge (P7-07). Scheduled ONLY when the job's trade genuinely recurs — the
     * interval lives on the skill and is null for most of the taxonomy, so a one-off wardrobe or a
     * haircut schedules nothing. Reminding someone to service work that does not need servicing is
     * how a channel teaches people to ignore it.
     *
     * It is a non-transactional kind, so it already sits behind the marketing-consent gate (P7-04)
     * and the per-channel budget (P7-03) at dispatch. Nothing here bypasses either.
     */
    private function scheduleMaintenanceDue(Engagement $engagement, User $customer, FollowUpChannel $channel): void
    {
        $job = $engagement->job()->first();
        if ($job === null) {
            return;
        }

        $intervalDays = $job->skill()->first()?->maintenance_interval_days;
        if (! is_int($intervalDays) || $intervalDays <= 0) {
            return;
        }

        // Anchored to completion, not to now: a completion relayed late must not shift the date the
        // customer's equipment is actually due.
        $completedAt = $engagement->completed_at !== null
            ? Carbon::parse((string) $engagement->completed_at)
            : now();

        $this->scheduler->schedule(
            FollowUpKind::MaintenanceDue, $customer, $channel,
            $completedAt->copy()->addDays($intervalDays),
            'engagement', $engagement->id, jobId: $job->id, engagementId: $engagement->id,
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
