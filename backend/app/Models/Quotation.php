<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Quotations\QuoteStatus;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A versioned, immutable quotation (doc 06). One live (draft|submitted) quote per provider per job
 * (DB-enforced). Terms never change once submitted — a revision is a new version linked by
 * `supersedes_id`. Status only moves through the QuotationStateMachine.
 *
 * @property string $id
 * @property string $job_id
 * @property string $provider_party_id
 * @property int $version
 * @property string|null $supersedes_id
 * @property QuoteStatus $status
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $deposit_minor
 * @property string|null $notes
 * @property Carbon|null $customer_requested_by
 * @property Carbon|null $provider_estimated_at
 * @property Carbon|null $provider_committed_at
 * @property Carbon $valid_until
 * @property Carbon|null $submitted_at
 * @property Carbon|null $responded_at
 */
final class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'job_id', 'provider_party_id', 'version', 'supersedes_id', 'status', 'currency',
        'subtotal_minor', 'deposit_minor', 'notes', 'customer_requested_by', 'provider_estimated_at',
        'provider_committed_at', 'valid_until', 'submitted_at', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'version' => 'integer',
            'subtotal_minor' => 'integer',
            'deposit_minor' => 'integer',
            'customer_requested_by' => 'datetime',
            'provider_estimated_at' => 'datetime',
            'provider_committed_at' => 'datetime',
            'valid_until' => 'datetime',
            'submitted_at' => 'datetime',
            'responded_at' => 'datetime',
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
     * @return BelongsTo<Quotation, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'supersedes_id');
    }

    /**
     * @return HasMany<QuotationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('position');
    }
}
