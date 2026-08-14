<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Models\Job;
use App\Models\ProviderProfile;
use App\Models\Quotation;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * `GET /jobs/{job}/quotations` (P2.5-01).
 *
 * The read that was missing, and its absence was the hole in the middle of the marketplace: a
 * provider could submit a priced quote and the customer had no way to see it, so nothing could be
 * accepted. A quote arrives BEFORE any engagement exists, which means there is no conversation to
 * narrate it into either — the job is the only place it can appear.
 */

/**
 * @return array{customer: User, provider: User, job: Job}
 */
function quotedJob(): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create(['customer_party_id' => $customer->party_id]);
    $provider = User::factory()->create();
    ProviderProfile::factory()->create(['party_id' => $provider->party_id, 'headline' => 'Plomberie soignée']);

    return ['customer' => $customer, 'provider' => $provider, 'job' => $job];
}

it('shows the customer every submitted quote on their job, with the provider’s public face', function () {
    ['customer' => $customer, 'provider' => $provider, 'job' => $job] = quotedJob();
    Quotation::factory()->submitted()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
        'subtotal_minor' => 450_000,
        'deposit_minor' => 100_000,
    ]);

    Sanctum::actingAs($customer);
    $response = $this->getJson("/api/v1/jobs/{$job->id}/quotations")->assertOk();

    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.subtotal.amount_minor', 450_000);
    $response->assertJsonPath('data.0.provider.headline', 'Plomberie soignée');
    // Pre-engagement, a provider is a headline and a badge — never a name (P2-03).
    expect($response->json('data.0.provider'))->not->toHaveKey('display_name');
});

it('never discloses another provider’s draft', function () {
    ['customer' => $customer, 'provider' => $provider, 'job' => $job] = quotedJob();
    Quotation::factory()->create(['job_id' => $job->id, 'provider_party_id' => $provider->party_id]); // draft

    Sanctum::actingAs($customer);
    $this->getJson("/api/v1/jobs/{$job->id}/quotations")->assertOk()->assertJsonCount(0, 'data');
});

it('shows a provider their own quote and nobody else’s', function () {
    ['provider' => $mine, 'job' => $job] = quotedJob();
    $other = User::factory()->create();
    Quotation::factory()->submitted()->create(['job_id' => $job->id, 'provider_party_id' => $mine->party_id]);
    Quotation::factory()->submitted()->create(['job_id' => $job->id, 'provider_party_id' => $other->party_id]);

    Sanctum::actingAs($mine);
    $response = $this->getJson("/api/v1/jobs/{$job->id}/quotations")->assertOk();

    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.provider_party_id', $mine->party_id);
});

it('refuses a stranger with 403 rather than an empty list', function () {
    ['job' => $job] = quotedJob();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/jobs/{$job->id}/quotations")->assertForbidden();
});

it('requires a session', function () {
    ['job' => $job] = quotedJob();

    $this->getJson("/api/v1/jobs/{$job->id}/quotations")->assertUnauthorized();
});
