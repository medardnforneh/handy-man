<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Offers\OfferOrigin;
use App\Domain\Offers\OfferStatus;
use Database\Factories\JobOfferFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An offer connecting a provider to a job (doc 02). One live offer per provider per job; exactly one
 * accepted offer per job (both DB-enforced).
 *
 * @property string $id
 * @property string $job_id
 * @property string $provider_party_id
 * @property OfferOrigin $origin
 * @property OfferStatus $status
 * @property int|null $amount_minor
 * @property string $currency
 * @property Carbon $expires_at
 * @property Carbon|null $responded_at
 */
final class JobOffer extends Model
{
    /** @use HasFactory<JobOfferFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'job_id', 'provider_party_id', 'origin', 'status', 'amount_minor', 'currency',
        'message', 'expires_at', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'origin' => OfferOrigin::class,
            'status' => OfferStatus::class,
            'amount_minor' => 'integer',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === OfferStatus::Pending;
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'provider_party_id');
    }
}
