<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reference model for the P0-05 worked slice. Models hold ONLY relationships, casts and scopes —
 * business logic lives in Actions (CLAUDE.md "Architecture conventions").
 *
 * @property int $id
 * @property int $author_id
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
