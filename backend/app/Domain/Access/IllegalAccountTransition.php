<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account-status move the machine does not allow (CLAUDE.md rule #8). Usually a bug or a stale
 * admin page — reinstating someone a colleague closed a moment ago, most likely.
 */
final class IllegalAccountTransition extends RuntimeException implements ProblemAware
{
    public function __construct(
        public readonly AccountStatus $from,
        public readonly AccountStatus $to,
    ) {
        parent::__construct("An account cannot move from {$from->value} to {$to->value}.");
    }

    public function problemType(): string
    {
        return 'illegal-account-transition';
    }

    public function problemTitle(): string
    {
        return 'That account change is not allowed';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
