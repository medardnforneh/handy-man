<?php

declare(strict_types=1);

namespace App\Domain\Engagements;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The worker being assigned does not belong to the engagement's provider — a different org's staff,
 * or an unrelated individual. The DB trigger `assignments_worker_boundary_check` is the hard
 * guarantee; this is the friendly app-layer rejection so clients get a 422, not a raw DB error.
 */
final class WorkerNotInProviderOrg extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'worker-not-in-provider-org';
    }

    public function problemTitle(): string
    {
        return 'Worker does not belong to the provider';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
