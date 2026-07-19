<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

use App\Domain\Quotations\Actions\ReviseQuotation;
use App\Domain\Quotations\Actions\SubmitQuotation;
use Carbon\CarbonInterface;

/**
 * The provider-supplied contents of a quotation, independent of version/supersession bookkeeping.
 * Shared by {@see SubmitQuotation} and
 * {@see ReviseQuotation}.
 */
final readonly class QuoteDraft
{
    /**
     * @param  list<QuoteLineInput>  $lines
     */
    public function __construct(
        public int $depositMinor,
        public ?string $notes,
        public CarbonInterface $validUntil,
        public ?CarbonInterface $customerRequestedBy,
        public ?CarbonInterface $providerEstimatedAt,
        public ?CarbonInterface $providerCommittedAt,
        public array $lines,
    ) {}

    public function subtotalMinor(): int
    {
        return array_sum(array_map(fn (QuoteLineInput $l): int => $l->totalMinor(), $this->lines));
    }
}
