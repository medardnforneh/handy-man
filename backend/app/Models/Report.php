<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A report — a complaint filed by one party about another (doc 02/04). Feeds the admin queue; a
 * report never auto-penalises anyone, it queues a human look. `off_platform` is a first-class
 * category because leakage is the platform's core risk.
 *
 * @property string $id
 * @property string $reporter_party_id
 * @property string $subject_party_id
 * @property string|null $job_id
 * @property string $category
 * @property string $body
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon|null $resolved_at
 */
final class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'reporter_party_id', 'subject_party_id', 'job_id', 'category', 'body', 'status', 'created_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'subject_party_id');
    }
}
