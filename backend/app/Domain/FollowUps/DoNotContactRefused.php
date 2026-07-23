<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A manual follow-up was attempted to a customer on the provider's do-not-contact list (build plan
 * P7-08). Do-not-contact is honoured absolutely.
 */
final class DoNotContactRefused extends RuntimeException implements ProblemAware
{
    public function problemType(): string
    {
        return 'do-not-contact';
    }

    public function problemTitle(): string
    {
        return 'This customer is on your do-not-contact list';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
