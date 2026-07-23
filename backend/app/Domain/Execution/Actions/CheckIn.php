<?php

declare(strict_types=1);

namespace App\Domain\Execution\Actions;

use App\Domain\Execution\AlreadyCheckedIn;
use App\Domain\Execution\CheckInNotSupported;
use App\Domain\Jobs\EngagementModePolicy;
use App\Domain\Workspace\ConversationManager;
use App\Domain\Workspace\MessageKind;
use App\Domain\Workspace\Narrator;
use App\Models\Assignment;
use App\Models\WorkSession;
use App\Support\Outbox;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Enums\Srid;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * A worker checks in at the job site (build plan P5-03, doc 06). Opens a {@see WorkSession} with the
 * server-recorded geo + timestamp and narrates `arrived` into the workspace thread, in one
 * transaction (rule #11) so a rollback narrates nothing.
 *
 * Check-in exists only for physical work — the {@see EngagementModePolicy} gate rejects a remote
 * engagement outright. A worker may hold only one open session per assignment; the DB partial unique
 * index `one_open_session_per_assignment` is the hard guarantee behind the app-level pre-check.
 */
final class CheckIn
{
    public function __construct(
        private readonly EngagementModePolicy $modePolicy,
        private readonly ConversationManager $conversations,
        private readonly Narrator $narrator,
        private readonly Outbox $outbox,
    ) {}

    public function handle(Assignment $assignment, ?float $latitude = null, ?float $longitude = null, ?float $accuracyM = null): WorkSession
    {
        $engagement = $assignment->engagement()->with('job')->firstOrFail();

        if (! $this->modePolicy->supportsCheckIn($engagement->job->engagement_mode)) {
            throw new CheckInNotSupported;
        }

        $point = $latitude !== null && $longitude !== null
            ? new Point($latitude, $longitude, Srid::WGS84->value)
            : null;

        return DB::transaction(function () use ($assignment, $engagement, $point, $accuracyM): WorkSession {
            try {
                $session = WorkSession::query()->create([
                    'assignment_id' => $assignment->id,
                    'started_at' => now(),
                    'start_point' => $point,
                    'start_accuracy_m' => $accuracyM,
                ]);
            } catch (QueryException $e) {
                // The partial unique index caught a second open session (a double check-in / race).
                if ($this->isOpenSessionConflict($e)) {
                    throw new AlreadyCheckedIn;
                }
                throw $e;
            }

            $conversation = $this->conversations->ensureForEngagement($engagement);
            $this->narrator->narrate($conversation, MessageKind::Arrived, [
                'work_session_id' => $session->id,
            ], senderUserId: $assignment->worker_user_id);

            $this->outbox->publish('work_session.started', [
                'work_session_id' => $session->id,
                'engagement_id' => $engagement->id,
            ]);

            return $session;
        });
    }

    private function isOpenSessionConflict(QueryException $e): bool
    {
        return $e->getCode() === '23505'
            && str_contains($e->getMessage(), 'one_open_session_per_assignment');
    }
}
