<?php

declare(strict_types=1);

namespace App\Domain\Identity\Consent;

use App\Domain\Identity\ConsentRequiredException;
use App\Models\User;

/**
 * Gate an action on a required consent (doc 04). e.g. writing a user's geo location requires
 * `location_tracking`; revoking it blocks the write. Used by the address-write path (P1-06) and
 * anywhere else a consented purpose is a precondition.
 */
final class ConsentGuard
{
    public function __construct(
        private readonly ConsentState $state,
    ) {}

    public function assertGranted(User $user, string $purpose): void
    {
        if (! $this->state->has($user, $purpose)) {
            throw new ConsentRequiredException($purpose);
        }
    }
}
