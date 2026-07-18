<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A {@see ProblemAware} exception that also contributes extra machine-readable members to the
 * problem+json body (e.g. the specific consent purpose, or a resolve deep link).
 */
interface ProvidesProblemExtras
{
    /**
     * @return array<string, mixed>
     */
    public function problemExtras(): array;
}
