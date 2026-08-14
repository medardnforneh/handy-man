<?php

declare(strict_types=1);

use App\Models\Block;
use App\Models\ProviderProfile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * `GET /blocks` labels each row (P6-07).
 *
 * A list of bare UUIDs is not one a person can act on — "unblock this id" is not a decision anyone
 * can make — so the row carries what the blocker saw when they blocked: the provider headline where
 * there is one, the display name otherwise, and never more than that (P2-03).
 */
it('labels a blocked provider with their public headline', function () {
    $me = User::factory()->create();
    $them = User::factory()->create();
    ProviderProfile::factory()->create(['party_id' => $them->party_id, 'headline' => 'Froid et climatisation']);
    Block::query()->create([
        'party_id' => $me->party_id,
        'blocked_party_id' => $them->party_id,
        'created_at' => now(),
    ]);

    Sanctum::actingAs($me);
    $this->getJson('/api/v1/blocks')
        ->assertOk()
        ->assertJsonPath('data.0.blocked_label', 'Froid et climatisation')
        ->assertJsonPath('data.0.blocked_party_id', $them->party_id);
});

it('falls back to the display name for a party with no provider profile', function () {
    $me = User::factory()->create();
    $them = User::factory()->create();
    $them->party->update(['display_name' => 'Jean Mbarga']);
    Block::query()->create([
        'party_id' => $me->party_id,
        'blocked_party_id' => $them->party_id,
        'created_at' => now(),
    ]);

    Sanctum::actingAs($me);
    $this->getJson('/api/v1/blocks')->assertOk()->assertJsonPath('data.0.blocked_label', 'Jean Mbarga');
});

it('lists only the caller’s own blocks', function () {
    $me = User::factory()->create();
    $someoneElse = User::factory()->create();
    $them = User::factory()->create();
    Block::query()->create(['party_id' => $someoneElse->party_id, 'blocked_party_id' => $them->party_id, 'created_at' => now()]);

    Sanctum::actingAs($me);
    $this->getJson('/api/v1/blocks')->assertOk()->assertJsonCount(0, 'data');
});
