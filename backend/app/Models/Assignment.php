<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Engagements\AssignmentRole;
use App\Domain\Engagements\AssignmentStatus;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A worker's assignment on an engagement (doc 02). The provider section queries ONLY this table —
 * it never branches on individual-vs-company. One active `lead` per engagement (DB-enforced).
 *
 * @property string $id
 * @property string $engagement_id
 * @property string $worker_user_id
 * @property string $assigned_by_user_id
 * @property AssignmentRole $role
 * @property AssignmentStatus $status
 * @property Carbon $assigned_at
 * @property Carbon|null $removed_at
 */
final class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'engagement_id', 'worker_user_id', 'assigned_by_user_id', 'role', 'status',
        'assigned_at', 'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => AssignmentRole::class,
            'status' => AssignmentStatus::class,
            'assigned_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_user_id');
    }
}
