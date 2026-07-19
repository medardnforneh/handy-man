<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The conversation for a job's engagement workspace (doc 06). One per job. The message thread is the
 * timeline, audit log and UI at once.
 *
 * @property string $id
 * @property string $job_id
 */
final class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['job_id'];

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function hasParticipant(string $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }
}
