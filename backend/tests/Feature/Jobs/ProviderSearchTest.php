<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Jobs\ProviderSearch;
use App\Models\Address;
use App\Models\Job;
use App\Models\ProviderProfile;
use App\Models\ProviderSkill;
use App\Models\ServiceArea;
use App\Models\Skill;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/** A provider profile that offers $skill and covers (lat,lng) within a radius. */
function providerCovering(Skill $skill, float $lat, float $lng, int $radius = 5000): ProviderProfile
{
    $profile = ProviderProfile::factory()->create();
    ProviderSkill::factory()->create(['provider_profile_id' => $profile->id, 'skill_id' => $skill->id]);
    ServiceArea::factory()->at($lat, $lng, $radius)->create(['provider_profile_id' => $profile->id]);

    return $profile;
}

it('matches on-site jobs to providers whose service area COVERS the job, excluding those that do not', function () {
    $skill = Skill::factory()->create();
    $address = Address::factory()->at(3.8700, 11.5200)->create();
    $job = Job::factory()->status(JobStatus::Open)->create([
        'skill_id' => $skill->id, 'engagement_mode' => 'onsite', 'address_id' => $address->id,
    ]);

    $near = providerCovering($skill, 3.8710, 11.5205, 3000);   // covers the job (~150 m)
    $far = providerCovering($skill, 4.2000, 11.9000, 3000);    // ~50 km away, does not cover

    $results = app(ProviderSearch::class)->forJob($job)->pluck('id');

    expect($results)->toContain($near->id)->not->toContain($far->id);
});

it('REMOTE search returns providers OUTSIDE any radius (P2-04 acceptance)', function () {
    $skill = Skill::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create(['skill_id' => $skill->id]);

    // A provider whose service area is nowhere near — and one with no service area at all.
    $faraway = providerCovering($skill, 4.5000, 12.5000, 1000);
    $noArea = ProviderProfile::factory()->create();
    ProviderSkill::factory()->create(['provider_profile_id' => $noArea->id, 'skill_id' => $skill->id]);

    $results = app(ProviderSearch::class)->forJob($job)->pluck('id');

    expect($results)->toContain($faraway->id)->toContain($noArea->id);
});

it('only matches providers who offer the required skill', function () {
    $skill = Skill::factory()->create();
    $other = Skill::factory()->create();
    $job = Job::factory()->remote()->create(['skill_id' => $skill->id]);

    $match = ProviderProfile::factory()->create();
    ProviderSkill::factory()->create(['provider_profile_id' => $match->id, 'skill_id' => $skill->id]);
    $wrongSkill = ProviderProfile::factory()->create();
    ProviderSkill::factory()->create(['provider_profile_id' => $wrongSkill->id, 'skill_id' => $other->id]);

    $results = app(ProviderSearch::class)->forJob($job)->pluck('id');
    expect($results)->toContain($match->id)->not->toContain($wrongSkill->id);
});

it('excludes suspended providers and, when required, unverified ones', function () {
    $skill = Skill::factory()->create();
    $job = Job::factory()->remote()->create(['skill_id' => $skill->id, 'requires_verified_provider' => true]);

    $verified = ProviderProfile::factory()->verified(2)->create();
    ProviderSkill::factory()->create(['provider_profile_id' => $verified->id, 'skill_id' => $skill->id]);
    $unverified = ProviderProfile::factory()->create(['verification_tier' => 0]);
    ProviderSkill::factory()->create(['provider_profile_id' => $unverified->id, 'skill_id' => $skill->id]);
    $suspended = ProviderProfile::factory()->verified(2)->create(['suspended_at' => now()]);
    ProviderSkill::factory()->create(['provider_profile_id' => $suspended->id, 'skill_id' => $skill->id]);

    $results = app(ProviderSearch::class)->forJob($job)->pluck('id');
    expect($results)->toContain($verified->id)
        ->not->toContain($unverified->id)
        ->not->toContain($suspended->id);
});

it('exposes the search over the API to the job owner only', function () {
    $skill = Skill::factory()->create();
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create([
        'skill_id' => $skill->id, 'customer_party_id' => $customer->party_id,
    ]);
    providerCovering($skill, 3.87, 11.52);

    Sanctum::actingAs($customer);
    $this->getJson("/api/v1/jobs/{$job->id}/providers")->assertOk()->assertJsonStructure(['data']);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/jobs/{$job->id}/providers")->assertForbidden();
});
