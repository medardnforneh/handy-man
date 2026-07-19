<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConversationParticipantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's membership of a conversation (doc 06). Only participants may read or post.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $party_id
 * @property string $user_id
 * @property Carbon $joined_at
 * @property Carbon|null $last_read_at
 */
final class ConversationParticipant extends Model
{
    /** @use HasFactory<ConversationParticipantFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['conversation_id', 'party_id', 'user_id', 'joined_at', 'last_read_at'];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
