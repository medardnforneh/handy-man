<?php

declare(strict_types=1);

namespace App\Domain\Access\Capabilities;

use App\Domain\Access\Facts\Fact;
use App\Domain\Access\Facts\FactDeriver;
use App\Domain\Access\PreconditionUnmetException;
use App\Models\User;

/**
 * Base class for a fact-gated capability (doc 10). A capability guards ONE high-stakes action by
 * naming the fact it requires and checking it inline — never a role check.
 *
 * Subclasses implement {@see authorize()} using {@see require()} to assert facts. When a fact is
 * unmet, `require()` throws a {@see PreconditionUnmetException} carrying the missing fact and a
 * deep link, which the API renders as a structured `precondition_unmet` prompt (not a 403).
 *
 * New sensitive action → new Capability subclass, not a new role.
 */
abstract class Capability
{
    public function __construct(
        protected readonly FactDeriver $facts,
    ) {}

    /**
     * A stable machine slug for this capability, e.g. `accept_paid_job`.
     */
    abstract public function key(): string;

    /**
     * Throw {@see PreconditionUnmetException} if the user may not perform the action in $context.
     *
     * @param  array<string, mixed>  $context
     */
    abstract public function authorize(User $user, array $context = []): void;

    /**
     * True when the action is allowed — a non-throwing convenience for UI hinting.
     *
     * @param  array<string, mixed>  $context
     */
    public function allows(User $user, array $context = []): bool
    {
        try {
            $this->authorize($user, $context);

            return true;
        } catch (PreconditionUnmetException) {
            return false;
        }
    }

    /**
     * Assert a (possibly tiered) fact, or throw a structured precondition failure.
     *
     * @param  array{type: string, deep_link: string}  $resolve
     * @param  array<string, mixed>  $context
     */
    protected function require(
        User $user,
        Fact $fact,
        array $resolve,
        int $requiredLevel = 1,
        array $context = [],
    ): void {
        $result = $this->facts->derive($user, $fact, $context);

        if (! $result->meets($requiredLevel)) {
            throw new PreconditionUnmetException(
                capability: $this->key(),
                missingFact: $fact,
                resolve: $resolve,
                requiredTier: $requiredLevel > 1 ? $requiredLevel : null,
            );
        }
    }
}
