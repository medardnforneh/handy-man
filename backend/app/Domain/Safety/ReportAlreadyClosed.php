<?php

declare(strict_types=1);

namespace App\Domain\Safety;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A second decision was attempted on an already-closed report (build plan P6-07). Refused rather
 * than silently overwritten: the first decision's attribution in the activity log is the record of
 * who closed it, and a quiet overwrite would leave two conflicting accounts of the same report.
 */
final class ReportAlreadyClosed extends RuntimeException implements ProblemAware
{
    public function __construct(private readonly string $currentStatus)
    {
        parent::__construct("This report is already {$currentStatus}.");
    }

    public function problemType(): string
    {
        return 'report-already-closed';
    }

    public function problemTitle(): string
    {
        return 'This report has already been decided';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function currentStatus(): string
    {
        return $this->currentStatus;
    }
}
