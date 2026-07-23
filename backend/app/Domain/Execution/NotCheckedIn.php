<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check-out was attempted with no open work session — the worker never checked in (or already
 * checked out) on this assignment (build plan P5-03).
 */
final class NotCheckedIn extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'not-checked-in';
    }

    public function problemTitle(): string
    {
        return 'You have no open work session to check out of';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
