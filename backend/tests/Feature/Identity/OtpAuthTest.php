<?php

declare(strict_types=1);

use App\Domain\Identity\Otp\OtpSender;
use App\Models\OtpChallenge;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Support\FakeOtpSender;

/**
 * P1-02 acceptance (doc 02/04): OTP-first signup/login with hard rate limits. The headline
 * acceptance — the 4th OTP request for a phone within an hour is rejected.
 */
beforeEach(function () {
    $this->fakeOtp = new FakeOtpSender;
    $this->app->instance(OtpSender::class, $this->fakeOtp);
});

/** POST with a fresh Idempotency-Key (each call is a distinct operation). */
function otpPost($test, string $uri, array $body)
{
    return $test->postJson($uri, $body, ['Idempotency-Key' => (string) Str::uuid()]);
}

it('issues a challenge without ever returning the code', function () {
    // The suite runs as `testing`, i.e. NOT `local` — which is the point: the dev convenience below
    // is gated on the environment, so every environment that is not a developer's own machine takes
    // this path.
    $res = otpPost($this, '/api/v1/auth/otp/request', [
        'phone_e164' => '+237699000111', 'purpose' => 'login',
    ]);

    $res->assertStatus(202)->assertJsonStructure(['challenge_id', 'expires_at']);
    expect($res->json())->not->toHaveKey('code')
        ->and($res->json())->not->toHaveKey('dev_code');
    expect(OtpChallenge::where('phone_e164', '+237699000111')->count())->toBe(1);
});

/**
 * Local development has no SMS gateway, so the code only ever existed in a log file — which makes
 * signing in to your own machine a grep. In `local` the response carries it, and the code returned
 * must be the one that actually verifies, not merely a plausible six digits.
 */
it('returns the code in local development, and it is the real one', function () {
    app()['env'] = 'local';

    $res = otpPost($this, '/api/v1/auth/otp/request', [
        'phone_e164' => '+237699000222', 'purpose' => 'login',
    ]);

    $res->assertStatus(202);
    $code = $res->json('dev_code');
    expect($code)->toMatch('/^\d{6}$/');

    otpPost($this, '/api/v1/auth/otp/verify', [
        'phone_e164' => '+237699000222', 'code' => $code, 'purpose' => 'login',
    ])->assertStatus(201);
});

it('rejects the 4th OTP request for a phone within an hour', function () {
    $phone = '+237699222333';

    for ($i = 1; $i <= 3; $i++) {
        otpPost($this, '/api/v1/auth/otp/request', ['phone_e164' => $phone, 'purpose' => 'login'])
            ->assertStatus(202);
    }

    otpPost($this, '/api/v1/auth/otp/request', ['phone_e164' => $phone, 'purpose' => 'login'])
        ->assertStatus(429)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Too many OTP requests');
});

it('creates a user on first successful verify (signup) then logs in on the second', function () {
    $phone = '+237699444555';

    otpPost($this, '/api/v1/auth/otp/request', ['phone_e164' => $phone, 'purpose' => 'signup'])->assertStatus(202);
    $code = $this->fakeOtp->codeFor($phone);

    $signup = otpPost($this, '/api/v1/auth/otp/verify', [
        'phone_e164' => $phone, 'code' => $code, 'purpose' => 'signup',
    ]);
    $signup->assertStatus(201)
        ->assertJsonPath('registered', true)
        ->assertJsonPath('data.phone_e164', $phone);

    $user = User::where('phone_e164', $phone)->firstOrFail();
    expect($user->party->kind)->toBe(Party::KIND_INDIVIDUAL)
        ->and($user->phone_verified_at)->not->toBeNull();

    // Second time round it's a login, not a new registration.
    otpPost($this, '/api/v1/auth/otp/request', ['phone_e164' => $phone, 'purpose' => 'login'])->assertStatus(202);
    $code2 = $this->fakeOtp->codeFor($phone);

    otpPost($this, '/api/v1/auth/otp/verify', ['phone_e164' => $phone, 'code' => $code2, 'purpose' => 'login'])
        ->assertStatus(200)
        ->assertJsonPath('registered', false);

    expect(User::where('phone_e164', $phone)->count())->toBe(1);
});

it('rejects a wrong code and counts the attempt', function () {
    $phone = '+237699666777';
    otpPost($this, '/api/v1/auth/otp/request', ['phone_e164' => $phone, 'purpose' => 'login'])->assertStatus(202);

    otpPost($this, '/api/v1/auth/otp/verify', ['phone_e164' => $phone, 'code' => '000000', 'purpose' => 'login'])
        ->assertStatus(422)
        ->assertJsonPath('title', 'Invalid or expired code');

    expect(OtpChallenge::where('phone_e164', $phone)->value('attempts'))->toBe(1);
});

it('rejects an expired challenge', function () {
    $phone = '+237699888999';
    OtpChallenge::factory()->expired()->create(['phone_e164' => $phone, 'purpose' => 'login']);

    otpPost($this, '/api/v1/auth/otp/verify', ['phone_e164' => $phone, 'code' => '123456', 'purpose' => 'login'])
        ->assertStatus(422)
        ->assertJsonPath('title', 'Invalid or expired code');
});

it('hard-locks after too many wrong attempts', function () {
    $phone = '+237699101010';
    OtpChallenge::factory()->create([
        'phone_e164' => $phone, 'purpose' => 'login', 'attempts' => config('otp.max_verify_attempts'),
    ]);

    otpPost($this, '/api/v1/auth/otp/verify', ['phone_e164' => $phone, 'code' => '123456', 'purpose' => 'login'])
        ->assertStatus(429)
        ->assertJsonPath('title', 'Too many attempts');
});
