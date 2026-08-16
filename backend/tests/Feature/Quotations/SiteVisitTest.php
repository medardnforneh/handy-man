<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\SiteVisitStatus;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\OutboxMessage;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P2.5-04 acceptance (doc 06): site visits can be scheduled and completed, a chargeable visit carries
 * a fee, and that fee is credited against the engagement when the quote it produced is accepted.
 */
function openJobForVisit(?User $customer = null): Job
{
    $customer ??= User::factory()->create();

    return Job::factory()->remote()->status(JobStatus::Open)->create(['customer_party_id' => $customer->party_id]);
}

it('schedules a free site visit', function () {
    $provider = User::factory()->create();
    $job = openJobForVisit();

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/jobs/{$job->id}/site-visits",
        ['scheduled_for' => now()->addDay()->toIso8601String()],
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.is_chargeable', false)
        ->assertJsonPath('data.fee.amount_minor', 0)
        ->assertJsonPath('data.status', 'scheduled');

    expect(OutboxMessage::where('type', 'site_visit.scheduled')->count())->toBe(1);
});

it('schedules a chargeable site visit with a fee', function () {
    $provider = User::factory()->create();
    $job = openJobForVisit();

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/jobs/{$job->id}/site-visits",
        ['scheduled_for' => now()->addDay()->toIso8601String(), 'is_chargeable' => true, 'fee_minor' => 30000],
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.is_chargeable', true)
        ->assertJsonPath('data.fee.amount_minor', 30000);
});

it('requires a fee for a chargeable visit (422)', function () {
    $provider = User::factory()->create();
    $job = openJobForVisit();

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/jobs/{$job->id}/site-visits",
        ['scheduled_for' => now()->addDay()->toIso8601String(), 'is_chargeable' => true],
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('completes a site visit, linking the resulting quotation', function () {
    $provider = User::factory()->create();
    $job = openJobForVisit();
    $visit = SiteVisit::factory()->chargeable()->create(['job_id' => $job->id, 'provider_party_id' => $provider->party_id]);
    $quote = Quotation::factory()->submitted()->create(['job_id' => $job->id, 'provider_party_id' => $provider->party_id]);

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/site-visits/{$visit->id}/complete",
        ['outcome_notes' => 'Needs new piping', 'resulting_quotation_id' => $quote->id],
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.resulting_quotation_id', $quote->id);

    expect(SiteVisit::findOrFail($visit->id)->completed_at)->not->toBeNull()
        ->and(OutboxMessage::where('type', 'site_visit.completed')->count())->toBe(1);
});

it('forbids completing another provider’s visit', function () {
    $visit = SiteVisit::factory()->create();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/site-visits/{$visit->id}/complete", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('rejects completing an already-completed visit (409)', function () {
    $provider = User::factory()->create();
    $visit = SiteVisit::factory()->completed()->create(['provider_party_id' => $provider->party_id]);

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/site-visits/{$visit->id}/complete", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409);
});

it('rejects a resulting quote from a different job/provider (422)', function () {
    $provider = User::factory()->create();
    $job = openJobForVisit();
    $visit = SiteVisit::factory()->create(['job_id' => $job->id, 'provider_party_id' => $provider->party_id]);
    $foreignQuote = Quotation::factory()->submitted()->create(); // unrelated job/provider

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/site-visits/{$visit->id}/complete",
        ['resulting_quotation_id' => $foreignQuote->id],
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('credits a completed chargeable visit fee against the engagement on acceptance', function () {
    $customer = User::factory()->create();
    $job = openJobForVisit($customer);
    $provider = User::factory()->create();

    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
        'subtotal_minor' => 900000,
        'deposit_minor' => 100000,
        'valid_until' => now()->addDays(3),
    ]);
    SiteVisit::factory()->chargeable(30000)->completed()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
        'resulting_quotation_id' => $quote->id,
    ]);

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/quotations/{$quote->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.agreed_amount.amount_minor', 870000) // 900000 - 30000
        ->assertJsonPath('data.visit_credit.amount_minor', 30000)
        ->assertJsonPath('data.milestones.0.amount_minor', 70000)   // deposit 100000 - 30000 credit
        ->assertJsonPath('data.milestones.1.amount_minor', 800000); // balance

    $engagement = Engagement::where('job_id', $job->id)->firstOrFail();
    expect($engagement->milestones->sum('amount_minor'))->toBe(870000)
        ->and($engagement->agreed_amount_minor)->toBe(870000);
});

it('does not double-count a scheduled (not completed) visit fee', function () {
    $customer = User::factory()->create();
    $job = openJobForVisit($customer);
    $provider = User::factory()->create();

    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id,
        'subtotal_minor' => 500000, 'deposit_minor' => 0, 'valid_until' => now()->addDays(3),
    ]);
    // Chargeable but only SCHEDULED → not creditable yet.
    SiteVisit::factory()->chargeable(50000)->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id, 'resulting_quotation_id' => $quote->id,
        'status' => SiteVisitStatus::Scheduled->value,
    ]);

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/quotations/{$quote->id}/accept", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.agreed_amount.amount_minor', 500000)
        ->assertJsonPath('data.visit_credit.amount_minor', 0);
});

