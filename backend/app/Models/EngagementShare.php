<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EngagementShareFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A share-my-job link (doc 04). Opaque token (stored hashed), expiring and revocable. Grants a
 * read-only view of the engagement's live status to whoever holds the URL.
 *
 * @property string $id
 * @property string $engagement_id
 * @property string $token_hash
 * @property string $created_by_user_id
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 */
final class EngagementShare extends Model
{
    /** @use HasFactory<EngagementShareFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'engagement_id', 'token_hash', 'created_by_user_id', 'expires_at', 'revoked_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }
}
