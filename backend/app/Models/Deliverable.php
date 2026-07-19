<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Workspace\DeliverableStatus;
use Database\Factories\DeliverableFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A submitted deliverable on an engagement (doc 06).
 *
 * @property string $id
 * @property string $engagement_id
 * @property string|null $milestone_id
 * @property string $title
 * @property string|null $media_url
 * @property DeliverableStatus $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property string|null $reject_reason
 */
final class Deliverable extends Model
{
    /** @use HasFactory<DeliverableFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'engagement_id', 'milestone_id', 'title', 'media_url', 'status',
        'submitted_at', 'reviewed_at', 'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliverableStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
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
