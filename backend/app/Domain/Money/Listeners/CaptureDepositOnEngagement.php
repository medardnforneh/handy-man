<?php

declare(strict_types=1);

namespace App\Domain\Money\Listeners;

use App\Domain\Money\Actions\CaptureDepositOnAgreement;
use App\Events\OutboxMessagePublished;
use App\Models\Engagement;

/**
 * Rides the outbox seam to capture the deposit into escrow when an engagement forms (build plan
 * P3-13). `engagement.created` is committed by the time it is relayed, so the gateway call happens
 * outside the acceptance transaction (doc 03 — never call a gateway inside a DB transaction). The
 * capture itself is idempotent, so the at-least-once relay can fire this more than once safely.
 */
final class CaptureDepositOnEngagement
{
    public function __construct(private readonly CaptureDepositOnAgreement $capture) {}

    public function handle(OutboxMessagePublished $event): void
    {
        if ($event->type !== 'engagement.created') {
            return;
        }

        $engagementId = $event->payload['engagement_id'] ?? null;
        if (! is_string($engagementId)) {
            return;
        }

        $engagement = Engagement::query()->with(['job.customer.user', 'milestones'])->find($engagementId);
        if ($engagement === null) {
            return;
        }

        $this->capture->handle($engagement);
    }
}