/**
 * The self-scoped read (P2.5-04). It exists because completing a visit was otherwise unreachable:
 * a visit is narrated into no thread and appears in no other list, so its id survived only inside
 * the session that scheduled it.
 */
it('lists the caller’s own site visits, open ones first', function () {
    $provider = User::factory()->create();
    $other = User::factory()->create();

    $soon = SiteVisit::factory()->create([
        'job_id' => openJobForVisit()->id,
        'provider_party_id' => $provider->party_id,
        'scheduled_for' => now()->addDay(),
        'status' => SiteVisitStatus::Scheduled->value,
    ]);
    $done = SiteVisit::factory()->create([
        'job_id' => openJobForVisit()->id,
        'provider_party_id' => $provider->party_id,
        'scheduled_for' => now()->subDay(),
        'status' => SiteVisitStatus::Completed->value,
    ]);
    // Somebody else's visit must not appear, whoever asks.
    SiteVisit::factory()->create([
        'job_id' => openJobForVisit()->id,
        'provider_party_id' => $other->party_id,
        'status' => SiteVisitStatus::Scheduled->value,
    ]);

    Sanctum::actingAs($provider);
    $response = $this->getJson('/api/v1/provider/site-visits')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // Scheduled outranks completed: the list is a to-do, not a history.
    expect($response->json('data.0.id'))->toBe($soon->id)
        ->and($response->json('data.1.id'))->toBe($done->id);
});

it('minimises the embedded job’s location for a pre-engagement provider', function () {
    $customer = User::factory()->create();
    $job = Job::factory()->status(JobStatus::Open)->create(['customer_party_id' => $customer->party_id]);
    $provider = User::factory()->create();

    SiteVisit::factory()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
        'status' => SiteVisitStatus::Scheduled->value,
    ]);

    Sanctum::actingAs($provider);
    $location = $this->getJson('/api/v1/provider/site-visits')
        ->assertOk()
        ->json('data.0.job.location');

    // Booking a visit does not buy the street: the coarse area is all a pre-engagement provider
    // sees anywhere else, and this list inherits that rather than deciding it again (P2-03).
    expect($location)->toHaveKey('city')
        ->and($location)->not->toHaveKey('line1')
        ->and($location)->not->toHaveKey('latitude');
});

it('completes a visit found through that list', function () {
    $provider = User::factory()->create();
    $visit = SiteVisit::factory()->create([
        'job_id' => openJobForVisit()->id,
        'provider_party_id' => $provider->party_id,
        'status' => SiteVisitStatus::Scheduled->value,
    ]);

    Sanctum::actingAs($provider);
    $id = $this->getJson('/api/v1/provider/site-visits')->assertOk()->json('data.0.id');

    $this->postJson("/api/v1/site-visits/{$id}/complete",
        ['outcome_notes' => 'Corroded riser, needs replacing before anything else.'],
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect($visit->fresh()->outcome_notes)->toBe('Corroded riser, needs replacing before anything else.');
});
