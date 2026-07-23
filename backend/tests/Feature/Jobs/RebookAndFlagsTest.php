<?php

declare(strict_types=1);

use App\Models\Engagement;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P8-04 / P8-05 acceptance: bidding is behind a flag (off by default); a customer can rebook a known
 * provider in one tap (clone the last job + direct offer).
 */
it('exposes marketplace feature flags, bidding off by default (P8-04)', function () {
    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('features.bidding', false)
        ->assertJsonPath('features.dispatch', false);
});

it('rebooks a known provider in one tap (P8-05)', function () {
    $customer = User::factory()->create();
    $provider = User::factory()->create();
    // A prior job with this provider (onsite by default, with an address).
    $priorJob = Job::factory()->create(['customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id]);
    Engagement::factory()->create(['job_id' => $priorJob->id, 'provider_party_id' => $provider->party_id]);

    Sanctum::actingAs($customer);
    $response = $this->postJson("/api/v1/providers/{$provider->party_id}/rebook", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    $offerId = $response->json('data.offer_id');
    $offer = JobOffer::findOrFail($offerId);
    expect($offer->provider_party_id)->toBe($provider->party_id)
        ->and(Job::findOrFail($offer->job_id)->skill_id)->toBe($priorJob->skill_id);
});

it('refuses to rebook a provider the customer never engaged (P8-05)', function () {
    $customer = User::factory()->create();
    $stranger = User::factory()->create();

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/providers/{$stranger->party_id}/rebook", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});
