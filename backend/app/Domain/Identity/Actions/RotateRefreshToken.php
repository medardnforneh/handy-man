<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\AuthTokenException;
use App\Domain\Identity\IssuedTokens;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\DB;

/**
 * Rotates a refresh token (build plan P1-03). Each refresh token is single-use: presenting it
 * mints a NEW pair in the same family and revokes the old token.
 *
 * REUSE DETECTION: if the presented token is already revoked (it was rotated before), it has
 * leaked and is being replayed — so we revoke the ENTIRE family and force re-authentication. This
 * is the token-theft tripwire (doc 04).
 */
final class RotateRefreshToken
{
    public function __construct(
        private readonly IssueAuthTokens $issue,
    ) {}

    public function handle(string $rawRefreshToken): IssuedTokens
    {
        $token = RefreshToken::query()
            ->where('token_hash', hash('sha256', $rawRefreshToken))
            ->first();

        if ($token === null) {
            throw AuthTokenException::invalidRefresh();
        }

        if ($token->isRevoked()) {
            // A rotated (revoked) token was replayed → theft. Burn the whole family and every
            // access token the user holds. This MUST run outside a transaction so it persists even
            // as we reject the request (a rolled-back revocation would leave the session usable).
            RefreshToken::query()
                ->where('family_id', $token->family_id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
            $token->user->tokens()->delete();

            throw AuthTokenException::reuseDetected();
        }

        if ($token->isExpired()) {
            throw AuthTokenException::invalidRefresh();
        }

        // Success path is atomic: re-lock the row, re-check it, rotate within the same family.
        return DB::transaction(function () use ($token): IssuedTokens {
            $locked = RefreshToken::query()->whereKey($token->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $locked->isActive()) {
                throw AuthTokenException::invalidRefresh();
            }

            $issued = $this->issue->handle($locked->user, $locked->device_id, $locked->family_id);

            $locked->forceFill([
                'revoked_at' => now(),
                'replaced_by_id' => $issued->refreshTokenId,
            ])->save();

            return $issued;
        });
    }
}
