<?php

declare(strict_types=1);

namespace App\Domain\Reference\Actions;

use App\Models\Note;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Reference Action (P0-05). Demonstrates the unit every feature is built from:
 *   - exactly one public method, `handle()`;
 *   - all writes wrapped in a single DB transaction;
 *   - side effects (fan-out) announced through the {@see Outbox} in that SAME transaction, never
 *     dispatched directly — so a rollback un-announces them (CLAUDE.md rule + P0-07).
 *
 * Controllers call this; they never contain business logic themselves.
 */
final class CreateNote
{
    public function __construct(
        private readonly Outbox $outbox,
    ) {}

    public function handle(User $author, string $body): Note
    {
        return DB::transaction(function () use ($author, $body): Note {
            $note = Note::query()->create([
                'author_id' => $author->getKey(),
                'body' => $body,
            ]);

            $this->outbox->publish('note.created', [
                'note_id' => $note->getKey(),
                'author_id' => $author->getKey(),
            ]);

            return $note;
        });
    }
}
