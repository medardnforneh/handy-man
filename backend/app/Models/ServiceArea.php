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
     * Service areas whose disc covers (lat, lng): the point is within radius_m of the centre.
     *
     * @param  Builder<ServiceArea>  $query
     */
    public function scopeCovering(Builder $query, float $latitude, float $longitude): void
    {
        $query->whereRaw(
            'ST_DWithin(center, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, radius_m)',
            [$longitude, $latitude],
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
