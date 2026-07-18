<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Support\ProblemAware;
use App\Support\ProvidesProblemExtras;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when an action requires a consent the user hasn't granted (doc 04). Rendered to
 * problem+json; the app prompts for the specific consent. Carries the missing purpose so the
 * client can render the right prompt.
 */
final class ConsentRequiredException extends RuntimeException implements ProblemAware, ProvidesProblemExtras
{
    public function __construct(public readonly string $purpose)
    {
        parent::__construct("Consent required: {$purpose}");
    }

    /**
     * @return array<string, mixed>
     */
    public function problemExtras(): array
    {
        return ['missing_purpose' => $this->purpose];
    }

    public function problemType(): string
    {
        return 'consent-required';
    }

    public function problemTitle(): string
    {
        return 'Consent required';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
