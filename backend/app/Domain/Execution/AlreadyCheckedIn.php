<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The worker already has an open work session on this assignment — they must check out before
 * checking in again (build plan P5-03). The DB partial unique index `one_open_session_per_assignment`
 * is the hard guarantee; this is the friendly 409 raised after the app pre-check or a caught race.
 */
final class AlreadyCheckedIn extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'already-checked-in';
    }

    public function problemTitle(): string
    {
        return 'You already have an open work session';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
