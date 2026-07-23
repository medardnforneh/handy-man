<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A provider's do-not-contact entry for a customer (doc 07). Honoured absolutely — a manual
 * follow-up to a listed customer is refused.
 *
 * @property string $provider_party_id
 * @property string $customer_party_id
 * @property Carbon $created_at
 */
final class DoNotContact extends Model
{
    protected $table = 'do_not_contacts';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['provider_party_id', 'customer_party_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function exists(string $providerPartyId, string $customerPartyId): bool
    {
        return self::query()
            ->where('provider_party_id', $providerPartyId)
            ->where('customer_party_id', $customerPartyId)
            ->exists();
    }
}
