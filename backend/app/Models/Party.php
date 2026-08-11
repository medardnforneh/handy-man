<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * The identity supertype (doc 02). A party is either an 'individual' (has a User) or an
 * 'organization' (has an Organization). Everything money/jobs/reviews reference `party_id`, so the
 * ledger stays free of PII (doc 04 crypto-shred design).
 *
 * @property string $id
 * @property string $kind
 * @property string $display_name
 * @property string $status
 * @property string|null $data_key
 * @property Carbon|null $erased_at
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

    protected function casts(): array
    {
        return [
            'data_key' => 'encrypted', // per-party crypto-shred key; encrypted at rest
            'erased_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Mint a per-party data key on creation (doc 04 crypto-shred). Destroying it later makes any
        // key-encrypted PII unrecoverable.
        self::creating(function (Party $party): void {
            if ($party->data_key === null) {
                $party->data_key = base64_encode(random_bytes(32)); // 256-bit
            }
        });
    }

    public function isErased(): bool
    {
        return $this->erased_at !== null;
    }

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

    /**
     * @return HasOne<ProviderProfile, $this>
     */
    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
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
