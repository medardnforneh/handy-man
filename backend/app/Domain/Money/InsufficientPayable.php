<?php

declare(strict_types=1);

namespace App\Domain\Money;

use App\Support\ProblemAware;
use App\Support\ProvidesProblemExtras;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A provider requested a payout larger than their available (unreserved) payable balance. A 422
 * carrying what's available.
 */
final class InsufficientPayable extends RuntimeException implements ProblemAware, ProvidesProblemExtras
{
    public function __construct(
        private readonly int $availableMinor,
        private readonly int $requestedMinor,
        private readonly string $currency,
    ) {
        parent::__construct('Insufficient payable balance.');
    }

    public function problemType(): string
    {
        return 'insufficient-payable';
    }

    public function problemTitle(): string
    {
        return 'Insufficient payable balance';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    /**
     * @return array<string, mixed>
     */
    public function problemExtras(): array
    {
        return [
            'available' => ['amount_minor' => $this->availableMinor, 'currency' => $this->currency],
            'requested' => ['amount_minor' => $this->requestedMinor, 'currency' => $this->currency],
        ];
    }
}
