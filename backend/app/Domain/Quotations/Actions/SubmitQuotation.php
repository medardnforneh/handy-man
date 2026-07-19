<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Quotations\QuotationBuilder;
use App\Domain\Quotations\QuoteAlreadyLive;
use App\Domain\Quotations\QuoteDraft;
use App\Models\Job;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * A provider submits a fresh quotation for a job (build plan P2.5-01). The version continues from any
 * prior (terminal) quotes; a still-live quote means they must revise instead ({@see QuoteAlreadyLive}
 * — the `one_live_quote_per_provider_per_job` index is the DB backstop).
 */
final class SubmitQuotation
{
    public function __construct(
        private readonly QuotationBuilder $builder,
        private readonly Outbox $outbox,
    ) {}

    public function handle(User $provider, Job $job, QuoteDraft $draft): Quotation
    {
        return DB::transaction(function () use ($provider, $job, $draft): Quotation {
            $hasLive = Quotation::query()
                ->where('job_id', $job->id)
                ->where('provider_party_id', $provider->party_id)
                ->whereIn('status', ['draft', 'submitted'])
                ->exists();

            if ($hasLive) {
                throw new QuoteAlreadyLive;
            }

            $version = (int) Quotation::query()
                ->where('job_id', $job->id)
                ->where('provider_party_id', $provider->party_id)
                ->max('version') + 1;

            try {
                $quote = $this->builder->build($job->id, $provider->party_id, $version, null, $draft);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'one_live_quote_per_provider_per_job')) {
                    throw new QuoteAlreadyLive;
                }
                throw $e;
            }

            $this->outbox->publish('quote.submitted', [
                'quotation_id' => $quote->id,
                'job_id' => $job->id,
                'provider_party_id' => $provider->party_id,
                'version' => $quote->version,
            ]);

            return $quote;
        });
    }
}
