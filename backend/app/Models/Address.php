<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

/**
 * A physical address (doc 02). The `point` is a PostGIS geography cast to a {@see Point}. Proximity
 * search uses {@see scopeNear()} → ST_DWithin, which is served by the GIST index.
 *
 * @property string $id
 * @property string $party_id
 * @property string|null $label
 * @property string $line1
 * @property string|null $quarter
 * @property string $city
 * @property string|null $region
 * @property string $country_code
 * @property Point $point
 * @property string|null $landmark_note
 */
final class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory, HasSpatial, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'party_id', 'label', 'line1', 'quarter', 'city', 'region', 'country_code', 'point', 'landmark_note',
    ];

    protected function casts(): array
    {
        return ['point' => Point::class];
    }

    /**
     * Addresses within $meters of (lat, lng). Uses ST_DWithin on geography so the GIST index does
     * the work — the query stays fast as the table grows. Bindings only (no SQL interpolation).
     *
     * @param  Builder<Address>  $query
     */
    public function scopeNear(Builder $query, float $latitude, float $longitude, float $meters): void
    {
        $query->whereRaw(
            'ST_DWithin(point, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
            [$longitude, $latitude, $meters],
        );
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
