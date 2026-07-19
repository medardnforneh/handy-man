<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Quotations\SiteVisitStatus;
use Database\Factories\SiteVisitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A site visit (doc 06). Onsite/hybrid = physical; remote = video consultation. A chargeable visit's
 * fee is creditable against the final quote it produces (`resulting_quotation_id`).
 *
 * @property string $id
 * @property string $job_id
 * @property string $provider_party_id
 * @property Carbon $scheduled_for
 * @property bool $is_chargeable
 * @property int $fee_minor
 * @property string $currency
 * @property SiteVisitStatus $status
 * @property Carbon|null $completed_at
 * @property string|null $outcome_notes
 * @property string|null $resulting_quotation_id
 */
final class SiteVisit extends Model
{
    /** @use HasFactory<SiteVisitFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'job_id', 'provider_party_id', 'scheduled_for', 'is_chargeable', 'fee_minor', 'currency',
        'status', 'completed_at', 'outcome_notes', 'resulting_quotation_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'is_chargeable' => 'boolean',
            'fee_minor' => 'integer',
            'status' => SiteVisitStatus::class,
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
     * @return BelongsTo<Quotation, $this>
     */
    public function resultingQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'resulting_quotation_id');
    }
}
