<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\DeliverableStatus;
use App\Models\Deliverable;

/**
 * Auto-approves deliverables the customer left un-reviewed past the window (build plan P3-11). A
 * provider shouldn't be held hostage by an unresponsive customer — after the timer (72h, config), a
 * submitted deliverable is accepted automatically through the same {@see ReviewDeliverable} Action,
 * so the narration, outbox and any escrow release all fire exactly as a manual acceptance would.
 * Scheduled; idempotent (only touches still-submitted rows).
 */
final class AutoApproveDeliverables
{
    public function __construct(private readonly ReviewDeliverable $review) {}

    /**
     * Auto-approves every overdue deliverable. Returns the number approved.
     */
    public function handle(): int
    {
        $cutoff = now()->subHours((int) config('deliverables.auto_approve_hours', 72));

        $overdue = Deliverable::query()
            ->where('status', DeliverableStatus::Submitted->value)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<', $cutoff)
            ->get();

        foreach ($overdue as $deliverable) {
            $this->review->handle($deliverable, accept: true);
        }

        return $overdue->count();
    }
}
