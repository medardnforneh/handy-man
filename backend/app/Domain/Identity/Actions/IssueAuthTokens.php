<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\IssuedTokens;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Mints an access + refresh token pair (build plan P1-03). The access token is a Sanctum personal
 * access token that expires in 15 minutes; the refresh token is an opaque 256-bit secret stored
 * only as a sha256 hash, belonging to a rotation `family`.
 */
final class IssueAuthTokens
{
    public function handle(User $user, ?string $deviceId = null, ?string $familyId = null): IssuedTokens
    {
        $accessTtlMinutes = (int) config('sanctum.expiration');
        $accessToken = $user->createToken('access', ['*'], now()->addMinutes($accessTtlMinutes))->plainTextToken;

        $rawRefresh = bin2hex(random_bytes(32)); // 256-bit opaque secret
        $refreshExpiresAt = now()->addDays((int) config('sanctum.refresh_ttl_days'));

        $refresh = RefreshToken::query()->create([
            'user_id' => $user->getKey(),
            'family_id' => $familyId ?? (string) Str::uuid(),
            'token_hash' => hash('sha256', $rawRefresh),
            'device_id' => $deviceId,
            'expires_at' => $refreshExpiresAt,
            'created_at' => now(),
        ]);

        return new IssuedTokens(
            accessToken: $accessToken,
            accessExpiresInSeconds: $accessTtlMinutes * 60,
            refreshToken: $rawRefresh,
            refreshTokenId: $refresh->getKey(),
            refreshExpiresAt: $refreshExpiresAt,
            user: $user,
        );
    }
}
