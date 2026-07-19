<?php

declare(strict_types=1);

use App\Domain\Jobs\EngagementMode;
use App\Domain\Jobs\IllegalJobTransition;
use App\Domain\Jobs\JobStateMachine;
use App\Domain\Jobs\JobStatus;
use App\Models\Address;
use App\Models\Job;
use Illuminate\Database\QueryException;

// --- P2-01 acceptance: geography is conditional, enforced by the DB CHECK ---

it('saves a remote job with a NULL address', function () {
    $job = Job::factory()->remote()->create();

    expect($job->address_id)->toBeNull()
        ->and($job->engagement_mode)->toBe(EngagementMode::Remote);

    $this->assertDatabaseHas('service_jobs', ['id' => $job->id, 'address_id' => null]);
});

it('REJECTS an on-site job with no address at the DB level', function () {
    expect(fn () => Job::factory()->create([
        'engagement_mode' => 'onsite',
        'address_id' => null,
    ]))->toThrow(QueryException::class); // jobs_address_required_unless_remote CHECK
});

it('REJECTS a hybrid job with no address at the DB level', function () {
    expect(fn () => Job::factory()->hybrid()->create(['address_id' => null]))
        ->toThrow(QueryException::class);
});

it('saves an on-site job that has an address', function () {
    $address = Address::factory()->create();
    $job = Job::factory()->create(['engagement_mode' => 'onsite', 'address_id' => $address->id]);

    expect($job->address_id)->toBe($address->id);
});

// --- JobStateMachine: legal + illegal transitions ---

it('applies a legal transition and stamps published_at on open', function () {
    $job = Job::factory()->status(JobStatus::Draft)->create();

    app(JobStateMachine::class)->transition($job, JobStatus::Open);

    expect($job->fresh()->status)->toBe(JobStatus::Open)
        ->and($job->fresh()->published_at)->not->toBeNull();
});

it('stamps cancelled_at on cancellation', function () {
    $job = Job::factory()->status(JobStatus::Open)->create();

    app(JobStateMachine::class)->transition($job, JobStatus::Cancelled);

    expect($job->fresh()->cancelled_at)->not->toBeNull();
});

it('throws on an illegal transition', function () {
    $job = Job::factory()->status(JobStatus::Draft)->create();

    expect(fn () => app(JobStateMachine::class)->transition($job, JobStatus::Completed))
        ->toThrow(IllegalJobTransition::class);
});

it('allows no transitions out of terminal states', function () {
    $sm = app(JobStateMachine::class);

    expect($sm->allowedFrom(JobStatus::Cancelled))->toBe([])
        ->and($sm->allowedFrom(JobStatus::Closed))->toBe([]);
});

it('enforces the full transition matrix (every illegal pair rejected)', function () {
    $sm = app(JobStateMachine::class);

    $legal = [
        'draft' => ['open', 'cancelled'],
        'open' => ['offered', 'engaged', 'cancelled'], // engaged directly on quote acceptance (P2.5-05)
        'offered' => ['engaged', 'open', 'cancelled'],
        'engaged' => ['scheduled', 'in_progress', 'cancelled', 'disputed'],
        'scheduled' => ['en_route', 'in_progress', 'cancelled', 'disputed'],
        'en_route' => ['in_progress', 'cancelled', 'disputed'],
        'in_progress' => ['work_submitted', 'disputed'],
        'work_submitted' => ['completed', 'in_progress', 'disputed'],
        'completed' => ['closed', 'disputed'],
        'disputed' => ['in_progress', 'completed', 'closed', 'cancelled'],
        'cancelled' => [],
        'closed' => [],
    ];

    foreach (JobStatus::cases() as $from) {
        foreach (JobStatus::cases() as $to) {
            $expected = in_array($to->value, $legal[$from->value], true);
            expect($sm->canTransition($from, $to))->toBe($expected);
        }
    }
});
