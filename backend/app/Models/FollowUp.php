<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\FollowUps\FollowUpChannel;
use App\Domain\FollowUps\FollowUpKind;
use App\Domain\FollowUps\FollowUpStatus;
use Database\Factories\FollowUpFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A scheduled follow-up (doc 07). Idempotent on `dedupe_key`. Scheduled on an event, cancelled on an
 * event — a follow-up that fires after its reason evaporated is worse than none.
 *
 * @property string $id
 * @property FollowUpKind $kind
 * @property string $target_party_id
 * @property string $target_user_id
 * @property string|null $created_by_user_id
 * @property FollowUpChannel $channel
 * @property Carbon $scheduled_for
 * @property FollowUpStatus $status
 * @property string $dedupe_key
 * @property Carbon|null $sent_at
 * @property Carbon|null $cancelled_at
 * @property string|null $response_action
 */
final class FollowUp extends Model
{
    /** @use HasFactory<FollowUpFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'kind', 'target_party_id', 'target_user_id', 'job_id', 'engagement_id', 'quotation_id',
        'warranty_id', 'created_by_user_id', 'channel', 'scheduled_for', 'status', 'dedupe_key',
        'attempts', 'sent_at', 'cancelled_at', 'cancel_reason', 'responded_at', 'response_action', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'kind' => FollowUpKind::class,
            'channel' => FollowUpChannel::class,
            'status' => FollowUpStatus::class,
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'responded_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
