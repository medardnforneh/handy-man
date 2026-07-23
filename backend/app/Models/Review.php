<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Reviews\ReviewVisibility;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A double-blind review (doc 02/04). Hidden (`pending`) until both parties submit or the window
 * closes, then `published`. The `private_note` is never published — it is for the subject's eyes.
 *
 * @property string $id
 * @property string $engagement_id
 * @property string $author_party_id
 * @property string $subject_party_id
 * @property string|null $subject_worker_user_id
 * @property int $rating
 * @property string|null $body
 * @property string|null $private_note
 * @property ReviewVisibility $visibility
 * @property Carbon $submitted_at
 * @property Carbon|null $published_at
 * @property Carbon $window_closes_at
 */
final class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'engagement_id', 'author_party_id', 'subject_party_id', 'subject_worker_user_id',
        'rating', 'body', 'private_note', 'visibility', 'submitted_at', 'published_at',
        'window_closes_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'visibility' => ReviewVisibility::class,
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'window_closes_at' => 'datetime',
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
