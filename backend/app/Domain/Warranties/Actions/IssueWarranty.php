<?php

declare(strict_types=1);

namespace App\Domain\Warranties\Actions;

use App\Domain\Warranties\WarrantyStatus;
use App\Domain\Workspace\ConversationManager;
use App\Domain\Workspace\MessageKind;
use App\Domain\Workspace\Narrator;
use App\Models\Engagement;
use App\Models\Warranty;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Issues a warranty on a completed engagement (build plan P6-11, doc 06). One per engagement
 * (DB-unique). The window runs from now for the given duration; the terms are free text (bilingual,
 * doc 09). Marketed as the reason to stay on-platform — the warranty only exists here.
 */
final class IssueWarranty
{
    public function __construct(
        private readonly ConversationManager $conversations,
        private readonly Narrator $narrator,
        private readonly Outbox $outbox,
    ) {}

    public function handle(Engagement $engagement, int $durationDays, ?string $terms = null): Warranty
    {
        return DB::transaction(function () use ($engagement, $durationDays, $terms): Warranty {
            $startsAt = now();

            $warranty = Warranty::query()->create([
                'engagement_id' => $engagement->id,
                'duration_days' => $durationDays,
                'starts_at' => $startsAt,
                'expires_at' => $startsAt->copy()->addDays($durationDays),
                'terms' => $terms,
                'status' => WarrantyStatus::Active->value,
            ]);

            // Narrated into the thread, in this transaction (rule #11), and NOT only for the record:
            // it is the one place the customer can learn a warranty exists at all. There is no read
            // endpoint for warranties, so before this the provider could issue one, the platform
            // could schedule an expiry nudge about it, and the person it protects had no way to see
            // it or to claim against it — the claim endpoint needed an id nothing would ever hand
            // them.
            $conversation = $this->conversations->ensureForEngagement($engagement);
            $this->narrator->narrate($conversation, MessageKind::WarrantyIssued, [
                'warranty_id' => $warranty->id,
                'expires_at' => $warranty->expires_at->toIso8601String(),
                'duration_days' => $durationDays,
            ]);

            // Announce it so the orchestrator schedules a warranty_expiring nudge 14d before expiry.
            $this->outbox->publish('warranty.issued', [
                'warranty_id' => $warranty->id,
                'engagement_id' => $engagement->id,
                'expires_at' => $warranty->expires_at->toIso8601String(),
            ]);

            return $warranty;
        });
    }
}
