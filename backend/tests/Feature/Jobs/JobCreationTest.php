<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Models\Address;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function makeCustomerWithAddress(): array
{
    $user = User::factory()->create();
    $address = Address::factory()->create(['party_id' => $user->party_id]);

    return [$user, $address];
}

function jobPost($test, array $body)
{
    return $test->postJson('/api/v1/jobs', $body, ['Idempotency-Key' => (string) Str::uuid()]);
}

it('creates an on-site job with photos; the owner sees the full exact address', function () {
    [$user, $address] = makeCustomerWithAddress();
    $skill = Skill::factory()->create();
    Sanctum::actingAs($user);

    $res = jobPost($this, [
        'skill_id' => $skill->id,
        'engagement_mode' => 'onsite',
        'address_id' => $address->id,
        'title' => 'Fuite sous l\'évier',
        'photos' => ['jobs/a.jpg', 'jobs/b.jpg'],
    ]);

    $res->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonCount(2, 'data.photos')
        // Owner sees the exact address.
        ->assertJsonPath('data.location.line1', $address->line1);

    // Compare against the DB-round-tripped coordinate (PostGIS double precision) rather than the
    // in-memory factory value, which can differ in the last float digit.
    $address->refresh();
    expect($res->json('data.location.latitude'))->toEqualWithDelta($address->point->latitude, 1e-6)
        ->and($res->json('data.reference'))->toStartWith('JOB-');
});

it('creates a remote job with no location', function () {
    $user = User::factory()->create();
    $skill = Skill::factory()->create();
    Sanctum::actingAs($user);

    jobPost($this, ['skill_id' => $skill->id, 'engagement_mode' => 'remote', 'title' => 'Consultation à distance'])
        ->assertCreated()
        ->assertJsonPath('data.location', null);
});

it('PII-MINIMISES: a pre-engagement provider never sees the exact address (P2-03 acceptance)', function () {
    [$customer, $address] = makeCustomerWithAddress();
    $skill = Skill::factory()->create();
    $job = Job::factory()->status(JobStatus::Open)->create([
        'customer_party_id' => $customer->party_id,
        'address_id' => $address->id,
        'engagement_mode' => 'onsite',
    ]);

    // A different user (a browsing provider) fetches the open job.
    $provider = User::factory()->create();
    Sanctum::actingAs($provider);

    $res = $this->getJson("/api/v1/jobs/{$job->id}")->assertOk();

    // Coarse area is visible…
    $res->assertJsonPath('data.location.city', $address->city)
        ->assertJsonPath('data.location.quarter', $address->quarter);

    // …but the exact address is NOT.
    expect($res->json('data.location'))->not->toHaveKey('line1')
        ->and($res->json('data.location'))->not->toHaveKey('latitude')
        ->and($res->json('data.location'))->not->toHaveKey('longitude')
        ->and($res->json('data.location'))->not->toHaveKey('landmark_note');
});

it('hides a draft job from everyone but its owner', function () {
    [$customer, $address] = makeCustomerWithAddress();
    $job = Job::factory()->status(JobStatus::Draft)->create([
        'customer_party_id' => $customer->party_id, 'address_id' => $address->id,
    ]);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/jobs/{$job->id}")->assertNotFound();
});

it('publishes a draft job (draft → open)', function () {
    [$user, $address] = makeCustomerWithAddress();
    $job = Job::factory()->status(JobStatus::Draft)->create([
        'customer_party_id' => $user->party_id, 'address_id' => $address->id,
    ]);

    Sanctum::actingAs($user);
    $this->postJson("/api/v1/jobs/{$job->id}/publish", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.status', 'open');

    expect($job->fresh()->published_at)->not->toBeNull();
});

it('rejects a job whose address belongs to someone else', function () {
    $user = User::factory()->create();
    $otherAddress = Address::factory()->create(); // different party
    $skill = Skill::factory()->create();
    Sanctum::actingAs($user);

    jobPost($this, [
        'skill_id' => $skill->id, 'engagement_mode' => 'onsite',
        'address_id' => $otherAddress->id, 'title' => 'x',
    ])->assertStatus(422);
});
