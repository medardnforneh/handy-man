<?php

declare(strict_types=1);

namespace App\Domain\Safety;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An action was attempted between two parties with a block between them (build plan P6-07). A block
 * is honoured everywhere — offer creation refuses rather than quietly proceeding.
 */
final class PartyBlocked extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'party-blocked';
    }

    public function problemTitle(): string
    {
        return 'There is a block between these parties';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
