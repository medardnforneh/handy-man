<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

use App\Models\Quotation;

/**
 * The only place a quotation's status changes (CLAUDE.md rule #8). Illegal transitions throw.
 * Content is never touched here — terms are immutable once submitted (doc 06, DB-enforced).
 */
final class QuotationStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'draft' => ['submitted', 'withdrawn'],
        'submitted' => ['accepted', 'rejected', 'expired', 'withdrawn', 'superseded'],
        'accepted' => [],
        'rejected' => [],
        'expired' => [],
        'withdrawn' => [],
        'superseded' => [],
    ];

    public function canTransition(QuoteStatus $from, QuoteStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }

    public function transition(Quotation $quote, QuoteStatus $to): void
    {
        $from = $quote->status;

        if (! $this->canTransition($from, $to)) {
            throw new IllegalQuoteTransition("Cannot move quotation from {$from->value} to {$to->value}.");
        }

        $quote->status = $to;

        if ($to === QuoteStatus::Submitted && $quote->submitted_at === null) {
            $quote->submitted_at = now();
        }

        if (in_array($to, [QuoteStatus::Accepted, QuoteStatus::Rejected], true) && $quote->responded_at === null) {
            $quote->responded_at = now();
        }

        $quote->save();
    }
}
