<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\Message;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P5-03 / P5-06 acceptance (doc 06): the provider execution surface. A dispatched worker checks in
 * (geo + `arrived`), records structured status signals, and checks out. Check-in exists only for
 * physical work — a remote engagement exposes no check-in affordance, and the server refuses it.
 */

/**
 * @param  'onsite'|'remote'  $mode
 * @return array{customer: User, provider: User, engagement: Engagement, assignment: Assignment}
 */
function executionEngagement(string $mode = 'onsite'): array
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

    return compact('customer', 'provider', 'engagement', 'assignment');
}

/**
 * @param  array<string, mixed>  $body
 */
function actAs(User $user, string $method, string $uri, array $body = [])
{
    Sanctum::actingAs($user);

    return test()->json($method, $uri, $body, ['Idempotency-Key' => (string) Str::uuid()]);
}

it('lets an assigned worker check in and narrates arrived into the thread', function () {
    ['provider' => $provider, 'engagement' => $engagement] = executionEngagement();

    actAs($provider, 'POST', "/api/v1/engagements/{$engagement->id}/check-in", [
        'latitude' => 3.848, 'longitude' => 11.502, 'accuracy_m' => 9.5,
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_open', true)
        ->assertJsonPath('data.start.accuracy_m', 9.5);

    expect(WorkSession::query()->whereNull('ended_at')->count())->toBe(1);
    expect(Message::where('kind', 'arrived')->count())->toBe(1);
});

it('refuses check-in on a remote engagement (no physical presence)', function () {
    ['provider' => $provider, 'engagement' => $engagement] = executionEngagement('remote');

    actAs($provider, 'POST', "/api/v1/engagements/{$engagement->id}/check-in")
        ->assertStatus(422)
        ->assertJsonPath('type', fn ($t) => str_contains((string) $t, 'check-in-not-supported'));

    expect(WorkSession::query()->count())->toBe(0);
});

it('forbids a non-assigned user from checking in', function () {
    ['engagement' => $engagement] = executionEngagement();

    actAs(User::factory()->create(), 'POST', "/api/v1/engagements/{$engagement->id}/check-in")
        ->assertForbidden();
});

it('rejects a second check-in while one session is open', function () {
    ['provider' => $provider, 'engagement' => $engagement] = executionEngagement();

    actAs($provider, 'POST', "/api/v1/engagements/{$engagement->id}/check-in")->assertCreated();
    actAs($provider, 'POST', "/api/v1/engagements/{$engagement->id}/check-in")->assertStatus(409);

    expect(WorkSession::query()->count())->toBe(1);
});

it('closes the open session on check-out', function () {
    ['provider' => $provider, 'engagement' => $engagement] = executionEngagement();
    actAs($provider, 'POST', "/api/v1/engagements/{$engagement->id}/check-in")->assertCreated();

    actAs($provider, 'POST', "/api/v1/engagements/{$engagement->id}/check-out", [
        'latitude' => 3.849, 'longitude' => 11.503,
    ])
        ->assertOk()
        ->assertJsonPath('data.is_open', false);

    expect(WorkSession::query()->whereNull('ended_at')->count())->toBe(0);
});

it('rejects check-out with no open session', function () {
    ['provider' => $provider, 'engagement' => $engagement] = executionEngagement();

    actAs($provider, 'POST', "/api/v1/engagements/{$engagement->id}/check-out")->assertStatus(409);
});

it('narrates a structured status signal into the timeline', function () {
    ['provider' => $provider, 'engagement' => $engagement] = executionEngagement();

    actAs($provider, 'POST', "/api/v1/engagements/{$engagement->id}/status", ['status' => 'on_the_way'])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'on_the_way');

    expect(Message::where('kind', 'on_the_way')->count())->toBe(1);
});

it('rejects arrived via the status endpoint (only geo check-in emits it)', function () {
    ['provider' => $provider, 'engagement' => $engagement] = executionEngagement();

    actAs($provider, 'POST', "/api/v1/engagements/{$engagement->id}/status", ['status' => 'arrived'])
        ->assertStatus(422);

    expect(Message::where('kind', 'arrived')->count())->toBe(0);
});
