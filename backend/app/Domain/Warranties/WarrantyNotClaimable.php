<?php

declare(strict_types=1);

namespace App\Domain\Warranties;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A claim was attempted on a warranty that is not active (already claimed, expired or void) — build
 * plan P6-11.
 */
final class WarrantyNotClaimable extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'warranty-not-claimable';
    }

    public function problemTitle(): string
    {
        return 'This warranty is not claimable';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
