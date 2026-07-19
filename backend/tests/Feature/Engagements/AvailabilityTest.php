<?php

declare(strict_types=1);

use App\Domain\Engagements\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P2-09 acceptance: a worker cannot be double-booked across overlapping time windows. The GiST
 * EXCLUDE constraint `assignments_no_double_booking` is the hard guarantee; the Action turns it into
 * a clean 409. Touching windows ([)-half-open) and removed assignments do not conflict.
 */

/**
 * A company with a dispatcher and a single worker, plus a factory to spin up engagements for it.
 *
 * @return array{dispatcher: User, worker: User, org: Organization}
 */
function schedulingOrg(): array
{
    $org = Organization::factory()->create();

    $dispatcher = User::factory()->create();
    Membership::factory()->create(['user_id' => $dispatcher->id, 'organization_id' => $org->id, 'role' => 'dispatcher']);

    $worker = User::factory()->create();
    Membership::factory()->create(['user_id' => $worker->id, 'organization_id' => $org->id, 'role' => 'worker']);

    return ['dispatcher' => $dispatcher, 'worker' => $worker, 'org' => $org];
}

function assignWindow(User $worker, string $from, string $to): array
{
    return ['worker_user_id' => $worker->id, 'role' => 'helper', 'scheduled_from' => $from, 'scheduled_to' => $to];
}

it('books a worker for a time window', function () {
    ['dispatcher' => $dispatcher, 'worker' => $worker, 'org' => $org] = schedulingOrg();
    $engagement = Engagement::factory()->create(['provider_party_id' => $org->party_id]);

    Sanctum::actingAs($dispatcher);
    $this->postJson("/api/v1/engagements/{$engagement->id}/assignments",
        assignWindow($worker, '2026-08-01T10:00:00Z', '2026-08-01T12:00:00Z'),
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.scheduled_from', '2026-08-01T10:00:00+00:00')
        ->assertJsonPath('data.scheduled_to', '2026-08-01T12:00:00+00:00');
});

it('rejects double-booking a worker across overlapping windows (409)', function () {
    ['dispatcher' => $dispatcher, 'worker' => $worker, 'org' => $org] = schedulingOrg();
    $eng1 = Engagement::factory()->create(['provider_party_id' => $org->party_id]);
    $eng2 = Engagement::factory()->create(['provider_party_id' => $org->party_id]);

    Sanctum::actingAs($dispatcher);
    $this->postJson("/api/v1/engagements/{$eng1->id}/assignments",
        assignWindow($worker, '2026-08-01T10:00:00Z', '2026-08-01T12:00:00Z'),
        ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    // Overlaps 11:00–13:00 with the first booking.
    $this->postJson("/api/v1/engagements/{$eng2->id}/assignments",
        assignWindow($worker, '2026-08-01T11:00:00Z', '2026-08-01T13:00:00Z'),
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409)
        ->assertJsonPath('title', 'Worker is already booked for that time');

    expect(Assignment::where('worker_user_id', $worker->id)->count())->toBe(1);
});

it('allows non-overlapping and exactly-adjacent windows', function () {
    ['dispatcher' => $dispatcher, 'worker' => $worker, 'org' => $org] = schedulingOrg();
    $eng1 = Engagement::factory()->create(['provider_party_id' => $org->party_id]);
    $eng2 = Engagement::factory()->create(['provider_party_id' => $org->party_id]);

    Sanctum::actingAs($dispatcher);
    $this->postJson("/api/v1/engagements/{$eng1->id}/assignments",
        assignWindow($worker, '2026-08-01T10:00:00Z', '2026-08-01T12:00:00Z'),
        ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    // Starts exactly when the first ends — half-open [) means no overlap.
    $this->postJson("/api/v1/engagements/{$eng2->id}/assignments",
        assignWindow($worker, '2026-08-01T12:00:00Z', '2026-08-01T14:00:00Z'),
        ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    expect(Assignment::where('worker_user_id', $worker->id)->count())->toBe(2);
});

it('frees the time slot once an overlapping assignment is removed', function () {
    ['dispatcher' => $dispatcher, 'worker' => $worker, 'org' => $org] = schedulingOrg();
    $eng1 = Engagement::factory()->create(['provider_party_id' => $org->party_id]);
    $eng2 = Engagement::factory()->create(['provider_party_id' => $org->party_id]);

    Sanctum::actingAs($dispatcher);
    $created = $this->postJson("/api/v1/engagements/{$eng1->id}/assignments",
        assignWindow($worker, '2026-08-01T10:00:00Z', '2026-08-01T12:00:00Z'),
        ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    $id = $created->json('data.id');

    $this->deleteJson("/api/v1/engagements/{$eng1->id}/assignments/{$id}",
        [], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    // The overlapping window is now free.
    $this->postJson("/api/v1/engagements/{$eng2->id}/assignments",
        assignWindow($worker, '2026-08-01T11:00:00Z', '2026-08-01T13:00:00Z'),
        ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
});

it('validates that a window has both bounds, ordered', function () {
    ['dispatcher' => $dispatcher, 'worker' => $worker, 'org' => $org] = schedulingOrg();
    $engagement = Engagement::factory()->create(['provider_party_id' => $org->party_id]);

    Sanctum::actingAs($dispatcher);
    // `to` before `from`.
    $this->postJson("/api/v1/engagements/{$engagement->id}/assignments",
        assignWindow($worker, '2026-08-01T12:00:00Z', '2026-08-01T10:00:00Z'),
        ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);

    // `from` without `to`.
    $this->postJson("/api/v1/engagements/{$engagement->id}/assignments",
        ['worker_user_id' => $worker->id, 'scheduled_from' => '2026-08-01T10:00:00Z'],
        ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
});

it('DB EXCLUDE constraint blocks an overlapping booking even if the app were bypassed', function () {
    ['worker' => $worker, 'org' => $org] = schedulingOrg();
    $eng1 = Engagement::factory()->create(['provider_party_id' => $org->party_id]);
    $eng2 = Engagement::factory()->create(['provider_party_id' => $org->party_id]);

    Assignment::factory()->create([
        'engagement_id' => $eng1->id,
        'worker_user_id' => $worker->id,
        'assigned_by_user_id' => $worker->id,
        'role' => 'helper',
        'scheduled_from' => '2026-08-01T10:00:00Z',
        'scheduled_to' => '2026-08-01T12:00:00Z',
    ]);

    expect(fn () => Assignment::factory()->create([
        'engagement_id' => $eng2->id,
        'worker_user_id' => $worker->id,
        'assigned_by_user_id' => $worker->id,
        'role' => 'helper',
        'scheduled_from' => '2026-08-01T11:00:00Z',
        'scheduled_to' => '2026-08-01T13:00:00Z',
    ]))->toThrow(QueryException::class);
});

it('does not conflict when no window is set (unscheduled assignments)', function () {
    ['dispatcher' => $dispatcher, 'worker' => $worker, 'org' => $org] = schedulingOrg();
    $eng1 = Engagement::factory()->create(['provider_party_id' => $org->party_id]);
    $eng2 = Engagement::factory()->create(['provider_party_id' => $org->party_id]);

    Sanctum::actingAs($dispatcher);
    $this->postJson("/api/v1/engagements/{$eng1->id}/assignments",
        ['worker_user_id' => $worker->id, 'role' => 'helper'],
        ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    $this->postJson("/api/v1/engagements/{$eng2->id}/assignments",
        ['worker_user_id' => $worker->id, 'role' => 'helper'],
        ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    expect(Assignment::where('worker_user_id', $worker->id)->whereIn('status', [AssignmentStatus::Assigned->value])->count())->toBe(2);
});
