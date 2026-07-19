<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The provider already has a live (draft or submitted) quote for this job — there can be only one
 * (DB-enforced, P2.5-02). To change the terms they must revise the existing quote, not submit a
 * second one.
 */
final class QuoteAlreadyLive extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'quote-already-live';
    }

    public function problemTitle(): string
    {
        return 'A live quote already exists for this job';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
