<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Models\Address;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\OutboxMessage;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function makeOpenJob(User $customer): Job
{
    $address = Address::factory()->create(['party_id' => $customer->party_id]);

    return Job::factory()->status(JobStatus::Open)->create([
        'customer_party_id' => $customer->party_id, 'address_id' => $address->id,
    ]);
}

it('creates a customer_direct offer and moves the job open → offered', function () {
    $customer = User::factory()->create();
    $job = makeOpenJob($customer);
    $provider = ProviderProfile::factory()->create();

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/jobs/{$job->id}/offers",
        ['provider_party_id' => $provider->party_id, 'amount_minor' => 800000],
        ['Idempotency-Key' => (string) Str::uuid()],
    )
        ->assertCreated()
        ->assertJsonPath('data.origin', 'customer_direct')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount.amount_minor', 800000);

    expect($job->fresh()->status)->toBe(JobStatus::Offered)
        ->and(OutboxMessage::where('type', 'offer.created')->count())->toBe(1);
});

it('rejects a second live offer to the same provider (one live offer per pro per job)', function () {
    $customer = User::factory()->create();
    $job = makeOpenJob($customer);
    $provider = ProviderProfile::factory()->create();

    Sanctum::actingAs($customer);
    $payload = ['provider_party_id' => $provider->party_id];
    $this->postJson("/api/v1/jobs/{$job->id}/offers", $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    $this->postJson("/api/v1/jobs/{$job->id}/offers", $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
});

it('only the job owner can offer', function () {
    $customer = User::factory()->create();
    $job = makeOpenJob($customer);
    $provider = ProviderProfile::factory()->create();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/jobs/{$job->id}/offers",
        ['provider_party_id' => $provider->party_id],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertForbidden();
});

it('ENFORCES exactly one accepted offer per job at the DB level (backbone of P2-06)', function () {
    $job = Job::factory()->create();

    JobOffer::factory()->accepted()->create(['job_id' => $job->id]);

    // A second accepted offer for the SAME job violates the partial unique index.
    // (No further DB work here: the violation aborts the test's transaction.)
    expect(fn () => JobOffer::factory()->accepted()->create(['job_id' => $job->id]))
        ->toThrow(QueryException::class);
});

it('allows accepted offers on different jobs', function () {
    JobOffer::factory()->accepted()->create();
    JobOffer::factory()->accepted()->create(); // different job via factory default

    expect(JobOffer::where('status', 'accepted')->count())->toBe(2);
});

it('expires pending offers past their deadline via the command', function () {
    $fresh = JobOffer::factory()->create();          // expires in 48h
    $stale = JobOffer::factory()->expired()->create(); // already past

    $this->artisan('offers:expire')->assertSuccessful();

    expect($fresh->fresh()->status->value)->toBe('pending')
        ->and($stale->fresh()->status->value)->toBe('expired');
});
