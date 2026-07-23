<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An audit-log entry (doc 04). Append-only (DB-enforced). Records a sensitive human action — a
 * verification-document view, an admin adjudication — with the actor, subject and context.
 *
 * @property string $id
 * @property string|null $actor_user_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
final class ActivityLog extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id', 'action', 'subject_type', 'subject_id', 'context', 'ip_address', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
