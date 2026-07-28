<?php

declare(strict_types=1);

use App\Domain\Access\Facts\Fact;
use App\Domain\Access\Facts\FactDeriver;
use App\Models\Consent;
use App\Models\ProviderProfile;
use App\Models\ProviderSkill;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\SkillsSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function providerPost($test, string $uri, array $body)
{
    return $test->postJson($uri, $body, ['Idempotency-Key' => (string) Str::uuid()]);
}

it('lets a brand-new user create a provider profile with zero prior grants (doc 10)', function () {
    $user = User::factory()->create(); // no roles, no facts
    Sanctum::actingAs($user);

    providerPost($this, '/api/v1/provider/profile', ['headline' => 'Plombier expérimenté'])
        ->assertOk()
        ->assertJsonPath('data.headline', 'Plombier expérimenté');

    expect(ProviderProfile::where('party_id', $user->party_id)->exists())->toBeTrue()
        ->and(app(FactDeriver::class)->derive($user, Fact::HasProviderProfile)->satisfied)->toBeTrue();
});

it('blocks listing a skill until a provider profile exists (precondition_unmet, not 403)', function () {
    $this->seed(SkillsSeeder::class);
    $skill = Skill::where('is_leaf', true)->firstOrFail();

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // No profile yet → precondition_unmet naming the missing fact + a resolve deep link.
    providerPost($this, '/api/v1/provider/skills', ['skill_id' => $skill->id, 'price_model' => 'fixed'])
        ->assertStatus(409)
        ->assertJsonPath('error', 'precondition_unmet')
        ->assertJsonPath('missing_fact', 'has_provider_profile')
        ->assertJsonPath('resolve.deep_link', '/provider/profile');
});

it('lists a skill once a profile exists and flips the skill_listed fact', function () {
    $this->seed(SkillsSeeder::class);
    $skill = Skill::where('is_leaf', true)->firstOrFail();

    $user = User::factory()->create();
    Sanctum::actingAs($user);
    providerPost($this, '/api/v1/provider/profile', [])->assertOk();

    providerPost($this, '/api/v1/provider/skills', [
        'skill_id' => $skill->id, 'price_model' => 'fixed', 'rate_minor' => 750000,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.skill_id', $skill->id)
        ->assertJsonPath('data.rate.amount_minor', 750000)
        ->assertJsonPath('data.rate.currency', 'XAF');

    expect(app(FactDeriver::class)->derive($user, Fact::SkillListed)->satisfied)->toBeTrue();
    expect(ProviderSkill::query()->count())->toBe(1);
});

it('sets a service area only with location_tracking consent', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    providerPost($this, '/api/v1/provider/profile', [])->assertOk();

    // Without consent → blocked.
    providerPost($this, '/api/v1/provider/service-areas', ['latitude' => 3.87, 'longitude' => 11.52, 'radius_m' => 5000])
        ->assertStatus(403)
        ->assertJsonPath('missing_purpose', 'location_tracking');

    // Grant consent → allowed.
    Consent::factory()->purpose('location_tracking')->create(['user_id' => $user->id, 'granted' => true]);

    providerPost($this, '/api/v1/provider/service-areas', ['latitude' => 3.87, 'longitude' => 11.52, 'radius_m' => 5000])
        ->assertStatus(201)
        ->assertJsonPath('data.radius_m', 5000);
});

it('derives identity_verified as the stronger of the phone check and the ID tier', function () {
    // Nothing proven yet → tier 0.
    $user = User::factory()->unverified()->create();
    expect(app(FactDeriver::class)->derive($user, Fact::IdentityVerified)->level)->toBe(0);

    // A confirmed phone alone satisfies the lighter (remote) check → tier 1.
    $user->forceFill(['phone_verified_at' => now()])->save();
    app(FactDeriver::class)->forget($user, Fact::IdentityVerified);
    expect(app(FactDeriver::class)->derive($user, Fact::IdentityVerified)->level)->toBe(1);

    // Full ID verification raises it to the on-site tier → 2.
    ProviderProfile::factory()->verified(2)->create(['party_id' => $user->party_id]);
    app(FactDeriver::class)->forget($user, Fact::IdentityVerified);
    expect(app(FactDeriver::class)->derive($user, Fact::IdentityVerified)->level)->toBe(2);
});

it('returns the PARTY id and bilingual skill labels on the provider’s own profile', function () {
    $user = User::factory()->create(['locale' => 'fr']);
    $skill = Skill::factory()->create(['name_en' => 'Plumbing', 'name_fr' => 'Plomberie', 'is_leaf' => true]);

    Sanctum::actingAs($user);
    providerPost($this, '/api/v1/provider/profile', ['headline' => 'Atelier Nkeng'])->assertOk();
    providerPost($this, '/api/v1/provider/skills', [
        'skill_id' => $skill->id, 'price_model' => 'quote_only',
    ])->assertCreated();

    // `party_id` is the handle every OTHER endpoint takes (offers, metrics, reviews) — the profile
    // row's own `id` is not interchangeable with it, and a client that confuses them sends garbage.
    $response = $this->getJson('/api/v1/provider/profile')->assertOk();
    $response->assertJsonPath('data.party_id', $user->party_id)
        ->assertJsonPath('data.display_name', $user->party->display_name);

    expect($response->json('data.id'))->not->toBe($user->party_id);
});

it('labels a provider’s skills in the caller’s own locale, with ?locale= winning', function () {
    // The user's stored locale must beat Accept-Language: SetLocale is registered on the WEB group
    // only, so without RequestLocale an API caller would silently get the app default instead.
    $user = User::factory()->create(['locale' => 'fr']);
    $skill = Skill::factory()->create(['name_en' => 'Plumbing', 'name_fr' => 'Plomberie', 'is_leaf' => true]);

    Sanctum::actingAs($user);
    providerPost($this, '/api/v1/provider/profile', ['headline' => 'Atelier Nkeng'])->assertOk();
    providerPost($this, '/api/v1/provider/skills', [
        'skill_id' => $skill->id, 'price_model' => 'quote_only',
    ])->assertCreated();

    $this->getJson('/api/v1/provider/profile', ['Accept-Language' => 'en-GB,en;q=0.9'])
        ->assertOk()
        ->assertJsonPath('data.skills.0.name', 'Plomberie');

    // An explicit query parameter still wins over everything.
    $this->getJson('/api/v1/provider/profile?locale=en')
        ->assertOk()
        ->assertJsonPath('data.skills.0.name', 'Plumbing');

});

it('falls back to Accept-Language when there is no session to read a preference from', function () {
    // `users.locale` is NOT NULL, so that branch is only reachable unauthenticated — which is
    // exactly the public taxonomy endpoint.
    Skill::factory()->create(['name_en' => 'Trades', 'name_fr' => 'Métiers', 'is_leaf' => false]);

    $this->getJson('/api/v1/skills', ['Accept-Language' => 'fr-FR,fr;q=0.9'])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Métiers']);

    $this->getJson('/api/v1/skills', ['Accept-Language' => 'en-GB,en;q=0.9'])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Trades']);
});
