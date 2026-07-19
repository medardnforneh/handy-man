<?php

declare(strict_types=1);

namespace App\Domain\Engagements;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The assignment can't be made because it collides with an existing one — the worker is already
 * assigned to this engagement, or a second `lead` was requested while one is already active. Backed
 * by `UNIQUE(engagement_id, worker_user_id)` and the `one_lead_per_engagement` partial index.
 */
final class AssignmentConflict extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'assignment-conflict';
    }

    public function problemTitle(): string
    {
        return 'Assignment conflicts with an existing one';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
