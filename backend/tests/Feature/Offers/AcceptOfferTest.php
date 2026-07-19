<?php

declare(strict_types=1);

use App\Domain\Engagements\AssignmentRole;
use App\Domain\Engagements\AssignmentStatus;
use App\Domain\Jobs\JobStatus;
use App\Domain\Offers\Actions\AcceptOfferAction;
use App\Domain\Offers\OfferNotAcceptable;
use App\Domain\Offers\OfferStatus;
use App\Models\Address;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\OutboxMessage;
use App\Models\ProviderProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P2-06 / P2-06b / P2-07 acceptance (doc 06/10):
 *   - the provider accepts an offer → exactly one engagement, job open→offered→engaged;
 *   - the accept-paid-job gate keys the required identity tier on engagement_mode (P2-06b):
 *     remote succeeds under the lighter (phone) check, on-site needs full ID;
 *   - an individual provider is auto-assigned as the engagement lead (P2-07);
 *   - concurrency: N accepts on one job yield exactly one engagement (DB-backed).
 */

/** An offered REMOTE job (no address) plus a pending offer to $provider. */
function offeredRemoteJobFor(User $provider, ?User $customer = null): JobOffer
{
    $customer ??= User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Offered)->create([
        'customer_party_id' => $customer->party_id,
    ]);

    return JobOffer::factory()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
        'amount_minor' => 750000,
    ]);
}

/** An offered ON-SITE job (with address) plus a pending offer to $provider. */
function offeredOnsiteJobFor(User $provider, int $riskTier = 1): JobOffer
{
    $customer = User::factory()->create();
    $address = Address::factory()->create(['party_id' => $customer->party_id]);
    $skill = Skill::factory()->create(['risk_tier' => $riskTier]);
    $job = Job::factory()->status(JobStatus::Offered)->create([
        'customer_party_id' => $customer->party_id,
        'address_id' => $address->id,
        'skill_id' => $skill->id,
    ]);

    return JobOffer::factory()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
        'amount_minor' => 900000,
    ]);
}

it('accepts a remote offer → engagement, job engaged, offer accepted, outbox emitted', function () {
    $provider = User::factory()->create(); // phone-verified → identity tier 1
    $offer = offeredRemoteJobFor($provider);

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/offers/{$offer->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.job_id', $offer->job_id)
        ->assertJsonPath('data.provider_party_id', $provider->party_id)
        ->assertJsonPath('data.agreed_amount.amount_minor', 750000)
        ->assertJsonPath('data.agreed_amount.currency', 'XAF');

    expect(Job::findOrFail($offer->job_id)->status)->toBe(JobStatus::Engaged)
        ->and($offer->fresh()->status)->toBe(OfferStatus::Accepted)
        ->and(Engagement::where('job_id', $offer->job_id)->count())->toBe(1)
        ->and(OutboxMessage::where('type', 'engagement.created')->count())->toBe(1);
});

it('auto-assigns the individual provider as engagement lead (P2-07)', function () {
    $provider = User::factory()->create();
    $offer = offeredRemoteJobFor($provider);

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/offers/{$offer->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.assignments.0.role', 'lead')
        ->assertJsonPath('data.assignments.0.worker_user_id', $provider->id);

    $assignment = Assignment::query()->firstOrFail();
    expect($assignment->role)->toBe(AssignmentRole::Lead)
        ->and($assignment->status)->toBe(AssignmentStatus::Assigned)
        ->and($assignment->worker_user_id)->toBe($provider->id)
        ->and($assignment->assigned_by_user_id)->toBe($provider->id);
});

it('supersedes every other pending offer on the job when one is accepted', function () {
    $provider = User::factory()->create();
    $offer = offeredRemoteJobFor($provider);
    // A second, rival pending offer on the same job.
    $rival = JobOffer::factory()->create(['job_id' => $offer->job_id]);

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/offers/{$offer->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    expect($rival->fresh()->status)->toBe(OfferStatus::Superseded);
});

it('gates an on-site accept on full ID — unverified provider gets precondition_unmet, not 403 (P2-06b)', function () {
    $provider = User::factory()->create(); // tier 1 (phone only); on-site needs tier 2
    $offer = offeredOnsiteJobFor($provider);

    Sanctum::actingAs($provider);
    $response = $this->postJson("/api/v1/offers/{$offer->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('error', 'precondition_unmet')
        ->assertJsonPath('capability', 'accept_paid_job')
        ->assertJsonPath('missing_fact', 'identity_verified')
        ->assertJsonPath('required_tier', 2);
    expect($response->status())->not->toBe(403);

    // Nothing happened: no engagement, job still offered, offer still pending.
    expect(Engagement::count())->toBe(0)
        ->and(Job::findOrFail($offer->job_id)->status)->toBe(JobStatus::Offered)
        ->and($offer->fresh()->status)->toBe(OfferStatus::Pending);
});

it('lets an ID-verified provider accept an on-site job (P2-06b)', function () {
    $provider = User::factory()->create();
    ProviderProfile::factory()->verified(2)->create(['party_id' => $provider->party_id]);
    $offer = offeredOnsiteJobFor($provider);

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/offers/{$offer->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    expect(Engagement::where('job_id', $offer->job_id)->count())->toBe(1);
});

it('lets only the offer’s own provider accept it', function () {
    $provider = User::factory()->create();
    $offer = offeredRemoteJobFor($provider);
    $intruder = User::factory()->create();

    Sanctum::actingAs($intruder);
    $this->postJson("/api/v1/offers/{$offer->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409)
        ->assertJsonPath('title', 'Offer no longer available');

    expect(Engagement::count())->toBe(0)
        ->and(Job::findOrFail($offer->job_id)->status)->toBe(JobStatus::Offered);
});

it('rejects accepting an offer whose job is already engaged', function () {
    $provider = User::factory()->create();
    $offer = offeredRemoteJobFor($provider);

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/offers/{$offer->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    // A second accept of the same offer is no longer possible.
    $this->postJson("/api/v1/offers/{$offer->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409);

    expect(Engagement::where('job_id', $offer->job_id)->count())->toBe(1);
});

it('yields exactly one engagement when many providers race to accept on one job (P2-06)', function () {
    // One offered remote job; 20 pending offers, one per provider.
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Offered)->create([
        'customer_party_id' => $customer->party_id,
    ]);

    /** @var array<int, array{user: User, offer: JobOffer}> $contenders */
    $contenders = [];
    foreach (range(1, 20) as $_) {
        $provider = User::factory()->create(); // phone-verified → tier 1, passes the remote gate
        $offer = JobOffer::factory()->create([
            'job_id' => $job->id,
            'provider_party_id' => $provider->party_id,
        ]);
        $contenders[] = ['user' => $provider, 'offer' => $offer];
    }

    // Each provider attempts to accept their own offer. The job-row lock + status guard let exactly
    // one win; the rest see the job is no longer offered. (Sequential here; the same guard + the
    // job_id UNIQUE + accepted-offer partial index make a truly-parallel run converge identically.)
    $action = app(AcceptOfferAction::class);
    $wins = 0;
    foreach ($contenders as $c) {
        try {
            $action->handle($c['user'], $c['offer']);
            $wins++;
        } catch (OfferNotAcceptable) {
            // lost the race — expected for the 19 losers
        }
    }

    expect($wins)->toBe(1)
        ->and(Engagement::where('job_id', $job->id)->count())->toBe(1)
        ->and(Job::findOrFail($job->id)->status)->toBe(JobStatus::Engaged)
        ->and(JobOffer::where('job_id', $job->id)->where('status', 'accepted')->count())->toBe(1);
});
