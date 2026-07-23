<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check-in was attempted on an engagement whose mode has no physical presence (build plan P5-03,
 * doc 06). Remote work proves itself through deliverables, not geo check-ins — so a remote (or any
 * non-onsite/hybrid) engagement exposes no check-in affordance, and the server refuses it too.
 */
final class CheckInNotSupported extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'check-in-not-supported';
    }

    public function problemTitle(): string
    {
        return 'This engagement does not support check-in';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
