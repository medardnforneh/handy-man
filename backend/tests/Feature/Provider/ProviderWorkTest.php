<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * The provider active-work list (P5-03): the caller's engagements still in flight, newest first.
 * Completed/cancelled jobs drop off; another provider's engagements never appear.
 */

/** An engagement on a remote job in the given status, for $provider, with a named customer. */
function engagementFor(User $provider, JobStatus $status, ?User $customer = null): Engagement
{
    $customer ??= User::factory()->create();
    $job = Job::factory()->remote()->status($status)->create([
        'customer_party_id' => $customer->party_id,
    ]);

    return Engagement::factory()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
    ]);
}

it('lists the provider’s in-flight engagements with job + customer + status', function () {
    $provider = User::factory()->create();
    $customer = User::factory()->create();
    $engagement = engagementFor($provider, JobStatus::InProgress, $customer);

    Sanctum::actingAs($provider);
    $this->getJson('/api/v1/provider/work')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $engagement->id)
        ->assertJsonPath('data.0.job_status', 'in_progress')
        ->assertJsonPath('data.0.engagement_mode', 'remote')
        ->assertJsonPath('data.0.customer_name', $customer->party->display_name);
});

it('excludes completed and cancelled work', function () {
    $provider = User::factory()->create();
    $live = engagementFor($provider, JobStatus::Engaged);
    engagementFor($provider, JobStatus::Completed);
    engagementFor($provider, JobStatus::Cancelled);

    Sanctum::actingAs($provider);
    $this->getJson('/api/v1/provider/work')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $live->id);
});

it('excludes another provider’s engagements', function () {
    $me = User::factory()->create();
    engagementFor(User::factory()->create(), JobStatus::Engaged); // someone else's work

    Sanctum::actingAs($me);
    $this->getJson('/api/v1/provider/work')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('requires authentication', function () {
    $this->getJson('/api/v1/provider/work')->assertUnauthorized();
});

/**
 * The work-detail read (GET /provider/work/{engagement}) — the single round-trip behind the
 * work-detail screen. Authorised by the SAME boundary as the execution actions (an active
 * assignment), and reporting DERIVED state so the client never drifts from the server.
 *
 * @param  'onsite'|'remote'  $mode
 * @return array{provider: User, engagement: Engagement, assignment: Assignment}
 */
function assignedWork(string $mode = 'onsite'): array
{
    $customer = User::factory()->create();
    $factory = Job::factory()->status(JobStatus::Engaged);
    if ($mode === 'remote') {
        $factory = $factory->remote();
    }
    $job = $factory->create([
        'customer_party_id' => $customer->party_id,
        'created_by_user_id' => $customer->id,
    ]);

    $provider = User::factory()->create();
    $engagement = Engagement::factory()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
    ]);
    $assignment = Assignment::factory()->create([
        'engagement_id' => $engagement->id,
        'worker_user_id' => $provider->id,
        'assigned_by_user_id' => $provider->id,
        'role' => 'lead',
    ]);

    return compact('provider', 'engagement', 'assignment');
}

it('returns the exact site address and a not-yet-checked-in state to the assigned worker', function () {
    ['provider' => $provider, 'engagement' => $engagement, 'assignment' => $assignment] = assignedWork();

    Sanctum::actingAs($provider);
    $this->getJson("/api/v1/provider/work/{$engagement->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $engagement->id)
        ->assertJsonPath('data.assignment_id', $assignment->id)
        ->assertJsonPath('data.supports_check_in', true)
        ->assertJsonPath('data.checked_in', false)
        ->assertJsonPath('data.current_status', null)
        ->assertJsonPath('data.report_submitted', false)
        // Post-engagement, the worker gets the street line — not the coarse area a browsing provider sees.
        ->assertJsonPath('data.address.line1', $engagement->job->address->line1);
});

it('derives checked_in and the current status from the rows the actions write', function () {
    ['provider' => $provider, 'engagement' => $engagement] = assignedWork();
    $key = fn () => ['Idempotency-Key' => (string) Str::uuid()];

    Sanctum::actingAs($provider);
    $this->json('POST', "/api/v1/engagements/{$engagement->id}/check-in", [
        'latitude' => 4.05, 'longitude' => 9.70,
    ], $key())->assertCreated();

    $this->getJson("/api/v1/provider/work/{$engagement->id}")
        ->assertOk()
        ->assertJsonPath('data.checked_in', true)
        ->assertJsonPath('data.current_status', 'arrived');

    $this->json('POST', "/api/v1/engagements/{$engagement->id}/status", ['status' => 'started'], $key())
        ->assertCreated();

    $this->getJson("/api/v1/provider/work/{$engagement->id}")
        ->assertOk()
        ->assertJsonPath('data.current_status', 'started');

    // Checking out closes the session — the affordance flips back, the status stays.
    $this->json('POST', "/api/v1/engagements/{$engagement->id}/check-out", [], $key())->assertOk();

    $this->getJson("/api/v1/provider/work/{$engagement->id}")
        ->assertOk()
        ->assertJsonPath('data.checked_in', false)
        ->assertJsonPath('data.current_status', 'started');
});

it('tells a remote engagement it has no check-in and no address', function () {
    ['provider' => $provider, 'engagement' => $engagement] = assignedWork('remote');

    Sanctum::actingAs($provider);
    $this->getJson("/api/v1/provider/work/{$engagement->id}")
        ->assertOk()
        ->assertJsonPath('data.supports_check_in', false)
        ->assertJsonPath('data.address', null);
});

it('refuses the detail to someone with no active assignment', function () {
    ['engagement' => $engagement, 'assignment' => $assignment] = assignedWork();

    // A stranger.
    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/provider/work/{$engagement->id}")->assertForbidden();

    // And the worker themselves once removed from the job — the read follows the action boundary.
    $worker = $assignment->worker;
    $assignment->update(['removed_at' => now()]);
    Sanctum::actingAs($worker);
    $this->getJson("/api/v1/provider/work/{$engagement->id}")->assertForbidden();
});

it('tells a remote engagement to use deliverables, not an on-site report', function () {
    ['provider' => $provider, 'engagement' => $engagement] = assignedWork('remote');

    Sanctum::actingAs($provider);
    $this->getJson("/api/v1/provider/work/{$engagement->id}")
        ->assertOk()
        ->assertJsonPath('data.supports_report', false)
        ->assertJsonPath('data.uses_deliverables', true)
        ->assertJsonPath('data.deliverables', []);
});

it('offers the on-site report and no deliverables on an onsite engagement', function () {
    ['provider' => $provider, 'engagement' => $engagement] = assignedWork();

    Sanctum::actingAs($provider);
    $this->getJson("/api/v1/provider/work/{$engagement->id}")
        ->assertOk()
        ->assertJsonPath('data.supports_report', true)
        ->assertJsonPath('data.uses_deliverables', false);
});
