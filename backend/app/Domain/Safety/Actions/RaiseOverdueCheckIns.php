<?php

declare(strict_types=1);

namespace App\Domain\Safety\Actions;

use App\Domain\Jobs\EngagementModePolicy;
use App\Domain\Safety\SafetyAlertKind;
use App\Models\Assignment;
use App\Models\SafetyAlert;
use App\Support\Outbox;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The check-in-overdue watchdog (build plan P6-06, doc 04). A worker booked for an on-site job who
 * hasn't checked in within the grace period after their scheduled start is flagged: a
 * `check_in_overdue` safety alert is raised and staff are notified. It reuses the audit trail already
 * in place — `assignments.scheduled_from` for the expectation, `work_sessions` for the actual
 * check-in. One alert per assignment (deduped against an existing open one). Scheduled; idempotent.
 */
final class RaiseOverdueCheckIns
{
    public function __construct(
        private readonly EngagementModePolicy $modePolicy,
        private readonly Outbox $outbox,
    ) {}

    /**
     * Raises alerts for every overdue assignment. Returns the number raised.
     */
    public function handle(): int
    {
        $cutoff = now()->subMinutes((int) config('safety.checkin_grace_minutes', 15));

        /** @var Collection<int, Assignment> $candidates */
        $candidates = Assignment::query()
            ->whereNotNull('scheduled_from')
            ->where('scheduled_from', '<', $cutoff)
            ->whereNull('removed_at')
            ->whereNotIn('status', ['removed', 'declined', 'completed'])
            // Never checked in.
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('work_sessions')
                ->whereColumn('work_sessions.assignment_id', 'assignments.id'))
            // Not already flagged (dedupe).
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('safety_alerts')
                ->whereColumn('safety_alerts.assignment_id', 'assignments.id')
                ->where('safety_alerts.kind', SafetyAlertKind::CheckInOverdue->value)
                ->where('safety_alerts.status', 'open'))
            ->with('engagement.job')
            ->get();

        $raised = 0;

        foreach ($candidates as $assignment) {
            $job = $assignment->engagement?->job;
            // Check-in only exists for physical work — the policy is the single mode authority.
            if ($job === null || ! $this->modePolicy->supportsCheckIn($job->engagement_mode)) {
                continue;
            }

            DB::transaction(function () use ($assignment): void {
                $alert = SafetyAlert::query()->create([
                    'user_id' => $assignment->worker_user_id,
                    'assignment_id' => $assignment->id,
                    'kind' => SafetyAlertKind::CheckInOverdue->value,
                    'status' => 'open',
                    'created_at' => now(),
                ]);

                $this->outbox->publish('safety.alert_raised', [
                    'alert_id' => $alert->id,
                    'user_id' => $assignment->worker_user_id,
                    'kind' => SafetyAlertKind::CheckInOverdue->value,
                ]);
            });

            $raised++;
        }

        return $raised;
    }
}
