<?php

declare(strict_types=1);

use App\Domain\Money\AccountKind;
use App\Domain\Money\Actions\InitiatePaymentIntent;
use App\Domain\Money\Actions\ReconcilePaymentIntent;
use App\Domain\Money\Actions\SpendLeadCredits;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\InsufficientLeadCredits;
use App\Domain\Money\Ledger;
use App\Domain\Money\PaymentPurpose;
use App\Domain\Money\TxnKind;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P3-07 acceptance (doc 03): lead-credit purchase and spend. Purchase arrives via a payment intent
 * (collection); spend shrinks the liability and books revenue, and can never overspend below zero.
 */

/** Grant a provider lead credits by running a successful collection end to end. */
function grantCredits(User $user, int $amount): void
{
    $intent = app(InitiatePaymentIntent::class)->handle(
        $user, PaymentPurpose::LeadCredits, $amount, '+237650000000', (string) Str::uuid(),
    );
    app(PaymentGateway::class)->settle($intent->external_ref, GatewayStatus::Succeeded);
    app(ReconcilePaymentIntent::class)->handle($intent->fresh());
}

it('reports a provider’s purchased lead-credit balance', function () {
    $user = User::factory()->create();
    grantCredits($user, 1_000_000);

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/provider/credits')
        ->assertOk()
        ->assertJsonPath('data.available.amount_minor', 1_000_000)
        ->assertJsonPath('data.available.currency', 'XAF');
});

it('spends lead credits — shrinking the liability and booking revenue', function () {
    $user = User::factory()->create();
    $ledger = app(Ledger::class);
    grantCredits($user, 1_000_000);

    $txn = app(SpendLeadCredits::class)->handle($user, 500, 'bid on job');

    expect($txn->kind)->toBe(TxnKind::LeadCreditSpend)
        ->and($ledger->availableMinor(AccountKind::LeadCreditLiability, $user->party_id))->toBe(999_500)
        ->and($ledger->availableMinor(AccountKind::PlatformRevenue))->toBe(500);
});

it('rejects spending more than the available balance (422 with the shortfall)', function () {
    $user = User::factory()->create();
    grantCredits($user, 1_000);

    expect(fn () => app(SpendLeadCredits::class)->handle($user, 2_000, 'too much'))
        ->toThrow(InsufficientLeadCredits::class);
});

it('never lets the balance go negative across sequential spends', function () {
    $user = User::factory()->create();
    $ledger = app(Ledger::class);
    grantCredits($user, 1_000);

    app(SpendLeadCredits::class)->handle($user, 1_000, 'spend it all');
    expect($ledger->availableMinor(AccountKind::LeadCreditLiability, $user->party_id))->toBe(0);

    expect(fn () => app(SpendLeadCredits::class)->handle($user, 1, 'one too many'))
        ->toThrow(InsufficientLeadCredits::class);
});
