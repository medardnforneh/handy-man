<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\IssueAuthTokens;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Str;

/** POST /refresh with a fresh Idempotency-Key. */
function postRefresh($test, string $refreshToken)
{
    return $test->postJson('/api/v1/auth/refresh',
        ['refresh_token' => $refreshToken],
        ['Idempotency-Key' => (string) Str::uuid()],
    );
}

it('authenticates a protected route with the access token', function () {
    $user = User::factory()->create();
    $issued = app(IssueAuthTokens::class)->handle($user);

    $this->withToken($issued->accessToken)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('rejects an unauthenticated request to a protected route', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('rotates the refresh token and revokes the old one', function () {
    $user = User::factory()->create();
    $issued = app(IssueAuthTokens::class)->handle($user);

    $res = postRefresh($this, $issued->refreshToken);
    $res->assertOk()->assertJsonStructure(['access_token', 'refresh_token', 'expires_in']);

    expect($res->json('refresh_token'))->not->toBe($issued->refreshToken);

    $old = RefreshToken::findOrFail($issued->refreshTokenId);
    expect($old->isRevoked())->toBeTrue()
        ->and($old->replaced_by_id)->not->toBeNull();
});

it('detects reuse of a rotated refresh token and revokes the whole family', function () {
    $user = User::factory()->create();
    $issued = app(IssueAuthTokens::class)->handle($user);

    // Legitimately rotate once → new token issued in the same family.
    $rotated = postRefresh($this, $issued->refreshToken)->assertOk();
    $newRefresh = $rotated->json('refresh_token');

    // Replay the OLD (already-rotated) token → theft signal.
    postRefresh($this, $issued->refreshToken)
        ->assertStatus(401)
        ->assertJsonPath('title', 'Session revoked');

    // The whole family is burned: even the legitimately-issued new token no longer works.
    postRefresh($this, $newRefresh)->assertStatus(401);

    expect(RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count())->toBe(0)
        ->and($user->tokens()->count())->toBe(0); // access tokens wiped too
});

it('rejects an invalid refresh token', function () {
    postRefresh($this, 'not-a-real-token')
        ->assertStatus(401)
        ->assertJsonPath('title', 'Invalid refresh token');
});

it('rejects an expired refresh token', function () {
    $user = User::factory()->create();
    $raw = bin2hex(random_bytes(32));
    RefreshToken::factory()->expired()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $raw),
    ]);

    postRefresh($this, $raw)->assertStatus(401)->assertJsonPath('title', 'Invalid refresh token');
});

it('logs out by revoking the current access token', function () {
    $user = User::factory()->create();
    $issued = app(IssueAuthTokens::class)->handle($user);

    $this->withToken($issued->accessToken)
        ->postJson('/api/v1/auth/logout', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
