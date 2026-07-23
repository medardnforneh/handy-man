<?php

declare(strict_types=1);

namespace App\Domain\Referrals;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A referral claim was refused (build plan P8-01): an unknown code, a self-referral, or a referee who
 * was already referred (duplicate). The `$reason` distinguishes them for the client.
 */
final class ReferralRefused extends RuntimeException implements ProblemAware
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Referral refused: {$reason}");
    }

    public function problemType(): string
    {
        return 'referral-refused';
    }

    public function problemTitle(): string
    {
        return 'This referral cannot be claimed';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
