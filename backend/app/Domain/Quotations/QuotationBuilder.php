<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

use App\Models\Quotation;
use Illuminate\Validation\ValidationException;

/**
 * Writes a quotation the immutable way (doc 06): create it in `draft`, add the (still-mutable) lines,
 * then transition to `submitted`. After that the terms and lines are frozen by DB triggers. Used by
 * both the first-submit and the revise Actions so the write path is identical.
 */
final class QuotationBuilder
{
    public function __construct(private readonly QuotationStateMachine $stateMachine) {}

    public function build(
        string $jobId,
        string $providerPartyId,
        int $version,
        ?string $supersedesId,
        QuoteDraft $draft,
    ): Quotation {
        $subtotal = $draft->subtotalMinor();

        if ($draft->depositMinor > $subtotal) {
            throw ValidationException::withMessages([
                'deposit_minor' => 'The deposit cannot exceed the quote subtotal.',
            ]);
        }

        $quote = Quotation::query()->create([
            'job_id' => $jobId,
            'provider_party_id' => $providerPartyId,
            'version' => $version,
            'supersedes_id' => $supersedesId,
            'status' => QuoteStatus::Draft->value,
            'currency' => 'XAF',
            'subtotal_minor' => $subtotal,
            'deposit_minor' => $draft->depositMinor,
            'notes' => $draft->notes,
            'customer_requested_by' => $draft->customerRequestedBy,
            'provider_estimated_at' => $draft->providerEstimatedAt,
            'provider_committed_at' => $draft->providerCommittedAt,
            'valid_until' => $draft->validUntil,
        ]);

        foreach ($draft->lines as $position => $line) {
            $quote->lines()->create([
                'position' => $position,
                'kind' => $line->kind->value,
                'label' => $line->label,
                'quantity' => $line->quantity,
                'unit_price_minor' => $line->unitPriceMinor,
            ]);
        }

        // draft → submitted freezes the terms and lines from here on.
        $this->stateMachine->transition($quote, QuoteStatus::Submitted);

        return $quote;
    }
}
