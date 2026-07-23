<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A party's referral code (doc 04). One per party; the shareable token others claim.
 *
 * @property string $party_id
 * @property string $code
 * @property Carbon $created_at
 */
final class ReferralCode extends Model
{
    protected $table = 'referral_codes';

    protected $primaryKey = 'party_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['party_id', 'code', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
