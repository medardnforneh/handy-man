<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\JobReportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A worker's on-site job report (doc 02/06) — the physical-work counterpart to the remote path's
 * deliverables: what was done, materials used, any extra charges, and before/after photos (attached
 * as {@see Media}).
 *
 * @property string $id
 * @property string $assignment_id
 * @property string $summary
 * @property array<int, array<string, mixed>> $materials
 * @property int $extra_charges_minor
 * @property string|null $customer_signature_path
 * @property Carbon|null $submitted_at
 */
final class JobReport extends Model
{
    /** @use HasFactory<JobReportFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'assignment_id', 'summary', 'materials', 'extra_charges_minor',
        'customer_signature_path', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'materials' => 'array',
            'extra_charges_minor' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * The before/after photos on this report. `attachable_type` is stored as the plain string
     * 'job_report' (doc 02), so this is a keyed hasMany with a type filter rather than a morphMany.
     *
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'attachable_id', 'id')->where('attachable_type', 'job_report');
    }
}
