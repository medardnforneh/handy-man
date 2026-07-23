<?php

declare(strict_types=1);

namespace App\Domain\Reviews;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The author already reviewed this engagement (build plan P6-08). One review per author per
 * engagement — the DB unique index is the hard guarantee; this is the friendly 409.
 */
final class AlreadyReviewed extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'already-reviewed';
    }

    public function problemTitle(): string
    {
        return 'You have already reviewed this engagement';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
