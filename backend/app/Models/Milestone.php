<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Engagements\MilestoneStatus;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment milestone on an engagement (doc 06). The milestones of an engagement must sum to its
 * agreed amount (deferred DB constraint). Deposit is conventionally position 0.
 *
 * @property string $id
 * @property string $engagement_id
 * @property int $position
 * @property string $title
 * @property int $amount_minor
 * @property MilestoneStatus $status
 * @property Carbon|null $due_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property string|null $reject_reason
 */
final class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'engagement_id', 'position', 'title', 'amount_minor', 'status',
        'due_at', 'submitted_at', 'approved_at', 'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'amount_minor' => 'integer',
            'status' => MilestoneStatus::class,
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }
}
