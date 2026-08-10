<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServiceAreaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

/**
 * Where a provider works (doc 02): a centre point + radius. Dispatch ranking (P8) is an ST_DWithin
 * query over `center` served by the GIST index.
 *
 * @property string $id
 * @property string $provider_profile_id
 * @property Point $center
 * @property int $radius_m
 */
final class ServiceArea extends Model
{
    /** @use HasFactory<ServiceAreaFactory> */
    use HasFactory, HasSpatial, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['provider_profile_id', 'center', 'radius_m', 'created_at'];

    protected function casts(): array
    {
        return [
            'center' => Point::class,
            'radius_m' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The widest disc the schema permits (`service_areas_radius_check`). Used as a constant bound
     * below; it must never be lower than that CHECK or coverage would silently miss providers.
     */
    public const MAX_RADIUS_M = 100000;

    /**
     * Service areas whose disc covers (lat, lng): the point is within radius_m of the centre.
     *
     * Two predicates, and the first one is why this is fast. `ST_DWithin(center, point, radius_m)`
     * alone takes its distance from a COLUMN, and a GIST index cannot bound a search whose radius
     * differs per row — so Postgres sequentially scans `service_areas`. That hid well in testing:
     * with providers clustered in a city the scan finds matches immediately and `LIMIT` exits early,
     * so it measured ~1ms. A point that matches little or nothing has no early exit and scans the
     * whole table — 131ms at 50k areas on this machine, growing linearly with every provider who
     * signs up, on the provider-search and dispatch hot path.
     *
     * The added constant-radius predicate IS index-served, and is a strict superset of the exact one
     * (nothing within `radius_m` of the centre can be further than the maximum permitted radius), so
     * it narrows the candidates without changing the answer. Measure with
     * `php artisan perf:geo-benchmark --target=areas`.
     *
     * @param  Builder<ServiceArea>  $query
     */
    public function scopeCovering(Builder $query, float $latitude, float $longitude): void
    {
        $query->whereRaw(
            'ST_DWithin(center, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)
             AND ST_DWithin(center, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, radius_m)',
            [$longitude, $latitude, self::MAX_RADIUS_M, $longitude, $latitude],
        );
    }

    /**
     * @return BelongsTo<ProviderProfile, $this>
     */
    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class);
    }
}
