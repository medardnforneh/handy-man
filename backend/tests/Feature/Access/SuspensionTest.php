<?php

declare(strict_types=1);

use App\Domain\Access\AccountStatus;
use App\Domain\Access\AccountStatusMachine;
use App\Domain\Access\Actions\SetAccountStatus;
use App\Domain\Access\IllegalAccountTransition;
use App\Domain\Access\Role;
use App\Domain\Identity\Actions\IssueAuthTokens;
use App\Domain\Identity\Actions\RotateRefreshToken;
use App\Domain\Identity\Otp\OtpSender;
use App\Models\ActivityLog;
use App\Models\OutboxMessage;
use App\Models\ProviderProfile;
use App\Models\RefreshToken;
use App\Models\User;
use Database\Seeders\StaffRolesSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeOtpSender;

/**
 * Suspension, which until now could not be reached and would not have mattered if it were.
 *
 * `user_status` has carried `suspended` on both `parties.status` and `users.status` since the first
 * migration. Nothing in the admin panel wrote either column, and nothing in the app read them — so
 * "suspend" meant, at most, `provider_profiles.suspended_at` hiding someone from search while they
 * carried on signing in, accepting offers, messaging customers and requesting payouts. These tests
 * are the evidence that it now means what the word means.
 */
beforeEach(function () {
    $this->fakeOtp = new FakeOtpSender;
    $this->app->instance(OtpSender::class, $this->fakeOtp);
});

/** A staff actor for the attribution the Action records. */
function suspender(): User
{
    return User::factory()->create();
}

it('bars the account everywhere: party, users, sessions and discovery', function () {
    $user = User::factory()->create();
    $profile = ProviderProfile::factory()->create(['party_id' => $user->party_id]);
    $tokens = app(IssueAuthTokens::class)->handle($user, deviceId: 'device-1');

    app(SetAccountStatus::class)->suspend($user->party, suspender(), 'threatened a customer');

    expect($user->party->refresh()->status)->toBe('suspended')
        ->and($user->refresh()->status)->toBe('suspended')
        // The discovery half, which is all suspension used to be.
        ->and($profile->refresh()->suspended_at)->not->toBeNull()
        // And the authentication half, which it was not: every session, both halves of it.
        ->and($user->tokens()->count())->toBe(0)
        ->and(RefreshToken::where('user_id', $user->getKey())->whereNull('revoked_at')->count())->toBe(0);

    // The bearer they are still holding is refused rather than working out its fifteen minutes.
    $this->withHeader('Authorization', 'Bearer '.$tokens->accessToken)
        ->getJson('/api/v1/follow-ups')
        ->assertStatus(403)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Account suspended')
        ->assertJsonPath('account_status', 'suspended');
});

