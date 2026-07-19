<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Engagements\LeadAssigner;
use App\Domain\Jobs\JobStateMachine;
use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\QuotationStateMachine;
use App\Domain\Quotations\QuoteNotAcceptable;
use App\Domain\Quotations\QuoteStatus;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * The customer accepts a submitted quotation (build plan P2.5-05). This is the quote-path entry into
 * `engagements` — it converges with the offer path (P2-06). Concurrency-safe: the job row is locked,
 * so exactly one engagement forms even if a quote and an offer are accepted at once.
 *
 * On acceptance it generates the milestone plan (deposit at position 0, then the balance) whose
 * amounts sum to the agreed amount — the deferred DB constraint `milestones_sum_matches_agreed` is
 * the guarantee. Competing live quotes on the job are rejected.
 *
 * Provider-side verification (accept-paid-job, doc 10) is enforced in Phase 6; a provider commits by
 * submitting the quote, and the engagement forms here on the customer's acceptance.
 */
final class AcceptQuotation
{
    public function __construct(
        private readonly JobStateMachine $jobStateMachine,
        private readonly QuotationStateMachine $quoteStateMachine,
        private readonly LeadAssigner $leadAssigner,
        private readonly Outbox $outbox,
    ) {}

    public function handle(User $customer, Quotation $quotation): Engagement
    {
        return DB::transaction(function () use ($quotation): Engagement {
            $job = Job::query()->whereKey($quotation->job_id)->lockForUpdate()->firstOrFail();
            $quote = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if ($quote->status !== QuoteStatus::Submitted
                || $quote->valid_until->isPast()
                || ! in_array($job->status, [JobStatus::Open, JobStatus::Offered], true)) {
                throw new QuoteNotAcceptable;
            }

            $this->quoteStateMachine->transition($quote, QuoteStatus::Accepted);

            // Competing live quotes on this job are now moot.
            Quotation::query()
                ->where('job_id', $job->id)
                ->whereKeyNot($quote->id)
                ->where('status', QuoteStatus::Submitted->value)
                ->update(['status' => QuoteStatus::Rejected->value, 'responded_at' => now()]);

            $this->jobStateMachine->transition($job, JobStatus::Engaged);

            $engagement = Engagement::query()->create([
                'job_id' => $job->id,
                'provider_party_id' => $quote->provider_party_id,
                'quotation_id' => $quote->id,
                'agreed_amount_minor' => $quote->subtotal_minor,
                'currency' => $quote->currency,
                'accepted_at' => now(),
            ]);

            foreach ($this->milestonePlan($quote) as $milestone) {
                $engagement->milestones()->create($milestone);
            }

            $this->leadAssigner->assignIfIndividual($engagement, $quote->provider_party_id);

            $this->outbox->publish('quote.accepted', [
                'quotation_id' => $quote->id,
                'job_id' => $job->id,
                'engagement_id' => $engagement->id,
            ]);
            $this->outbox->publish('engagement.created', [
                'engagement_id' => $engagement->id,
                'job_id' => $job->id,
                'provider_party_id' => $quote->provider_party_id,
            ]);

            return $engagement;
        });
    }

    /**
     * The default milestone plan: deposit (position 0) then balance, or a single full-payment
     * milestone. Amounts always sum to the quote subtotal (= the agreed amount).
     *
     * @return list<array{position: int, title: string, amount_minor: int}>
     */
    private function milestonePlan(Quotation $quote): array
    {
        $subtotal = $quote->subtotal_minor;
        $deposit = $quote->deposit_minor;

        if ($deposit > 0 && $deposit < $subtotal) {
            return [
                ['position' => 0, 'title' => 'Deposit', 'amount_minor' => $deposit],
                ['position' => 1, 'title' => 'Balance', 'amount_minor' => $subtotal - $deposit],
            ];
        }

        return [['position' => 0, 'title' => 'Full payment', 'amount_minor' => $subtotal]];
    }
}
