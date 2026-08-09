<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A node in the skills taxonomy (doc 02). Category = `parent_id` null; leaf = has a parent.
 * Bilingual via name_fr / name_en. Search uses the FTS config matching the query's language (P1-07b).
 *
 * @property string $id
 * @property string|null $parent_id
 * @property string $slug
 * @property string $name_fr
 * @property string $name_en
 * @property bool $is_leaf
 * @property bool $requires_license
 * @property int $risk_tier
 */
final class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'parent_id', 'slug', 'name_fr', 'name_en', 'is_leaf', 'requires_license', 'risk_tier',
        'maintenance_interval_days',
    ];

    protected function casts(): array
    {
        return [
            'is_leaf' => 'boolean',
            'requires_license' => 'boolean',
            'risk_tier' => 'integer',
            'maintenance_interval_days' => 'integer',
        ];
    }

    /**
     * The localized display name for a locale.
     */
    public function name(string $locale): string
    {
        return $locale === 'en' ? $this->name_en : $this->name_fr;
    }

    /**
     * Full-text search over leaf skills, using the dictionary that MATCHES the query language
     * (P1-07b). The column is chosen from a fixed whitelist (never user input); the config and term
     * are bound parameters — no SQL string interpolation of untrusted data (CLAUDE.md rule #7).
     *
     * @param  Builder<Skill>  $query
     */
    public function scopeSearch(Builder $query, string $term, string $locale): void
    {
        $config = $locale === 'en' ? 'english' : 'french';
        $column = $locale === 'en' ? 'name_en' : 'name_fr';

        $query->whereRaw(
            "to_tsvector(?::regconfig, {$column}) @@ plainto_tsquery(?::regconfig, ?)",
            [$config, $config, $term],
        );
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Skill, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
