<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RefreshTokenFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single rotating refresh token (build plan P1-03). Only the hash is stored. See
 * App\Domain\Identity\Actions\{IssueAuthTokens,RotateRefreshToken} for the rotation + reuse-detection
 * protocol.
 *
 * @property string $id
 * @property string $user_id
 * @property string $family_id
 * @property string $token_hash
 * @property string|null $device_id
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property string|null $replaced_by_id
 * @property Carbon $created_at
 */
final class RefreshToken extends Model
{
    /** @use HasFactory<RefreshTokenFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id', 'family_id', 'token_hash', 'device_id',
        'expires_at', 'revoked_at', 'replaced_by_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
