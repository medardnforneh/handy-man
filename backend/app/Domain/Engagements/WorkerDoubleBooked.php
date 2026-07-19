<?php

declare(strict_types=1);

namespace App\Domain\Engagements;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The worker already has an active assignment whose time window overlaps the requested one — a
 * double-booking (build plan P2-09). The DB EXCLUDE constraint `assignments_no_double_booking` is
 * the hard guarantee; this is the friendly 409 the app raises after its own pre-check (or after
 * catching the constraint under a race).
 */
final class WorkerDoubleBooked extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'worker-double-booked';
    }

    public function problemTitle(): string
    {
        return 'Worker is already booked for that time';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
