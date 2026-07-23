<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EngagementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The engagement — one per job (DB-unique). Created by AcceptOfferAction (doc 06). The workspace
 * (quotations, milestones, chat) hangs off this in later phases.
 *
 * @property string $id
 * @property string $job_id
 * @property string $provider_party_id
 * @property string|null $offer_id
 * @property string|null $quotation_id
 * @property int $agreed_amount_minor
 * @property int $visit_credit_minor
 * @property string $currency
 * @property bool $is_escrowed
 * @property Carbon $accepted_at
 */
final class Engagement extends Model
{
    /** @use HasFactory<EngagementFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'job_id', 'provider_party_id', 'offer_id', 'quotation_id', 'warranty_claim_id', 'agreed_amount_minor',
        'visit_credit_minor', 'currency', 'platform_fee_minor', 'is_escrowed', 'accepted_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'agreed_amount_minor' => 'integer',
            'visit_credit_minor' => 'integer',
            'platform_fee_minor' => 'integer',
            'is_escrowed' => 'boolean',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
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

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('position');
    }
}
