<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

/**
 * A worker's on-site work session (doc 02). Opened at check-in, closed at check-out, each end
 * carrying a geography point + GPS accuracy. Exists only for onsite/hybrid engagements — the remote
 * path proves work through deliverables instead.
 *
 * @property string $id
 * @property string $assignment_id
 * @property Carbon $started_at
 * @property Point|null $start_point
 * @property float|null $start_accuracy_m
 * @property Carbon|null $ended_at
 * @property Point|null $end_point
 * @property float|null $end_accuracy_m
 */
final class WorkSession extends Model
{
    /** @use HasFactory<WorkSessionFactory> */
    use HasFactory, HasSpatial, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'assignment_id', 'started_at', 'start_point', 'start_accuracy_m',
        'ended_at', 'end_point', 'end_accuracy_m',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'start_point' => Point::class,
            'start_accuracy_m' => 'float',
            'ended_at' => 'datetime',
            'end_point' => Point::class,
            'end_accuracy_m' => 'float',
        ];
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
