<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The identity supertype (doc 02). A party is either an 'individual' (has a User) or an
 * 'organization' (has an Organization). Everything money/jobs/reviews reference `party_id`, so the
 * ledger stays free of PII (doc 04 crypto-shred design).
 *
 * @property string $id
 * @property string $kind
 * @property string $display_name
 * @property string $status
 */
final class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory, HasUuids;

    public const string KIND_INDIVIDUAL = 'individual';

    public const string KIND_ORGANIZATION = 'organization';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['kind', 'display_name', 'status'];

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return HasOne<Organization, $this>
     */
    public function organization(): HasOne
    {
        return $this->hasOne(Organization::class);
    }

    public function isIndividual(): bool
    {
        return $this->kind === self::KIND_INDIVIDUAL;
    }

    public function isOrganization(): bool
    {
        return $this->kind === self::KIND_ORGANIZATION;
    }
}
