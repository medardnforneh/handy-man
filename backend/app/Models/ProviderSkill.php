<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProviderSkillFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A skill a provider offers, with pricing (doc 02). At least one of these is the `skill_listed`
 * fact (doc 10).
 *
 * @property string $id
 * @property string $provider_profile_id
 * @property string $skill_id
 * @property string $price_model
 * @property int|null $rate_minor
 * @property string $currency
 * @property int|null $years_experience
 */
final class ProviderSkill extends Model
{
    /** @use HasFactory<ProviderSkillFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'provider_profile_id', 'skill_id', 'price_model', 'rate_minor', 'currency', 'years_experience',
    ];

    protected function casts(): array
    {
        return [
            'rate_minor' => 'integer',
            'years_experience' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProviderProfile, $this>
     */
    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class);
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
