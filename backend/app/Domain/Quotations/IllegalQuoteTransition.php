<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An attempt to move a quotation through an illegal status transition (doc 06 / rule #8).
 */
final class IllegalQuoteTransition extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'illegal-quote-transition';
    }

    public function problemTitle(): string
    {
        return 'Illegal quotation transition';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
