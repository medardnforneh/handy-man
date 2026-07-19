<?php

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The offer can no longer be accepted — it lapsed, was superseded, or another provider won the race
 * (the job is already engaged). A 409, so the client refreshes rather than retries.
 */
final class OfferNotAcceptable extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'offer-not-acceptable';
    }

    public function problemTitle(): string
    {
        return 'Offer no longer available';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
