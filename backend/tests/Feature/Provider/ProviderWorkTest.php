<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\User;
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
