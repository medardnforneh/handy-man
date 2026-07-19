<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\Consent;
use App\Models\Device;
use App\Models\OutboxMessage;
use App\Models\Party;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

it('exports the user personal data (DSAR)', function () {
    $user = User::factory()->create(['phone_e164' => '+237699555444']);
    Consent::factory()->purpose('terms')->create(['user_id' => $user->id]);
    Device::factory()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/me/data-export')
        ->assertOk()
        ->assertJsonPath('data.identity.phone_e164', '+237699555444')
        ->assertJsonStructure(['data' => ['identity', 'addresses', 'consents', 'devices']])
        ->assertJsonCount(1, 'data.consents')
        ->assertJsonCount(1, 'data.devices');
});

it('crypto-shreds on erasure: destroys the key, keeps the party row and its FKs', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $party = $user->party;
    $partyId = $party->id;

    // Data that must be scrubbed, plus a party-referencing row that must SURVIVE (stands in for a
    // future ledger FK).
    Address::factory()->create(['party_id' => $partyId]);
    Device::factory()->create(['user_id' => $user->id]);
    $profile = ProviderProfile::factory()->create(['party_id' => $partyId, 'bio' => 'Real bio']);

    expect($party->data_key)->not->toBeNull(); // a key was minted

    Sanctum::actingAs($user);
    $this->deleteJson('/api/v1/me', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk();

    // The party ROW survives (its id anchors ledger FKs) but is tombstoned and its key destroyed.
    $party = Party::findOrFail($partyId);
    expect($party->isErased())->toBeTrue()
        ->and($party->data_key)->toBeNull()               // key crypto-shredded
        ->and($party->display_name)->toBe('Utilisateur supprimé')
        ->and($party->status)->toBe('closed');

    // Identifiers nulled / tombstoned.
    $user->refresh();
    expect($user->email)->toBeNull()
        ->and($user->phone_e164)->toBe('erased-'.$partyId)
        ->and($user->status)->toBe('closed');

    // PII rows gone; the party-referencing profile SURVIVES with a valid FK (free text scrubbed).
    expect(Address::where('party_id', $partyId)->count())->toBe(0)
        ->and(Device::where('user_id', $user->id)->count())->toBe(0);
    $profile->refresh();
    expect($profile->party_id)->toBe($partyId)  // FK still valid → ledger FKs would survive
        ->and($profile->bio)->toBeNull();        // free-text PII scrubbed

    // An outbox event announces the erasure for downstream cleanup.
    expect(OutboxMessage::where('type', 'party.erased')->count())->toBe(1);
});

it('makes the erased user unrecoverable as an identity', function () {
    $user = User::factory()->create(['phone_e164' => '+237699000001']);
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/me', [], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    // The original phone no longer resolves to anyone.
    expect(User::where('phone_e164', '+237699000001')->exists())->toBeFalse();
});
