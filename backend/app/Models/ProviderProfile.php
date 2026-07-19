<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProviderProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A provider profile (doc 02). Existence of this row is the `has_provider_profile` fact (doc 10);
 * `verification_tier` feeds `identity_verified`. Rating fields are cached/derived — never the
 * source of truth (recomputed from reviews).
 *
 * @property string $id
 * @property string $party_id
 * @property string|null $headline
 * @property string|null $bio
 * @property string|null $bio_language
 * @property int $verification_tier
 * @property int $jobs_completed
 */
final class ProviderProfile extends Model
{
    /** @use HasFactory<ProviderProfileFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'party_id', 'headline', 'bio', 'bio_language', 'verification_tier',
        'accepts_direct', 'accepts_dispatch', 'accepts_bidding',
    ];

    protected function casts(): array
    {
        return [
            'verification_tier' => 'integer',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
            'jobs_completed' => 'integer',
            'accepts_direct' => 'boolean',
            'accepts_dispatch' => 'boolean',
            'accepts_bidding' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return HasMany<ProviderSkill, $this>
     */
    public function skills(): HasMany
    {
        return $this->hasMany(ProviderSkill::class);
    }

    /**
     * @return HasMany<ServiceArea, $this>
     */
    public function serviceAreas(): HasMany
    {
        return $this->hasMany(ServiceArea::class);
    }
}
