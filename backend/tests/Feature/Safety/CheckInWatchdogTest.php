<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Safety\Actions\RaiseOverdueCheckIns;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\OutboxMessage;
use App\Models\SafetyAlert;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Carbon;

/**
 * P6-06 acceptance (doc 04): the check-in-overdue watchdog. A worker booked for an on-site job who
 * hasn't checked in within the grace period after their scheduled start is flagged — reusing
 * assignments.scheduled_from (the expectation) and work_sessions (the actual check-in).
 */

/**
 * @param  'onsite'|'remote'  $mode
 */
function overdueAssignment(string $mode = 'onsite', ?Carbon $scheduledFrom = null): Assignment
{
    $factory = Job::factory()->status(JobStatus::Engaged);
    if ($mode === 'remote') {
        $factory = $factory->remote();
    }
    $job = $factory->create();
    $provider = User::factory()->create();
    $engagement = Engagement::factory()->create(['job_id' => $job->id, 'provider_party_id' => $provider->party_id]);

    return Assignment::factory()->create([
        'engagement_id' => $engagement->id,
        'worker_user_id' => $provider->id,
        'assigned_by_user_id' => $provider->id,
        'role' => 'lead',
        'scheduled_from' => $scheduledFrom ?? now()->subHour(),
        'scheduled_to' => now()->addHour(),
    ]);
}

it('raises a check-in-overdue alert for an overdue on-site booking', function () {
    $assignment = overdueAssignment();

    expect(app(RaiseOverdueCheckIns::class)->handle())->toBe(1);

    $alert = SafetyAlert::query()->where('kind', 'check_in_overdue')->firstOrFail();
    expect($alert->assignment_id)->toBe($assignment->id)
        ->and($alert->user_id)->toBe($assignment->worker_user_id);
    expect(OutboxMessage::query()->where('type', 'safety.alert_raised')->exists())->toBeTrue();
});

it('does not flag a worker who has checked in', function () {
    $assignment = overdueAssignment();
    WorkSession::factory()->create(['assignment_id' => $assignment->id]); // checked in

    expect(app(RaiseOverdueCheckIns::class)->handle())->toBe(0);
});

it('does not flag remote engagements (no check-in exists there)', function () {
    overdueAssignment('remote');

    expect(app(RaiseOverdueCheckIns::class)->handle())->toBe(0);
});

it('does not flag a booking still within the grace period', function () {
    overdueAssignment('onsite', now()->subMinutes(2)); // grace is 15m

    expect(app(RaiseOverdueCheckIns::class)->handle())->toBe(0);
});

it('does not raise a second alert on a re-run (dedupe)', function () {
    overdueAssignment();

    expect(app(RaiseOverdueCheckIns::class)->handle())->toBe(1);
    expect(app(RaiseOverdueCheckIns::class)->handle())->toBe(0);
    expect(SafetyAlert::query()->where('kind', 'check_in_overdue')->count())->toBe(1);
});
