<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Domain\Workspace\Actions\SubmitDeliverable;
use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An on-site job report was attempted on an engagement with no site (build plan P5-04, doc 06). The
 * report documents physical work — materials consumed and before/after photos OF A PLACE — so it
 * belongs to the same modes as check-in. Remote work proves itself through deliverables instead
 * ({@see SubmitDeliverable}), which the customer reviews.
 */
final class JobReportNotSupported extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'job-report-not-supported';
    }

    public function problemTitle(): string
    {
        return 'This engagement uses deliverables, not an on-site report';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
