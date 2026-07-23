<?php

declare(strict_types=1);

use App\Domain\Notifications\FakeSmsSender;
use App\Domain\Safety\Actions\ResolveSafetyAlert;
use App\Models\EmergencyContact;
use App\Models\OutboxMessage;
use App\Models\SafetyAlert;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P6-04 acceptance (doc 04): the panic button. One request raises a safety alert, texts the user's
 * emergency contacts, and alerts staff — all server-side, so it works with the app backgrounded.
 */
it('raises an alert, texts every emergency contact, and alerts staff', function () {
    $user = User::factory()->create();
    EmergencyContact::factory()->create(['user_id' => $user->id, 'phone_e164' => '+237690000001']);
    EmergencyContact::factory()->create(['user_id' => $user->id, 'phone_e164' => '+237690000002']);

    Sanctum::actingAs($user);
    $this->postJson('/api/v1/safety/panic', [
        'latitude' => 3.848, 'longitude' => 11.502, 'note' => 'Being followed.',
    ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'panic')
        ->assertJsonPath('data.status', 'open');

    expect(SafetyAlert::query()->where('user_id', $user->id)->where('kind', 'panic')->count())->toBe(1);

    /** @var FakeSmsSender $sms */
    $sms = app(FakeSmsSender::class);
    expect($sms->recipients())->toContain('+237690000001')->toContain('+237690000002');

    expect(OutboxMessage::query()->where('type', 'safety.alert_raised')->exists())->toBeTrue();
});

it('still records the alert and alerts staff when there are no emergency contacts', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);
    $this->postJson('/api/v1/safety/panic', [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    expect(SafetyAlert::query()->count())->toBe(1)
        ->and(app(FakeSmsSender::class)->sent)->toBeEmpty()
        ->and(OutboxMessage::query()->where('type', 'safety.alert_raised')->exists())->toBeTrue();
});

it('manages emergency contacts (add, list, remove) and guards ownership', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $id = $this->postJson('/api/v1/emergency-contacts', ['name' => 'Sister', 'phone_e164' => '+237690123456'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->json('data.id');

    $this->getJson('/api/v1/emergency-contacts')->assertOk()->assertJsonCount(1, 'data');

    // Another user cannot delete this contact.
    Sanctum::actingAs(User::factory()->create());
    $this->deleteJson("/api/v1/emergency-contacts/{$id}", [], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();

    Sanctum::actingAs($user);
    $this->deleteJson("/api/v1/emergency-contacts/{$id}", [], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    $this->getJson('/api/v1/emergency-contacts')->assertJsonCount(0, 'data');
});

it('rejects an invalid emergency contact phone (422)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/emergency-contacts', ['name' => 'X', 'phone_e164' => '690123456'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('resolves an alert, attributed to the admin', function () {
    $alert = SafetyAlert::factory()->create();
    $admin = User::factory()->create();

    app(ResolveSafetyAlert::class)->handle($alert, $admin);

    expect($alert->fresh()->status)->toBe('resolved')
        ->and($alert->fresh()->resolved_by_user_id)->toBe($admin->id)
        ->and($alert->fresh()->resolved_at)->not->toBeNull();
});
