<?php

declare(strict_types=1);

namespace App\Domain\Access\Facts;

/**
 * The outcome of deriving a {@see Fact} for a subject.
 *
 * `level` carries the strength for tiered facts (e.g. identity_verified: 0 = none, 1 = light /
 * phone-confirmed, 2 = full ID, 3 = high-risk tier). For boolean facts, `level` is 1 when
 * satisfied and 0 otherwise, so a single comparison (`level >= requiredLevel`) works for both.
 */
final readonly class FactResult
{
    public function __construct(
        public bool $satisfied,
        public int $level = 0,
    ) {}

    public static function unmet(): self
    {
        return new self(satisfied: false, level: 0);
    }

    public static function met(int $level = 1): self
    {
        return new self(satisfied: true, level: $level);
    }

    /**
     * A tiered fact satisfied to at least $requiredLevel.
     */
    public static function tier(int $level): self
    {
        return new self(satisfied: $level > 0, level: $level);
    }

    public function meets(int $requiredLevel): bool
    {
        return $this->level >= $requiredLevel;
    }
}
