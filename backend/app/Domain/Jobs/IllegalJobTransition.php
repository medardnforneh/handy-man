<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when code attempts an illegal job state transition (CLAUDE.md rule #8). Usually a bug or a
 * lost race; rendered as a 409 conflict if it ever reaches the API.
 */
final class IllegalJobTransition extends RuntimeException implements ProblemAware
{
    public function __construct(
        public readonly JobStatus $from,
        public readonly JobStatus $to,
    ) {
        parent::__construct("Illegal job transition: {$from->value} → {$to->value}");
    }

    public function problemType(): string
    {
        return 'illegal-job-transition';
    }

    public function problemTitle(): string
    {
        return 'Invalid state transition';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
