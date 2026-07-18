<?php

declare(strict_types=1);

namespace App\Domain\Identity\Consent;

use App\Models\Consent;
use App\Models\User;

/**
 * Reads current consent state from the append-only log (build plan P1-05): the CURRENT decision for
 * a purpose is the most recent event for that (user, purpose).
 */
final class ConsentState
{
    /**
     * The latest decision per purpose. Purposes with no event are absent (treated as not granted).
     *
     * @return array<string, bool>
     */
    public function currentFor(User $user): array
    {
        $latest = Consent::query()
            ->where('user_id', $user->getKey())
            ->orderBy('created_at')
            ->get(['purpose', 'granted']);

        $state = [];
        foreach ($latest as $event) {
            $state[$event->purpose] = $event->granted; // later rows overwrite earlier → current
        }

        return $state;
    }

    public function has(User $user, string $purpose): bool
    {
        return $this->currentFor($user)[$purpose] ?? false;
    }
}
