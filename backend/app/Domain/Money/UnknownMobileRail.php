<?php

declare(strict_types=1);

namespace App\Domain\Money;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A mobile-money number no operator claims: neither MTN's nor Orange's prefixes, and the caller did
 * not say which rail to use. The gateway would refuse it a request later, with a worse message.
 */
final class UnknownMobileRail extends RuntimeException implements ProblemAware
{
    public function __construct(private readonly string $msisdn)
    {
        parent::__construct("No mobile money operator recognised for {$msisdn}.");
    }

    public function problemType(): string
    {
        return 'unknown-mobile-rail';
    }

    public function problemTitle(): string
    {
        return 'Which mobile money?';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
