<?php

declare(strict_types=1);

use App\Models\FollowUp;
use App\Models\Job;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Outbox;
use App\Support\OutboxRelay;

/**
 * P2.5-06 acceptance (doc 07): the unconverted-quote nudges. On submission the customer gets a
 * quote_pending_customer nudge and a quote_expiring warning; both cancel when the quote settles.
 */

/**
 * @return array{customer: User, quote: Quotation}
 */
function submittedQuote(): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->create(['customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id]);
    $provider = User::factory()->create();
    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id, 'valid_until' => now()->addDays(5),
    ]);

    return compact('customer', 'quote');
}

it('schedules quote nudges on submission (P2.5-06)', function () {
    ['quote' => $quote] = submittedQuote();

    app(Outbox::class)->publish('quote.submitted', ['quotation_id' => $quote->id, 'job_id' => $quote->job_id]);
    app(OutboxRelay::class)->drain();

    expect(FollowUp::query()->where('kind', 'quote_pending_customer')->where('status', 'scheduled')->count())->toBe(1)
        ->and(FollowUp::query()->where('kind', 'quote_expiring')->where('status', 'scheduled')->count())->toBe(1);
});

it('cancels the quote nudges when the quote is accepted', function () {
    ['quote' => $quote] = submittedQuote();

    app(Outbox::class)->publish('quote.submitted', ['quotation_id' => $quote->id, 'job_id' => $quote->job_id]);
    app(OutboxRelay::class)->drain();

    app(Outbox::class)->publish('quote.accepted', ['quotation_id' => $quote->id, 'job_id' => $quote->job_id]);
    app(OutboxRelay::class)->drain();

    expect(FollowUp::query()->whereIn('kind', ['quote_pending_customer', 'quote_expiring'])->where('status', 'scheduled')->count())->toBe(0)
        ->and(FollowUp::query()->where('status', 'cancelled')->count())->toBe(2);
});
