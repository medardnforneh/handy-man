<?php

declare(strict_types=1);

namespace App\Domain\Engagements\Actions;

use App\Domain\Engagements\AssignmentConflict;
use App\Domain\Engagements\AssignmentRole;
use App\Domain\Engagements\AssignmentStatus;
use App\Domain\Engagements\Policies\EngagementPolicy;
use App\Domain\Engagements\WorkerNotInProviderOrg;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * A dispatcher assigns a worker to an engagement (build plan P2-08). Authorization (who may assign)
 * is the {@see EngagementPolicy}, checked in the controller. This
 * Action owns the org-boundary check on the WORKER and the lead/duplicate conflict handling.
 *
 * The DB trigger `assignments_worker_boundary_check` is the hard guarantee; the app-level check here
 * turns a cross-org attempt into a clean 422 instead of a raw DB error.
 */
final class AssignWorker
{
    public function __construct(private readonly Outbox $outbox) {}

    public function handle(User $actor, Engagement $engagement, User $worker, AssignmentRole $role): Assignment
    {
        $this->assertWorkerBelongs($engagement, $worker);

        return DB::transaction(function () use ($actor, $engagement, $worker, $role): Assignment {
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

            $assignment = Assignment::query()->create([
                'engagement_id' => $engagement->id,
                'worker_user_id' => $worker->id,
                'assigned_by_user_id' => $actor->id,
                'role' => $role->value,
                'status' => AssignmentStatus::Assigned->value,
                'assigned_at' => now(),
            ]);

            $this->outbox->publish('assignment.created', [
                'assignment_id' => $assignment->id,
                'engagement_id' => $engagement->id,
                'worker_user_id' => $worker->id,
                'role' => $role->value,
            ]);

            return $assignment;
        });
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
