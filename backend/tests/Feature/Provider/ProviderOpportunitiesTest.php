<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Offers\OfferStatus;
use App\Models\Address;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * The provider opportunity feed (P2-05/06): the provider's live incoming direct offers, PII-minimised.
 * A provider viewing a pre-engagement on-site job must see only the coarse area — never the exact
 * address or coordinates (build plan P2-03, CLAUDE.md rule #6). Expired/decided offers don't appear.
 */

/** An on-site offered job (with address) plus a pending offer to $provider. */
function pendingOnsiteOfferFor(User $provider): JobOffer
{
    $customer = User::factory()->create();
    $address = Address::factory()->create([
        'party_id' => $customer->party_id,
        'line1' => '42 Rue Secrète',
        'quarter' => 'Bonapriso',
        'city' => 'Douala',
    ]);
    $job = Job::factory()->status(JobStatus::Offered)->create([
        'customer_party_id' => $customer->party_id,
        'address_id' => $address->id,
    ]);

    return JobOffer::factory()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
        'amount_minor' => 900_000,
    ]);
}

it('lists the provider’s live offers with the coarse area, hiding the exact address', function () {
    $provider = User::factory()->create();
    $offer = pendingOnsiteOfferFor($provider);

    Sanctum::actingAs($provider);
    $response = $this->getJson('/api/v1/provider/opportunities')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $offer->id)
        ->assertJsonPath('data.0.amount.amount_minor', 900_000)
        ->assertJsonPath('data.0.job.location.quarter', 'Bonapriso')
        ->assertJsonPath('data.0.job.location.city', 'Douala');

    // The street line must never reach a pre-engagement provider.
    expect($response->json('data.0.job.location'))->not->toHaveKey('line1');
    expect(json_encode($response->json()))->not->toContain('Rue Secrète');
});

it('excludes another provider’s offers', function () {
    $me = User::factory()->create();
    pendingOnsiteOfferFor(User::factory()->create()); // an offer to someone else

    Sanctum::actingAs($me);
    $this->getJson('/api/v1/provider/opportunities')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('excludes expired and already-decided offers', function () {
    $provider = User::factory()->create();
    $live = pendingOnsiteOfferFor($provider);

    $expired = pendingOnsiteOfferFor($provider);
    $expired->update(['expires_at' => now()->subHour()]);

    $accepted = pendingOnsiteOfferFor($provider);
    $accepted->update(['status' => OfferStatus::Accepted->value]);

    Sanctum::actingAs($provider);
    $this->getJson('/api/v1/provider/opportunities')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $live->id);
});

it('requires authentication', function () {
    $this->getJson('/api/v1/provider/opportunities')->assertUnauthorized();
});
