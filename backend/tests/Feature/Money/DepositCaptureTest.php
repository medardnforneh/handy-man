<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Money\Actions\CaptureDepositOnAgreement;
use App\Domain\Money\PaymentPurpose;
use App\Domain\Money\PaymentStatus;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\PaymentIntent;
use App\Models\Quotation;
use App\Models\User;
use App\Support\OutboxRelay;

/**
 * P3-13 — agreement-time deposit capture. When a quote acceptance forms an engagement with a
 * milestone plan, the deposit (the position-0 milestone) is collected into escrow automatically as
 * the `engagement.created` outbox message is relayed — not left to manual funding. It is idempotent,
 * and offer-path engagements (no milestones) capture nothing.
 */

/**
 * @return array{customer: User, engagement: Engagement}
 */
function acceptQuoteWithDeposit(int $subtotal, int $deposit): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create(['customer_party_id' => $customer->party_id]);
    $provider = User::factory()->create();
    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id,
        'subtotal_minor' => $subtotal, 'deposit_minor' => $deposit, 'valid_until' => now()->addDays(3),
    ]);
    $engagement = app(AcceptQuotation::class)->handle($customer, $quote);

    return ['customer' => $customer, 'engagement' => $engagement];
}

it('captures the deposit into escrow when the engagement is relayed', function () {
    ['engagement' => $engagement] = acceptQuoteWithDeposit(1_000_000, 200_000);

    // Acceptance itself moves no money — capture rides the committed outbox message.
    expect(PaymentIntent::query()->count())->toBe(0);

    app(OutboxRelay::class)->drain();

    $intent = PaymentIntent::query()->where('engagement_id', $engagement->id)->sole();
    expect($intent->purpose)->toBe(PaymentPurpose::Escrow)
        ->and($intent->amount_minor)->toBe(200_000)
        ->and($intent->status)->toBe(PaymentStatus::Processing);
});

it('is idempotent — relaying twice captures the deposit only once', function () {
    ['engagement' => $engagement] = acceptQuoteWithDeposit(1_000_000, 200_000);

    app(OutboxRelay::class)->drain();
    // Re-publishing the same at-least-once event must not charge again.
    app(CaptureDepositOnAgreement::class)->handle($engagement->fresh());

    expect(PaymentIntent::query()->where('engagement_id', $engagement->id)->count())->toBe(1);
});

it('captures nothing for an offer-path engagement (no milestones)', function () {
    $engagement = Engagement::factory()->create(); // offer path — carries no milestones

    expect(app(CaptureDepositOnAgreement::class)->handle($engagement))->toBeNull()
        ->and(PaymentIntent::query()->count())->toBe(0);
});
