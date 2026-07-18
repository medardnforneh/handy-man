<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The result of issuing an auth token pair (P1-03): a short-lived Sanctum access token and an
 * opaque rotating refresh token. The raw refresh token is returned ONCE — only its hash is stored.
 */
final readonly class IssuedTokens
{
    public function __construct(
        public string $accessToken,
        public int $accessExpiresInSeconds,
        public string $refreshToken,
        public string $refreshTokenId,
        public Carbon $refreshExpiresAt,
        public User $user,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token_type' => 'Bearer',
            'access_token' => $this->accessToken,
            'expires_in' => $this->accessExpiresInSeconds,
            'refresh_token' => $this->refreshToken,
            'refresh_expires_at' => $this->refreshExpiresAt->toIso8601String(),
        ];
    }
}
