<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OtpChallengeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single OTP challenge. The code is stored hashed in `code_hash`; the plaintext exists only long
 * enough to be sent. See App\Domain\Identity\Actions\{RequestOtp,VerifyOtp}.
 *
 * @property string $id
 * @property string $phone_e164
 * @property string $code_hash
 * @property string $purpose
 * @property int $attempts
 * @property Carbon|null $consumed_at
 * @property Carbon $expires_at
 * @property Carbon $created_at
 */
final class OtpChallenge extends Model
{
    /** @use HasFactory<OtpChallengeFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['phone_e164', 'code_hash', 'purpose', 'attempts', 'expires_at', 'created_at'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'consumed_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
