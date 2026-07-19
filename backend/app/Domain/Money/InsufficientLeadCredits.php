<?php

declare(strict_types=1);

namespace App\Domain\Money;

use App\Support\ProblemAware;
use App\Support\ProvidesProblemExtras;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A provider tried to spend more lead credits than they hold. A 422 carrying the shortfall so the
 * app can prompt a top-up.
 */
final class InsufficientLeadCredits extends RuntimeException implements ProblemAware, ProvidesProblemExtras
{
    public function __construct(
        private readonly int $availableMinor,
        private readonly int $requestedMinor,
        private readonly string $currency,
    ) {
        parent::__construct('Insufficient lead credits.');
    }

    public function problemType(): string
    {
        return 'insufficient-lead-credits';
    }

    public function problemTitle(): string
    {
        return 'Insufficient lead credits';
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
