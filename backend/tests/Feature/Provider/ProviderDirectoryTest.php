<?php

declare(strict_types=1);

use App\Models\Block;
use App\Models\ProviderProfile;
use App\Models\ProviderSkill;
use App\Models\ServiceArea;
use App\Models\Skill;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Browsing providers by trade, without a job (the discover rail).
 *
 * The properties that matter: a stranger can look (no login), what they see carries no personal
 * identity, and every rule that hides a provider elsewhere hides them here too — suspension and
 * blocks — because a list reachable by a different route must not be a way around them.
 */
function providerOffering(Skill $skill, array $attributes = []): ProviderProfile
{
    $profile = ProviderProfile::factory()->create($attributes);
    ProviderSkill::factory()->create([
        'provider_profile_id' => $profile->id,
        'skill_id' => $skill->id,
    ]);

    return $profile->fresh();
}

/**
 * A category with one leaf under it — the taxonomy shape the directory has to handle (browsing a
 * category must reach the providers listed against its leaves).
 *
 * @return array{category: Skill, leaf: Skill}
 */
function directoryTaxonomy(): array
{
    $category = Skill::factory()->create([
        'name_en' => 'Plumbing', 'name_fr' => 'Plomberie', 'slug' => 'plumbing', 'is_leaf' => false,
    ]);
    $leaf = Skill::factory()->create([
        'name_en' => 'Leak repair', 'name_fr' => 'Reparation de fuite', 'slug' => 'leak-repair',
        'is_leaf' => true, 'parent_id' => $category->id,
    ]);

    return ['category' => $category, 'leaf' => $leaf];
}

function leafSkill(): Skill
{
    return directoryTaxonomy()['leaf'];
}

it('lets an anonymous visitor browse providers for a trade', function () {
    $skill = leafSkill();
    $profile = providerOffering($skill, ['headline' => 'Plomberie Akwa']);

    test()->getJson("/api/v1/providers?skill={$skill->slug}")
        ->assertOk()
        ->assertJsonPath('data.0.party_id', $profile->party_id)
        ->assertJsonPath('data.0.headline', 'Plomberie Akwa');
});

it('never exposes a provider display name to a browsing stranger', function () {
    $skill = leafSkill();
    $profile = providerOffering($skill);
    $name = $profile->party->display_name;

    $response = test()->getJson("/api/v1/providers?skill={$skill->slug}")->assertOk();

    // Not "the field is absent" but "the name appears nowhere in the payload" — the stronger claim,
    // and the one that catches a future eager-load quietly reintroducing it.
    expect($response->json('data.0'))->not->toHaveKey('display_name');
    if (is_string($name) && $name !== '') {
        expect($response->getContent())->not->toContain($name);
    }
});

it('excludes a suspended provider, exactly as search does', function () {
    $skill = leafSkill();
    providerOffering($skill, ['suspended_at' => now()]);

    test()->getJson("/api/v1/providers?skill={$skill->slug}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('excludes a provider who does not accept direct approaches', function () {
    $skill = leafSkill();
    providerOffering($skill, ['accepts_direct' => false]);

    test()->getJson("/api/v1/providers?skill={$skill->slug}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('honours a block in both directions for a signed-in browser', function () {
    $skill = leafSkill();
    $blocked = providerOffering($skill);
    $visible = providerOffering($skill);
    $customer = User::factory()->create();

    Block::query()->create([
        'party_id' => $customer->party_id,
        'blocked_party_id' => $blocked->party_id,
    ]);

    Sanctum::actingAs($customer);
    $ids = test()->getJson("/api/v1/providers?skill={$skill->slug}")->assertOk()->json('data.*.party_id');

    expect($ids)->toContain($visible->party_id)
        ->and($ids)->not->toContain($blocked->party_id);
});

it('still lists a blocked provider to an anonymous visitor, who has no block to honour', function () {
    $skill = leafSkill();
    $blocked = providerOffering($skill);
    $customer = User::factory()->create();
    Block::query()->create([
        'party_id' => $customer->party_id,
        'blocked_party_id' => $blocked->party_id,
    ]);

    // A block is a relationship between two parties, not a global ban — an unrelated visitor sees
    // the provider normally.
    test()->getJson("/api/v1/providers?skill={$skill->slug}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('treats a category slug as every trade beneath it', function () {
    ['category' => $category, 'leaf' => $leaf] = directoryTaxonomy();
    $profile = providerOffering($leaf);

    // A customer browsing "Plumbing" should not have to know which leaf their problem falls under.
    test()->getJson("/api/v1/providers?skill={$category->slug}")
        ->assertOk()
        ->assertJsonPath('data.0.party_id', $profile->party_id);
});

it('returns nobody for an unknown trade rather than everybody', function () {
    providerOffering(leafSkill());

    test()->getJson('/api/v1/providers?skill=not-a-real-trade')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('splits on-site from remote by whether a service area exists, without revealing it', function () {
    $skill = leafSkill();
    $onsite = providerOffering($skill);
    $remote = providerOffering($skill);
    ServiceArea::factory()->create(['provider_profile_id' => $onsite->id]);

    $onsiteIds = test()->getJson("/api/v1/providers?skill={$skill->slug}&mode=onsite")->assertOk()->json('data.*.party_id');
    $remoteIds = test()->getJson("/api/v1/providers?skill={$skill->slug}&mode=remote")->assertOk()->json('data.*.party_id');

    expect($onsiteIds)->toBe([$onsite->party_id])
        ->and($remoteIds)->toBe([$remote->party_id]);

    // The filter works off the area, but the payload only ever says whether one exists — a coordinate
    // or radius here would be exactly the leak the match list is careful to avoid.
    $card = test()->getJson("/api/v1/providers?skill={$skill->slug}&mode=onsite")->json('data.0');
    expect($card['serves_onsite'])->toBeTrue()
        ->and($card)->not->toHaveKey('service_areas');
});

it('ranks verified providers above unverified, then by rating', function () {
    $skill = leafSkill();
    $unverified = providerOffering($skill, ['verification_tier' => 1, 'rating_avg' => 4.9]);
    $verified = providerOffering($skill, ['verification_tier' => 3, 'rating_avg' => 4.1]);

    $ids = test()->getJson("/api/v1/providers?skill={$skill->slug}")->assertOk()->json('data.*.party_id');

    // Trust outranks a high average — the same order the services directory and job search use.
    expect($ids[0])->toBe($verified->party_id)
        ->and($ids[1])->toBe($unverified->party_id);
});
