<?php

declare(strict_types=1);

use App\Domain\Metrics\ProviderMetrics;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Models\WorkSession;
use Laravel\Sanctum\Sanctum;

/**
 * P6-12 / P6-13 acceptance (doc 04): rolling provider metrics with a sample-size floor ("100% on-time
 * (1 job)" is not displayed) and a repeat-customer leakage proxy that flags — never accuses.
 */
function providerWithProfile(): User
{
    $provider = User::factory()->create();
    ProviderProfile::factory()->create(['party_id' => $provider->party_id]);

    return $provider;
}

/** A completed engagement for the provider, from a given (or fresh) customer. */
function completionFor(User $provider, ?User $customer = null): Engagement
{
    $customer ??= User::factory()->create();
    $job = Job::factory()->create(['customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id]);

    return Engagement::factory()->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id, 'completed_at' => now(),
    ]);
}

/** Add an on-time (checked out before the booking end) work session on a staggered, non-overlapping window. */
function onTimeSession(User $provider, Engagement $engagement, int $slot): void
{
    $assignment = Assignment::factory()->create([
        'engagement_id' => $engagement->id, 'worker_user_id' => $provider->id,
        'assigned_by_user_id' => $provider->id, 'role' => 'lead',
        'scheduled_from' => now()->addHours($slot * 3), 'scheduled_to' => now()->addHours($slot * 3 + 1),
    ]);
    WorkSession::factory()->create(['assignment_id' => $assignment->id, 'started_at' => now(), 'ended_at' => now()]);
}

it('withholds a rate computed from below the sample floor (P6-12)', function () {
    $provider = providerWithProfile();
    onTimeSession($provider, completionFor($provider), 0); // a single data point

    $m = app(ProviderMetrics::class)->forParty($provider->party_id);

    expect($m['on_time_sample'])->toBe(1)
        ->and($m['on_time_rate'])->toBeNull(); // 1 < floor of 5 → not displayed
});

it('computes an on-time rate once the sample floor is met (P6-12)', function () {
    $provider = providerWithProfile();
    for ($i = 0; $i < 6; $i++) {
        onTimeSession($provider, completionFor($provider), $i); // 6 on-time sessions
    }

    $m = app(ProviderMetrics::class)->forParty($provider->party_id);

    expect($m['on_time_sample'])->toBe(6)
        ->and($m['on_time_rate'])->toBe(1.0);
});

it('flags high-completion / low-repeat as possible leakage (P6-13)', function () {
    $provider = providerWithProfile();
    for ($i = 0; $i < 9; $i++) {
        completionFor($provider); // 9 completions, all DISTINCT customers → repeat rate 0
    }

    $m = app(ProviderMetrics::class)->forParty($provider->party_id);

    expect($m['completed_total'])->toBe(9)
        ->and($m['repeat_customer_rate'])->toBe(0.0)
        ->and($m['leakage_flag'])->toBeTrue();
});

it('does not flag a provider with healthy repeat business', function () {
    $provider = providerWithProfile();
    $regular = User::factory()->create();
    for ($i = 0; $i < 9; $i++) {
        completionFor($provider, $regular); // same customer every time → all repeats
    }

    $m = app(ProviderMetrics::class)->forParty($provider->party_id);

    expect($m['leakage_flag'])->toBeFalse();
});

it('does not flag a provider below the completion threshold', function () {
    $provider = providerWithProfile();
    completionFor($provider);
    completionFor($provider);

    $m = app(ProviderMetrics::class)->forParty($provider->party_id);

    expect($m['leakage_flag'])->toBeFalse(); // 2 < min completions, and repeat rate below floor → null
});

it('exposes display-safe metrics publicly, never the leakage flag (P6-12)', function () {
    $provider = providerWithProfile();
    completionFor($provider);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/providers/{$provider->party_id}/metrics")
        ->assertOk()
        ->assertJsonPath('data.jobs_completed_90d', 1)
        ->assertJsonMissingPath('data.leakage_flag')
        ->assertJsonMissingPath('data.repeat_customer_rate');
});
