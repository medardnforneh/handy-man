<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Workspace\MessageKind;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message in the workspace thread (doc 06). Free-form messages have a `sender_user_id`; structured
 * ones are narrated by the server and carry a `payload`. `contact_flag` records detected phone/email
 * (logged, not blocked in v1).
 *
 * @property string $id
 * @property string $conversation_id
 * @property string|null $sender_user_id
 * @property string|null $body
 * @property MessageKind $kind
 * @property array<string, mixed>|null $payload
 * @property string|null $contact_flag
 * @property string|null $reply_to_id
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 */
final class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'conversation_id', 'sender_user_id', 'body', 'kind', 'payload', 'contact_flag',
        'reply_to_id', 'edited_at', 'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MessageKind::class,
            'payload' => 'array',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
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
