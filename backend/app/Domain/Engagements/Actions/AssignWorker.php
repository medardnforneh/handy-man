<?php

declare(strict_types=1);

namespace App\Domain\Engagements\Actions;

use App\Domain\Engagements\AssignmentConflict;
use App\Domain\Engagements\AssignmentRole;
use App\Domain\Engagements\AssignmentStatus;
use App\Domain\Engagements\Policies\EngagementPolicy;
use App\Domain\Engagements\WorkerDoubleBooked;
use App\Domain\Engagements\WorkerNotInProviderOrg;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\User;
use App\Support\Outbox;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * A dispatcher assigns a worker to an engagement (build plan P2-08/P2-09). Authorization (who may
 * assign) is the {@see EngagementPolicy}, checked in the controller. This Action owns the
 * org-boundary check on the WORKER, the lead/duplicate conflict handling, and — when a time window
 * is given — double-booking prevention.
 *
 * Hard guarantees live in the DB: the trigger `assignments_worker_boundary_check` (org boundary) and
 * the EXCLUDE constraint `assignments_no_double_booking` (overlapping windows). The app-level checks
 * here turn those into clean 4xx problems instead of raw DB errors, and the DB catches races.
 */
final class AssignWorker
{
    public function __construct(private readonly Outbox $outbox) {}

    public function handle(
        User $actor,
        Engagement $engagement,
        User $worker,
        AssignmentRole $role,
        ?CarbonInterface $scheduledFrom = null,
        ?CarbonInterface $scheduledTo = null,
    ): Assignment {
        $this->assertWorkerBelongs($engagement, $worker);

        return DB::transaction(function () use ($actor, $engagement, $worker, $role, $scheduledFrom, $scheduledTo): Assignment {
            // Already assigned to this engagement? (UNIQUE(engagement, worker) is the backstop.)
            $exists = Assignment::query()
                ->where('engagement_id', $engagement->id)
                ->where('worker_user_id', $worker->id)
                ->exists();

            if ($exists) {
                throw new AssignmentConflict('This worker is already assigned to the engagement.');
            }

            // A second active lead collides with the one_lead_per_engagement partial index.
            if ($role === AssignmentRole::Lead && $this->hasActiveLead($engagement)) {
                throw new AssignmentConflict('The engagement already has a lead.');
            }

            // Double-booking pre-check (the EXCLUDE constraint is the hard backstop under a race).
            if ($scheduledFrom !== null && $scheduledTo !== null
                && $this->overlaps($worker, $scheduledFrom, $scheduledTo)) {
                throw new WorkerDoubleBooked;
            }

            try {
                $assignment = Assignment::query()->create([
                    'engagement_id' => $engagement->id,
                    'worker_user_id' => $worker->id,
                    'assigned_by_user_id' => $actor->id,
                    'role' => $role->value,
                    'status' => AssignmentStatus::Assigned->value,
                    'assigned_at' => now(),
                    'scheduled_from' => $scheduledFrom,
                    'scheduled_to' => $scheduledTo,
                ]);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'assignments_no_double_booking')) {
                    throw new WorkerDoubleBooked;
                }
                throw $e;
            }

            $this->outbox->publish('assignment.created', [
                'assignment_id' => $assignment->id,
                'engagement_id' => $engagement->id,
                'worker_user_id' => $worker->id,
                'role' => $role->value,
            ]);

            return $assignment;
        });
    }

    private function overlaps(User $worker, CarbonInterface $from, CarbonInterface $to): bool
    {
        return Assignment::query()
            ->where('worker_user_id', $worker->id)
            ->where('status', '<>', AssignmentStatus::Removed->value)
            ->whereRaw('scheduled_window && tstzrange(?, ?, ?)', [
                $from->toDateTimeString(), $to->toDateTimeString(), '[)',
            ])
            ->exists();
    }

    private function hasActiveLead(Engagement $engagement): bool
    {
        return Assignment::query()
            ->where('engagement_id', $engagement->id)
            ->where('role', AssignmentRole::Lead->value)
            ->where('status', '<>', AssignmentStatus::Removed->value)
            ->exists();
    }

    private function assertWorkerBelongs(Engagement $engagement, User $worker): void
    {
        // Individual provider: the only valid worker is the provider themselves.
        if ($engagement->provider_party_id === $worker->party_id) {
            return;
        }

        // Organization provider: the worker must be an active member of that org.
        $member = Membership::query()
            ->where('user_id', $worker->id)
            ->where('status', 'active')
            ->whereHas('organization', fn ($q) => $q->where('party_id', $engagement->provider_party_id))
            ->exists();

        if (! $member) {
            throw new WorkerNotInProviderOrg;
        }
    }
}
