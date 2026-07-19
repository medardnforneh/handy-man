<?php

declare(strict_types=1);

namespace App\Domain\Money;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A release/refund asked for more than the escrow currently holds for the engagement — the customer
 * hasn't funded it (or enough of it) yet. A 409: fund escrow, then retry.
 */
final class InsufficientEscrow extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'insufficient-escrow';
    }

    public function problemTitle(): string
    {
        return 'Not enough escrow held for this engagement';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
