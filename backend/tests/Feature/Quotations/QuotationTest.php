<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\QuoteStatus;
use App\Models\Job;
use App\Models\OutboxMessage;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P2.5-01/02/03 acceptance (doc 06): quotations are versioned and immutable — a submitted quote's
 * terms/lines can't be UPDATEd (revision = a new version with supersedes_id); only one live quote
 * per provider per job; the three dates are captured distinctly.
 */
function openJob(): Job
{
    $customer = User::factory()->create();

    return Job::factory()->remote()->status(JobStatus::Open)->create([
        'customer_party_id' => $customer->party_id,
    ]);
}

/**
 * @param  list<array<string, mixed>>|null  $lines
 */
function quotePayload(?array $lines = null): array
{
    return [
        'lines' => $lines ?? [
            ['kind' => 'labour', 'label' => 'Install', 'quantity' => 2, 'unit_price_minor' => 300000],
            ['kind' => 'material', 'label' => 'Cable', 'quantity' => 1.5, 'unit_price_minor' => 200000],
        ],
        'deposit_minor' => 100000,
        'notes' => 'Two-day job',
        'valid_until' => now()->addDays(7)->toIso8601String(),
        'provider_committed_at' => now()->addDays(5)->toIso8601String(),
    ];
}

it('submits a quotation, computing the subtotal from the lines', function () {
    $provider = User::factory()->create();
    $job = openJob();

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/jobs/{$job->id}/quotations", quotePayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.subtotal.amount_minor', 900000) // 2*300000 + 1.5*200000
        ->assertJsonPath('data.deposit.amount_minor', 100000)
        ->assertJsonCount(2, 'data.lines');

    expect(OutboxMessage::where('type', 'quote.submitted')->count())->toBe(1)
        ->and(Quotation::where('job_id', $job->id)->where('provider_party_id', $provider->party_id)->value('provider_committed_at'))->not->toBeNull();
});

it('rejects a second live quote from the same provider (one live quote per job)', function () {
    $provider = User::factory()->create();
    $job = openJob();

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/jobs/{$job->id}/quotations", quotePayload(), ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    $this->postJson("/api/v1/jobs/{$job->id}/quotations", quotePayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409)
        ->assertJsonPath('title', 'A live quote already exists for this job');
});

it('revises a submitted quote into a new version, superseding the old one', function () {
    $provider = User::factory()->create();
    $job = openJob();

    Sanctum::actingAs($provider);
    $v1 = $this->postJson("/api/v1/jobs/{$job->id}/quotations", quotePayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()->json('data.id');

    $revised = quotePayload([
        ['kind' => 'labour', 'label' => 'Install', 'quantity' => 3, 'unit_price_minor' => 300000],
    ]);
    $this->postJson("/api/v1/quotations/{$v1}/revise", $revised, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.supersedes_id', $v1)
        ->assertJsonPath('data.subtotal.amount_minor', 900000);

    expect(Quotation::findOrFail($v1)->status)->toBe(QuoteStatus::Superseded)
        ->and(Quotation::where('job_id', $job->id)->whereIn('status', ['draft', 'submitted'])->count())->toBe(1)
        ->and(OutboxMessage::where('type', 'quote.revised')->count())->toBe(1);
});

it('forbids revising another provider’s quote', function () {
    $provider = User::factory()->create();
    $job = openJob();
    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id,
    ]);

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/quotations/{$quote->id}/revise", quotePayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('rejects a deposit greater than the subtotal (422)', function () {
    $provider = User::factory()->create();
    $job = openJob();

    Sanctum::actingAs($provider);
    $payload = quotePayload();
    $payload['deposit_minor'] = 99_000_000; // way over subtotal

    $this->postJson("/api/v1/jobs/{$job->id}/quotations", $payload, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('DB-freezes the terms of a submitted quote (UPDATE throws)', function () {
    $quote = Quotation::factory()->submitted()->create();

    // Changing a term on a non-draft quote is rejected by the immutability trigger.
    expect(fn () => $quote->forceFill(['subtotal_minor' => 1])->save())
        ->toThrow(QueryException::class);
});

it('DB-freezes the lines of a submitted quote (INSERT throws)', function () {
    $quote = Quotation::factory()->submitted()->create();

    expect(fn () => QuotationLine::factory()->create(['quotation_id' => $quote->id, 'position' => 5]))
        ->toThrow(QueryException::class);
});

it('allows editing a DRAFT quote’s terms (immutability only bites after submit)', function () {
    $quote = Quotation::factory()->create(); // draft

    $quote->forceFill(['subtotal_minor' => 123456])->save();

    expect($quote->fresh()->subtotal_minor)->toBe(123456);
});

it('rejects quotations on a job that is not open/offered (409)', function () {
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Draft)->create(['customer_party_id' => $customer->party_id]);

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/jobs/{$job->id}/quotations", quotePayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409);
});
