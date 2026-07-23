<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Warranties\WarrantyStatus;
use Database\Factories\WarrantyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A warranty on an engagement (doc 06) — one per engagement. Exists only on-platform; the strongest
 * anti-leakage mechanic there is. A claim spawns a real remedy job.
 *
 * @property string $id
 * @property string $engagement_id
 * @property int $duration_days
 * @property Carbon $starts_at
 * @property Carbon $expires_at
 * @property string|null $terms
 * @property WarrantyStatus $status
 */
final class Warranty extends Model
{
    /** @use HasFactory<WarrantyFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'engagement_id', 'duration_days', 'starts_at', 'expires_at', 'terms', 'status', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => WarrantyStatus::class,
        ];
    }

    public function isClaimable(): bool
    {
        return $this->status === WarrantyStatus::Active && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }
}
