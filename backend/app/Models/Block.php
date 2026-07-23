<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A block — a hard boundary between two parties (doc 02/04). Directional in storage, but honoured
 * BIDIRECTIONALLY: if either party has blocked the other, they are never matched. That must hold in
 * search, dispatch ranking and offer creation (P6-07).
 *
 * @property string $party_id
 * @property string $blocked_party_id
 * @property Carbon $created_at
 */
final class Block extends Model
{
    protected $table = 'blocks';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['party_id', 'blocked_party_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * Is there a block between these two parties in EITHER direction?
     */
    public static function existsBetween(string $partyA, string $partyB): bool
    {
        return self::query()
            ->where(fn ($q) => $q->where('party_id', $partyA)->where('blocked_party_id', $partyB))
            ->orWhere(fn ($q) => $q->where('party_id', $partyB)->where('blocked_party_id', $partyA))
            ->exists();
    }

    /**
     * Every party id that this party has blocked OR been blocked by — the set to exclude from
     * matching. Kept small (a party's personal block list), so an `in`/`not in` is fine.
     *
     * @return array<int, string>
     */
    public static function partyIdsAround(string $partyId): array
    {
        $blocked = self::query()->where('party_id', $partyId)->pluck('blocked_party_id');
        $blockedBy = self::query()->where('blocked_party_id', $partyId)->pluck('party_id');

        return $blocked->merge($blockedBy)->unique()->values()->all();
    }
}
