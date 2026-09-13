<?php

declare(strict_types=1);

use App\Domain\Verification\VerificationStorage;
use App\Models\Address;
use App\Models\Consent;
use App\Models\Device;
use App\Models\EmergencyContact;
use App\Models\OutboxMessage;
use App\Models\Party;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

it('destroys the identity papers and the emergency contacts with the person', function () {
    Storage::fake('verification');
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('id.jpg', 'PLAINTEXT-ID');
    [$path, $sha] = app(VerificationStorage::class)->store($file);
    $doc = VerificationDocument::factory()->create(['party_id' => $user->party_id, 'storage_path' => $path, 'sha256' => $sha]);
    EmergencyContact::factory()->create(['user_id' => $user->id, 'phone_e164' => '+237690000009']);
    Storage::disk('verification')->assertExists($path);

    Sanctum::actingAs($user);
    $this->deleteJson('/api/v1/me', [], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    // The bytes are gone from the bucket; the audit row remains, marked purged.
    Storage::disk('verification')->assertMissing($path);
    expect($doc->refresh()->purged_at)->not->toBeNull()
        ->and($doc->reviewed_at)->toBeNull()
        // Other people's numbers, held only for this person's safety, go with them.
        ->and(EmergencyContact::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('makes the erased user unrecoverable as an identity', function () {
    $user = User::factory()->create(['phone_e164' => '+237699000001']);
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/me', [], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    // The original phone no longer resolves to anyone.
    expect(User::where('phone_e164', '+237699000001')->exists())->toBeFalse();
});
