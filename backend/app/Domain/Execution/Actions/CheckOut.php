<?php

declare(strict_types=1);

namespace App\Domain\Execution\Actions;

use App\Domain\Execution\NotCheckedIn;
use App\Models\Assignment;
use App\Models\WorkSession;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Enums\Srid;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * A worker checks out, closing their open {@see WorkSession} with the server-recorded end geo +
 * timestamp (build plan P5-03, doc 06). Check-out is a pure work-time close — completion of the job
 * is a separate structured signal ({@see RecordStatus} with `completed`), so leaving the site and
 * declaring the work done stay distinct.
 */
final class CheckOut
{
    public function handle(Assignment $assignment, ?float $latitude = null, ?float $longitude = null, ?float $accuracyM = null): WorkSession
    {
        $point = $latitude !== null && $longitude !== null
            ? new Point($latitude, $longitude, Srid::WGS84->value)
            : null;

        return DB::transaction(function () use ($assignment, $point, $accuracyM): WorkSession {
            $session = WorkSession::query()
                ->where('assignment_id', $assignment->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw new NotCheckedIn;
            }

            $session->update([
                'ended_at' => now(),
                'end_point' => $point,
                'end_accuracy_m' => $accuracyM,
            ]);

            return $session;
        });
    }
}
