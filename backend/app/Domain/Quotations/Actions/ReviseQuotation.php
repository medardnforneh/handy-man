<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Quotations\QuotationBuilder;
use App\Domain\Quotations\QuotationStateMachine;
use App\Domain\Quotations\QuoteDraft;
use App\Domain\Quotations\QuoteStatus;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * A provider revises a submitted quotation (build plan P2.5-01, doc 06 / rule #9). Never an edit:
 * the previous version is marked `superseded` (freeing the one-live-quote slot) and a NEW version is
 * created with `supersedes_id` pointing back — the version chain is the negotiation record.
 */
final class ReviseQuotation
{
    public function __construct(
        private readonly QuotationBuilder $builder,
        private readonly QuotationStateMachine $stateMachine,
        private readonly Outbox $outbox,
    ) {}

    public function handle(User $provider, Quotation $previous, QuoteDraft $draft): Quotation
    {
        return DB::transaction(function () use ($previous, $draft): Quotation {
            // Supersede first so the partial unique index frees before the new version is written.
            $this->stateMachine->transition($previous, QuoteStatus::Superseded);

            $quote = $this->builder->build(
                $previous->job_id,
                $previous->provider_party_id,
                $previous->version + 1,
                $previous->id,
                $draft,
            );

            $this->outbox->publish('quote.revised', [
                'quotation_id' => $quote->id,
                'supersedes_id' => $previous->id,
                'job_id' => $previous->job_id,
                'provider_party_id' => $previous->provider_party_id,
                'version' => $quote->version,
            ]);

            return $quote;
        });
    }
}
