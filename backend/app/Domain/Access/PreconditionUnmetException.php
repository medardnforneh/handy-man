<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Domain\Access\Facts\Fact;
use RuntimeException;

/**
 * Thrown by a {@see Capabilities\Capability} when its required fact is not satisfied (doc 10).
 *
 * This is NOT a 403. It is a first-class, machine-readable "precondition_unmet" that names the
 * missing fact and a deep link to satisfy it, so the app renders an inline prompt instead of
 * dead-ending. Rendered to problem+json (409) in bootstrap/app.php.
 */
final class PreconditionUnmetException extends RuntimeException
{
    /**
     * @param  array{type: string, deep_link: string}  $resolve
     */
    public function __construct(
        public readonly string $capability,
        public readonly Fact $missingFact,
        public readonly array $resolve,
        public readonly ?int $requiredTier = null,
    ) {
        parent::__construct("Precondition unmet for {$capability}: {$missingFact->value}");
    }
}
