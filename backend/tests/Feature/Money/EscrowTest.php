<?php

declare(strict_types=1);

use App\Domain\Engagements\MilestoneStatus;
use App\Domain\Jobs\JobStatus;
use App\Domain\Money\AccountKind;
use App\Domain\Money\Actions\ApproveMilestone;
use App\Domain\Money\Actions\InitiatePaymentIntent;
use App\Domain\Money\Actions\ReconcilePaymentIntent;
use App\Domain\Money\Actions\RefundEngagement;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\InsufficientEscrow;
use App\Domain\Money\Ledger;
use App\Domain\Money\PaymentPurpose;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P3-10/14 acceptance (doc 03/06): escrow release on milestone approval and refund. Each approval
 * releases only its milestone's slice (net of commission) — a partial approval leaves the remainder
 * escrowed. A refund returns whatever escrow remains.
 */

/**
 * @return array{customer: User, provider: User, engagement: Engagement}
 */
function engageWithMilestones(int $subtotal, int $deposit): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create(['customer_party_id' => $customer->party_id]);
    $provider = User::factory()->create();
    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id,
        'subtotal_minor' => $subtotal, 'deposit_minor' => $deposit, 'valid_until' => now()->addDays(3),
    ]);
    $engagement = app(AcceptQuotation::class)->handle($customer, $quote);

    return ['customer' => $customer, 'provider' => $provider, 'engagement' => $engagement];
}

function fundEscrow(User $customer, Engagement $engagement, int $amount): void
{
    $intent = app(InitiatePaymentIntent::class)->handle(
        $customer, PaymentPurpose::Escrow, $amount, '+237650000000', (string) Str::uuid(), $engagement->id,
    );
    app(PaymentGateway::class)->settle($intent->external_ref, GatewayStatus::Succeeded);
    app(ReconcilePaymentIntent::class)->handle($intent->fresh());
}

it('holds collected escrow against the engagement', function () {
    ['customer' => $customer, 'engagement' => $engagement] = engageWithMilestones(1_000_000, 200_000);
    fundEscrow($customer, $engagement, 1_000_000);

    expect(app(Ledger::class)->escrowHeldMinor($engagement->id))->toBe(1_000_000);
});

it('releases a milestone slice on approval, leaving the remainder escrowed (partial release)', function () {
    $ledger = app(Ledger::class);
    ['customer' => $customer, 'provider' => $provider, 'engagement' => $engagement] = engageWithMilestones(1_000_000, 200_000);
    fundEscrow($customer, $engagement, 1_000_000);

    $deposit = $engagement->milestones()->where('position', 0)->firstOrFail();
    app(ApproveMilestone::class)->handle($deposit);

    // Deposit 200,000 released: fee 15% = 30,000, provider net 170,000. Balance 800,000 stays escrowed.
    expect($ledger->escrowHeldMinor($engagement->id))->toBe(800_000)
        ->and($ledger->availableMinor(AccountKind::ProviderPayable, $provider->party_id))->toBe(170_000)
        ->and($ledger->availableMinor(AccountKind::PlatformRevenue))->toBe(30_000)
        ->and(Milestone::findOrFail($deposit->id)->status)->toBe(MilestoneStatus::Paid);
});

it('releases the full agreed amount once every milestone is approved', function () {
    $ledger = app(Ledger::class);
    ['customer' => $customer, 'provider' => $provider, 'engagement' => $engagement] = engageWithMilestones(1_000_000, 200_000);
    fundEscrow($customer, $engagement, 1_000_000);

    foreach ($engagement->milestones as $milestone) {
        app(ApproveMilestone::class)->handle($milestone);
    }

    expect($ledger->escrowHeldMinor($engagement->id))->toBe(0)
        ->and($ledger->availableMinor(AccountKind::ProviderPayable, $provider->party_id))->toBe(850_000)
        ->and($ledger->availableMinor(AccountKind::PlatformRevenue))->toBe(150_000);
});

it('rejects approving a milestone when escrow is not funded (409)', function () {
    ['engagement' => $engagement] = engageWithMilestones(1_000_000, 200_000);
    $deposit = $engagement->milestones()->where('position', 0)->firstOrFail();

    expect(fn () => app(ApproveMilestone::class)->handle($deposit))
        ->toThrow(InsufficientEscrow::class);
});

it('is idempotent — approving a paid milestone releases nothing more', function () {
    $ledger = app(Ledger::class);
    ['customer' => $customer, 'engagement' => $engagement] = engageWithMilestones(1_000_000, 200_000);
    fundEscrow($customer, $engagement, 1_000_000);

    $deposit = $engagement->milestones()->where('position', 0)->firstOrFail();
    app(ApproveMilestone::class)->handle($deposit);
    app(ApproveMilestone::class)->handle($deposit->fresh());

    expect($ledger->escrowHeldMinor($engagement->id))->toBe(800_000); // only released once
});

it('refunds the remaining escrow to the customer', function () {
    $ledger = app(Ledger::class);
    ['customer' => $customer, 'engagement' => $engagement] = engageWithMilestones(1_000_000, 200_000);
    fundEscrow($customer, $engagement, 1_000_000);

    // Release the deposit, then refund the rest.
    app(ApproveMilestone::class)->handle($engagement->milestones()->where('position', 0)->firstOrFail());
    app(RefundEngagement::class)->handle($engagement, 'customer cancelled');

    expect($ledger->escrowHeldMinor($engagement->id))->toBe(0);
});

it('lets only the job customer approve a milestone (403)', function () {
    ['customer' => $customer, 'engagement' => $engagement] = engageWithMilestones(1_000_000, 200_000);
    fundEscrow($customer, $engagement, 1_000_000);
    $deposit = $engagement->milestones()->where('position', 0)->firstOrFail();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/milestones/{$deposit->id}/approve", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});
