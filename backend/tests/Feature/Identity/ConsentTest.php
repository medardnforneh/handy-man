<?php

declare(strict_types=1);

use App\Domain\Identity\Consent\ConsentGuard;
use App\Domain\Identity\ConsentRequiredException;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function consentPost($test, array $body)
{
    return $test->postJson('/api/v1/consents', $body, ['Idempotency-Key' => (string) Str::uuid()]);
}

it('records a granular, versioned consent with the locale it was presented in', function () {
    $user = User::factory()->anglophone()->create();
    Sanctum::actingAs($user);

    consentPost($this, ['purpose' => 'location_tracking', 'granted' => true, 'presented_locale' => 'en'])
        ->assertStatus(201)
        ->assertJsonPath('purpose', 'location_tracking')
        ->assertJsonPath('granted', true)
        ->assertJsonPath('presented_locale', 'en');

    $this->assertDatabaseHas('consents', [
        'user_id' => $user->id, 'purpose' => 'location_tracking', 'granted' => true, 'presented_locale' => 'en',
    ]);
});

it('reports current state as the latest event per purpose', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    consentPost($this, ['purpose' => 'location_tracking', 'granted' => true])->assertStatus(201);
    consentPost($this, ['purpose' => 'location_tracking', 'granted' => false])->assertStatus(201); // revoke

    $this->getJson('/api/v1/consents')
        ->assertOk()
        ->assertJsonPath('data.location_tracking', false) // latest wins
        ->assertJsonPath('data.marketing', false);        // never granted → false

    // Append-only: both events are retained.
    expect(Consent::where('user_id', $user->id)->where('purpose', 'location_tracking')->count())->toBe(2);
});

it('blocks a location write once location_tracking is revoked', function () {
    $user = User::factory()->create();
    $guard = app(ConsentGuard::class);

    // Grant → guard passes.
    Consent::factory()->purpose('location_tracking')->create(['user_id' => $user->id, 'granted' => true]);
    expect(fn () => $guard->assertGranted($user, 'location_tracking'))->not->toThrow(ConsentRequiredException::class);

    // Revoke (newer event) → guard blocks the geo write.
    Consent::factory()->purpose('location_tracking')->revoked()->create([
        'user_id' => $user->id, 'created_at' => now()->addSecond(),
    ]);
    expect(fn () => $guard->assertGranted($user, 'location_tracking'))->toThrow(ConsentRequiredException::class);
});

it('surfaces a consent_required problem+json over the API', function () {
    // A throwaway route guarded on location_tracking consent.
    Route::middleware(['api', 'auth:sanctum'])
        ->post('/api/v1/_test/geo', function (ConsentGuard $guard) {
            $guard->assertGranted(request()->user(), 'location_tracking');

            return response()->json(['ok' => true]);
        });

    $user = User::factory()->create(); // no consent
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/_test/geo', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(403)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Consent required')
        ->assertJsonPath('missing_purpose', 'location_tracking');
});

it('lets a user set locale=en and comms_locale=fr independently (P1-05b)', function () {
    $user = User::factory()->create(['locale' => 'fr', 'comms_locale' => 'fr']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me/preferences',
        ['locale' => 'en', 'comms_locale' => 'fr'],
        ['Idempotency-Key' => (string) Str::uuid()],
    )
        ->assertOk()
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.comms_locale', 'fr');

    expect($user->refresh()->locale)->toBe('en')
        ->and($user->comms_locale)->toBe('fr');
});