it('refuses a suspended account a new session, with the right code and a correct code', function () {
    $phone = '+237699404040';
    $user = User::factory()->create(['phone_e164' => $phone]);
    app(SetAccountStatus::class)->suspend($user->party, suspender(), 'fraud');

    // The OTP is still SENT — refusing at /otp/request would tell anyone who typed a number
    // whether it is suspended, and that endpoint answers 202 either way by design.
    $this->postJson('/api/v1/auth/otp/request',
        ['phone_e164' => $phone, 'purpose' => 'login'],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertStatus(202);

    // The refusal is at verify, where it belongs, and it is a 403 — not a 401, which the app would
    // answer by sending them back through these same screens to be refused again.
    $this->postJson('/api/v1/auth/otp/verify',
        ['phone_e164' => $phone, 'code' => $this->fakeOtp->codeFor($phone), 'purpose' => 'login'],
        ['Idempotency-Key' => (string) Str::uuid()],
    )
        ->assertStatus(403)
        ->assertJsonPath('account_status', 'suspended');
});

it('refuses to rotate a refresh token for a suspended account', function () {
    $user = User::factory()->create();
    $tokens = app(IssueAuthTokens::class)->handle($user, deviceId: 'device-1');

    // Suspension revokes the family, so reach past that to the token minted seconds before the
    // sweep: this is the belt to that braces, and it must say the true thing.
    app(SetAccountStatus::class)->suspend($user->party, suspender(), 'fraud');
    RefreshToken::query()->where('user_id', $user->getKey())->update(['revoked_at' => null]);

    expect(fn () => app(RotateRefreshToken::class)->handle($tokens->refreshToken))
        ->toThrow(App\Domain\Access\AccountNotActive::class);
});

it('keeps a suspended staff member out of the admin panel', function () {
    // The one place from which they could have unsuspended themselves.
    $this->seed(StaffRolesSeeder::class);
    $admin = User::factory()->create(['email' => 'admin+'.uniqid().'@handyman.cm']);
    $admin->assignRole(Role::SuperAdmin->value);

    // Staff, un-suspended: the panel lets them in as far as the mandatory 2FA enrolment (P1-09),
    // which is a redirect, not a refusal.
    $this->actingAs($admin)->get('/admin')->assertRedirect();

    app(SetAccountStatus::class)->suspend($admin->party, suspender(), 'left under a cloud');

    $this->actingAs($admin->refresh())->get('/admin')->assertForbidden();
});

it('reinstates without handing the old sessions back', function () {
    $user = User::factory()->create();
    $profile = ProviderProfile::factory()->create(['party_id' => $user->party_id]);
    app(IssueAuthTokens::class)->handle($user, deviceId: 'device-1');

    $actor = suspender();
    app(SetAccountStatus::class)->suspend($user->party, $actor, 'a report, since withdrawn');
    app(SetAccountStatus::class)->reinstate($user->party, $actor, 'report withdrawn');

    expect($user->party->refresh()->status)->toBe('active')
        ->and($user->refresh()->status)->toBe('active')
        ->and($profile->refresh()->suspended_at)->toBeNull()
        // Revoked stays revoked: they sign in again. Safe direction to be wrong in.
        ->and(RefreshToken::where('user_id', $user->getKey())->whereNull('revoked_at')->count())->toBe(0);

    // And they can, which is the point of reinstating.
    Sanctum::actingAs($user->refresh());
    $this->getJson('/api/v1/follow-ups')->assertOk();
});

it('records who did it and why, and announces it once', function () {
    $user = User::factory()->create();
    $actor = suspender();

    app(SetAccountStatus::class)->suspend($user->party, $actor, 'threatened a customer');

    $log = ActivityLog::query()->where('action', 'party.suspended')->sole();
    expect($log->actor_user_id)->toBe($actor->getKey())
        ->and($log->subject_id)->toBe($user->party_id)
        ->and($log->context['reason'])->toBe('threatened a customer')
        ->and($log->context['from'])->toBe('active');

    // Ids only on the wire — no PII in an outbox payload (rule #6).
    $message = OutboxMessage::query()->where('type', 'party.suspended')->sole();
    expect($message->payload)->toBe(['party_id' => $user->party_id, 'from' => 'active', 'to' => 'suspended']);
});

it('leaves pending accounts alone — it is not a punishment, it is pre-verification', function () {
    // Every signup starts here and VerifyOtp promotes it. If the gate blocked `pending`, nobody
    // could ever finish signing up.
    expect(AccountStatus::Pending->canAuthenticate())->toBeTrue()
        ->and(AccountStatus::Active->canAuthenticate())->toBeTrue()
        ->and(AccountStatus::Suspended->canAuthenticate())->toBeFalse()
        ->and(AccountStatus::Closed->canAuthenticate())->toBeFalse();
});

it('enforces the full transition matrix, illegal moves included', function () {
    $machine = app(AccountStatusMachine::class);

    // Legal.
    foreach ([
        [AccountStatus::Pending, AccountStatus::Active],
        [AccountStatus::Pending, AccountStatus::Suspended],
        [AccountStatus::Pending, AccountStatus::Closed],
        [AccountStatus::Active, AccountStatus::Suspended],
        [AccountStatus::Active, AccountStatus::Closed],
        [AccountStatus::Suspended, AccountStatus::Active],
        [AccountStatus::Suspended, AccountStatus::Closed],
    ] as [$from, $to]) {
        expect($machine->allows($from, $to))->toBeTrue("{$from->value} → {$to->value} should be legal");
    }

    // Closed is terminal: an erased party's row survives for the ledger's FKs, and bringing it
    // back would resurrect an identity that was deliberately destroyed.
    foreach ([AccountStatus::Pending, AccountStatus::Active, AccountStatus::Suspended] as $to) {
        expect($machine->allows(AccountStatus::Closed, $to))->toBeFalse();
        expect(fn () => $machine->assert(AccountStatus::Closed, $to))->toThrow(IllegalAccountTransition::class);
    }

    // Nothing goes back to pending, and re-entering your own state is a no-op rather than a raise.
    expect($machine->allows(AccountStatus::Active, AccountStatus::Pending))->toBeFalse()
        ->and($machine->allows(AccountStatus::Active, AccountStatus::Active))->toBeTrue();
});

it('refuses to reinstate an erased party', function () {
    $user = User::factory()->create();
    app(SetAccountStatus::class)->close($user->party, suspender(), 'erasure');

    expect(fn () => app(SetAccountStatus::class)->reinstate($user->party->refresh(), suspender(), 'mistake'))
        ->toThrow(IllegalAccountTransition::class);
});

it('is idempotent — suspending twice is not an error', function () {
    $user = User::factory()->create();
    $actor = suspender();

    app(SetAccountStatus::class)->suspend($user->party, $actor, 'first');
    app(SetAccountStatus::class)->suspend($user->party->refresh(), $actor, 'again');

    // The second call is a no-op: one decision, one log line, one announcement.
    expect(ActivityLog::query()->where('action', 'party.suspended')->count())->toBe(1)
        ->and(OutboxMessage::query()->where('type', 'party.suspended')->count())->toBe(1);
});
