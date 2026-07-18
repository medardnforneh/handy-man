<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConsentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single consent event (grant or revoke) — append-only (doc 04). The latest event per
 * (user, purpose) is the current state; see App\Domain\Identity\Consent\ConsentState.
 *
 * @property string $id
 * @property string $user_id
 * @property string $purpose
 * @property bool $granted
 * @property string $policy_version
 * @property string $presented_locale
 * @property Carbon $created_at
 */
final class Consent extends Model
{
    /** @use HasFactory<ConsentFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['user_id', 'purpose', 'granted', 'policy_version', 'presented_locale', 'created_at'];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
