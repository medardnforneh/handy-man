<?php

declare(strict_types=1);

use App\Domain\Engagements\AssignmentRole;
use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\QuoteStatus;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\OutboxMessage;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P2.5-05 acceptance (doc 06): the customer accepts a submitted quotation → an engagement plus a
 * milestone plan whose amounts sum to the agreed amount (deferred DB constraint). This is the
 * quote-path into `engagements`, converging with the offer path.
 */

/**
 * @return array{customer: User, provider: User, job: Job, quote: Quotation}
 */
function submittedQuoteScenario(int $subtotal = 900000, int $deposit = 100000): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create(['customer_party_id' => $customer->party_id]);
    $provider = User::factory()->create();
    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
        'subtotal_minor' => $subtotal,
        'deposit_minor' => $deposit,
        'valid_until' => now()->addDays(3),
    ]);

    return ['customer' => $customer, 'provider' => $provider, 'job' => $job, 'quote' => $quote];
}

it('accepts a quote → engagement (quote origin), milestones summing to the agreed amount', function () {
    ['customer' => $customer, 'provider' => $provider, 'job' => $job, 'quote' => $quote] = submittedQuoteScenario();

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/quotations/{$quote->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.quotation_id', $quote->id)
        ->assertJsonPath('data.offer_id', null)
        ->assertJsonPath('data.agreed_amount.amount_minor', 900000)
        ->assertJsonCount(2, 'data.milestones')
        ->assertJsonPath('data.milestones.0.title', 'Deposit')
        ->assertJsonPath('data.milestones.0.amount_minor', 100000)
        ->assertJsonPath('data.milestones.1.amount_minor', 800000);

    $engagement = Engagement::where('job_id', $job->id)->firstOrFail();
    expect(Job::findOrFail($job->id)->status)->toBe(JobStatus::Engaged)
        ->and(Quotation::findOrFail($quote->id)->status)->toBe(QuoteStatus::Accepted)
        ->and($engagement->milestones->sum('amount_minor'))->toBe(900000)
        ->and(OutboxMessage::where('type', 'quote.accepted')->count())->toBe(1)
        ->and(OutboxMessage::where('type', 'engagement.created')->count())->toBe(1);

    // Individual provider is auto-assigned as lead.
    expect(Assignment::where('engagement_id', $engagement->id)->where('role', AssignmentRole::Lead->value)->count())->toBe(1);
});

it('makes a single full-payment milestone when there is no deposit', function () {
    ['customer' => $customer, 'quote' => $quote] = submittedQuoteScenario(subtotal: 500000, deposit: 0);

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/quotations/{$quote->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonCount(1, 'data.milestones')
        ->assertJsonPath('data.milestones.0.amount_minor', 500000);
});

it('rejects competing live quotes on the job when one is accepted', function () {
    ['customer' => $customer, 'job' => $job, 'quote' => $quote] = submittedQuoteScenario();
    $rival = Quotation::factory()->submitted()->create(['job_id' => $job->id, 'valid_until' => now()->addDays(3)]);

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/quotations/{$quote->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    expect(Quotation::findOrFail($rival->id)->status)->toBe(QuoteStatus::Rejected);
});

it('lets only the job owner accept a quote', function () {
    ['quote' => $quote] = submittedQuoteScenario();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/quotations/{$quote->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();

    expect(Engagement::count())->toBe(0);
});

it('rejects accepting an expired quote (409)', function () {
    // valid_until is an immutable term, so build the quote already past its deadline.
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create(['customer_party_id' => $customer->party_id]);
    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id,
        'valid_until' => now()->subHour(),
    ]);

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/quotations/{$quote->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409)
        ->assertJsonPath('title', 'Quotation no longer available');
});

it('cannot accept the same quote twice (job already engaged)', function () {
    ['customer' => $customer, 'quote' => $quote] = submittedQuoteScenario();

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/quotations/{$quote->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    $this->postJson("/api/v1/quotations/{$quote->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);

    expect(Engagement::where('job_id', $quote->job_id)->count())->toBe(1);
});

it('enforces SUM(milestones) = agreed_amount via the deferred constraint', function () {
    $engagement = Engagement::factory()->create(['agreed_amount_minor' => 1000]);

    // A milestone plan that doesn't add up is rejected when the deferred check is forced.
    expect(function () use ($engagement) {
        DB::transaction(function () use ($engagement) {
            Milestone::factory()->create(['engagement_id' => $engagement->id, 'position' => 0, 'amount_minor' => 999]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE'); // force the deferred trigger to run now
        });
    })->toThrow(QueryException::class);
});
