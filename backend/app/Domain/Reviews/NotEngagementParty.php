<?php

declare(strict_types=1);

namespace App\Domain\Reviews;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A review was attempted by someone who is neither party to the engagement (build plan P6-08). Only
 * the customer and the provider may review each other.
 */
final class NotEngagementParty extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'not-engagement-party';
    }

    public function problemTitle(): string
    {
        return 'Only the parties to this engagement can review it';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
