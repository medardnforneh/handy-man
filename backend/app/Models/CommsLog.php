<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\FollowUps\FollowUpChannel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A record of one actual send (doc 07). The per-user per-channel budget is enforced by counting these
 * over a rolling window — so only real sends count, never suppressed follow-ups.
 *
 * @property string $id
 * @property string $user_id
 * @property FollowUpChannel $channel
 * @property string $purpose
 * @property string|null $follow_up_id
 * @property Carbon $sent_at
 */
final class CommsLog extends Model
{
    use HasUuids;

    protected $table = 'comms_log';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['user_id', 'channel', 'purpose', 'follow_up_id', 'sent_at'];

    protected function casts(): array
    {
        return [
            'channel' => FollowUpChannel::class,
            'sent_at' => 'datetime',
        ];
    }
}
