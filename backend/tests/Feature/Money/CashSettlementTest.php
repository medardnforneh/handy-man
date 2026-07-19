<?php

declare(strict_types=1);

use App\Domain\Engagements\MilestoneStatus;
use App\Domain\Jobs\JobStatus;
use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P3-15 acceptance (doc 03): cash settlement recording. A provider records a cash-settled amount; the
 * ledger reflects it by booking the platform commission as revenue AND as a debt the provider owes
 * (provider_receivable). Cash is first-class, not a rounding error.
 */

/**
 * @return array{provider: User, engagement: Engagement}
 */
function cashEngagement(): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create(['customer_party_id' => $customer->party_id]);
    $provider = User::factory()->create();
    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id,
        'subtotal_minor' => 1_000_000, 'deposit_minor' => 200_000, 'valid_until' => now()->addDays(3),
    ]);
    $engagement = app(AcceptQuotation::class)->handle($customer, $quote);

    return ['provider' => $provider, 'engagement' => $engagement];
}

it('records a cash settlement, booking commission as revenue and a provider debt', function () {
    ['provider' => $provider, 'engagement' => $engagement] = cashEngagement();
    $ledger = app(Ledger::class);

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/engagements/{$engagement->id}/cash-settlements",
        ['amount_minor' => 100_000],
        ['Idempotency-Key' => (string) Str::uuid()],
    )
        ->assertCreated()
        ->assertJsonPath('data.amount.amount_minor', 100_000)
        ->assertJsonPath('data.commission.amount_minor', 15_000); // 15%

    // DR provider_receivable(pro) 15,000 / CR platform_revenue 15,000.
    expect($ledger->availableMinor(AccountKind::ProviderReceivable, $provider->party_id))->toBe(15_000)
        ->and($ledger->availableMinor(AccountKind::PlatformRevenue))->toBe(15_000);
});

it('marks the named milestone paid when settling it in cash', function () {
    ['provider' => $provider, 'engagement' => $engagement] = cashEngagement();
    $milestone = $engagement->milestones()->where('position', 0)->firstOrFail();

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/engagements/{$engagement->id}/cash-settlements",
        ['amount_minor' => 200_000, 'milestone_id' => $milestone->id],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated();

    expect(Milestone::findOrFail($milestone->id)->status)->toBe(MilestoneStatus::Paid);
});

it('lets only the engagement provider record a cash settlement (403)', function () {
    ['engagement' => $engagement] = cashEngagement();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/engagements/{$engagement->id}/cash-settlements",
        ['amount_minor' => 100_000],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertForbidden();
});
