<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A referral (doc 04). Pending until the referee completes their first paid job, then `qualified`
 * with a ledger-backed reward. One per referee (DB-unique); never self.
 *
 * @property string $id
 * @property string $referrer_party_id
 * @property string $referee_party_id
 * @property string $status
 * @property string|null $reward_transaction_id
 * @property Carbon|null $qualified_at
 */
final class Referral extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'referrer_party_id', 'referee_party_id', 'status', 'reward_transaction_id', 'qualified_at', 'created_at',
    ];

    protected function casts(): array
    {
        return ['qualified_at' => 'datetime'];
    }
}
