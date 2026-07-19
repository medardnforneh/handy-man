<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

/**
 * One input line for a quotation draft. `quantity` is a decimal string (matches the numeric(12,3)
 * column) to avoid float drift; the money maths uses integer minor units.
 */
final readonly class QuoteLineInput
{
    public function __construct(
        public QuoteLineKind $kind,
        public string $label,
        public string $quantity,
        public int $unitPriceMinor,
    ) {}

    /**
     * The line total in minor units, rounded to the nearest whole minor unit.
     */
    public function totalMinor(): int
    {
        return (int) round((float) $this->quantity * $this->unitPriceMinor);
    }
}
