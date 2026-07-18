<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function grantLocationConsent(User $user): void
{
    Consent::factory()->purpose('location_tracking')->create(['user_id' => $user->id, 'granted' => true]);
}

function addressPayload(array $overrides = []): array
{
    return array_merge([
        'label' => 'Maison',
        'line1' => '123 Rue de Bastos',
        'quarter' => 'Bastos',
        'city' => 'Yaoundé',
        'region' => 'Centre',
        'latitude' => 3.8850,
        'longitude' => 11.5210,
    ], $overrides);
}

it('blocks creating an address without location_tracking consent', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/addresses', addressPayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(403)
        ->assertJsonPath('title', 'Consent required')
        ->assertJsonPath('missing_purpose', 'location_tracking');
});

it('creates an address with location consent and round-trips the coordinates', function () {
    $user = User::factory()->create();
    grantLocationConsent($user);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/addresses', addressPayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.city', 'Yaoundé')
        ->assertJsonPath('data.latitude', 3.885)
        ->assertJsonPath('data.longitude', 11.521);

    expect(Address::where('party_id', $user->party_id)->count())->toBe(1);
});

it('finds addresses within a radius via ST_DWithin and excludes those beyond', function () {
    // Reference point: Bastos, Yaoundé.
    $lat = 3.8900;
    $lng = 11.5150;

    $near = Address::factory()->at(3.8905, 11.5155)->create();   // ~70 m away
    $mid = Address::factory()->at(3.9100, 11.5350)->create();    // ~3 km away
    $far = Address::factory()->at(4.0500, 11.5150)->create();    // ~18 km away

    $within2km = Address::query()->near($lat, $lng, 2000)->pluck('id');
    expect($within2km)->toContain($near->id)
        ->not->toContain($mid->id)
        ->not->toContain($far->id);

    $within5km = Address::query()->near($lat, $lng, 5000)->pluck('id');
    expect($within5km)->toContain($near->id)->toContain($mid->id)->not->toContain($far->id);
});

it('has a GIST spatial index backing the proximity search', function () {
    $index = DB::selectOne(
        "SELECT indexdef FROM pg_indexes WHERE tablename = 'addresses' AND indexdef ILIKE '%USING gist%'"
    );

    expect($index)->not->toBeNull();
    expect($index->indexdef)->toContain('point');
});

it('only lists the user own addresses', function () {
    $user = User::factory()->create();
    grantLocationConsent($user);
    Address::factory()->create(['party_id' => $user->party_id]);
    Address::factory()->create(); // someone else

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/addresses')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
