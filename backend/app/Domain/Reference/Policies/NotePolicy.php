<?php

declare(strict_types=1);

namespace App\Domain\Reference\Policies;

use App\Models\Note;
use App\Models\User;

/**
 * Reference Policy (P0-05). Authorization for a note lives here, not scattered through the
 * controller. Registered in AppServiceProvider via Gate::policy (our domain policies live under
 * their module, so Laravel's App\Policies auto-discovery does not find them — we bind explicitly).
 *
 * NOTE: this authorizes ownership of an already-identified resource. It is NOT the fact-gated
 * capability model from doc 10 — that arrives in P0-17 for high-stakes marketplace actions.
 */
final class NotePolicy
{
    public function view(User $user, Note $note): bool
    {
        return $note->author_id === $user->getKey();
    }

    public function update(User $user, Note $note): bool
    {
        return $note->author_id === $user->getKey();
    }

    public function delete(User $user, Note $note): bool
    {
        return $note->author_id === $user->getKey();
    }
}
