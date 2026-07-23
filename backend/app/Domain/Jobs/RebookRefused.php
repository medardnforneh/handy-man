<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A one-tap rebook was attempted for a provider the customer has never engaged (build plan P8-05) —
 * there's no prior job to clone.
 */
final class RebookRefused extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'rebook-refused';
    }

    public function problemTitle(): string
    {
        return 'No previous job with this provider to rebook';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
