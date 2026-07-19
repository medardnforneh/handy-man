<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The quotation can no longer be accepted — it isn't the live submitted version, it has expired, or
 * the job was engaged in the meantime (another quote/offer won). A 409: refresh, don't retry.
 */
final class QuoteNotAcceptable extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'quote-not-acceptable';
    }

    public function problemTitle(): string
    {
        return 'Quotation no longer available';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
