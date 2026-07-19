<?php

declare(strict_types=1);

use App\Domain\Money\AccountKind;
use App\Domain\Money\Actions\RequestPayout;
use App\Domain\Money\Actions\ReversePayout;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\InsufficientPayable;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\PaymentStatus;
use App\Domain\Money\TxnKind;
use App\Models\LedgerTransaction;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P3-08 acceptance (doc 03): payouts and failure reversal. The payout posts to the ledger only on
 * gateway confirmation; a confirmed-then-failed payout is reversed with a NEW balanced transaction
 * (never a delete) that restores provider_payable to its pre-payout value.
 */

/** Credit a provider's payable balance (as an escrow release would). */
function grantPayable(User $user, int $amount): void
{
    $ledger = app(Ledger::class);
    $ledger->post(TxnKind::Adjustment, [
        LedgerEntryInput::debit($ledger->account(AccountKind::PlatformCash), $amount),
        LedgerEntryInput::credit($ledger->account(AccountKind::ProviderPayable, $user->party_id), $amount),
    ]);
}

it('requests a payout that rests in processing without posting to the ledger yet', function () {
    $user = User::factory()->create();
    grantPayable($user, 1_000_000);

    Sanctum::actingAs($user);
    $this->postJson('/api/v1/provider/payouts',
        ['amount_minor' => 400_000, 'msisdn' => '+237650000000'],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated()->assertJsonPath('data.status', 'processing');

    // No payout posting until confirmation — payable is still fully owed.
    expect(app(Ledger::class)->availableMinor(AccountKind::ProviderPayable, $user->party_id))->toBe(1_000_000)
        ->and(LedgerTransaction::where('kind', TxnKind::Payout->value)->count())->toBe(0);
});

it('posts DR provider_payable / CR platform_cash once the gateway confirms', function () {
    $user = User::factory()->create();
    $ledger = app(Ledger::class);
    grantPayable($user, 1_000_000);

    $payout = app(RequestPayout::class)->handle($user, 1_000_000, '+237650000000', (string) Str::uuid());
    app(PaymentGateway::class)->settle($payout->external_ref, GatewayStatus::Succeeded);
    $this->artisan('payouts:reconcile')->assertSuccessful();

    $payout->refresh();
    expect($payout->status)->toBe(PaymentStatus::Succeeded)
        ->and($payout->ledger_transaction_id)->not->toBeNull()
        ->and($ledger->availableMinor(AccountKind::ProviderPayable, $user->party_id))->toBe(0);
});

it('rejects a payout larger than the available payable (422)', function () {
    $user = User::factory()->create();
    grantPayable($user, 1_000);

    expect(fn () => app(RequestPayout::class)->handle($user, 5_000, '+237650000000', (string) Str::uuid()))
        ->toThrow(InsufficientPayable::class);
});

it('reserves pending payouts so the balance can’t be double-spent', function () {
    $user = User::factory()->create();
    grantPayable($user, 1_000);

    app(RequestPayout::class)->handle($user, 1_000, '+237650000000', (string) Str::uuid());
    // The whole balance is now reserved by the pending payout.
    expect(fn () => app(RequestPayout::class)->handle($user, 1, '+237650000000', (string) Str::uuid()))
        ->toThrow(InsufficientPayable::class);
});

it('reverses a confirmed-then-failed payout, restoring provider_payable (never a delete)', function () {
    $user = User::factory()->create();
    $ledger = app(Ledger::class);
    grantPayable($user, 1_000_000);

    $payout = app(RequestPayout::class)->handle($user, 1_000_000, '+237650000000', (string) Str::uuid());
    app(PaymentGateway::class)->settle($payout->external_ref, GatewayStatus::Succeeded);
    $this->artisan('payouts:reconcile')->assertSuccessful();

    expect($ledger->availableMinor(AccountKind::ProviderPayable, $user->party_id))->toBe(0);

    // The disbursement bounced — reverse it.
    app(ReversePayout::class)->handle($payout->refresh(), 'gateway returned the funds');

    $payout->refresh();
    expect($ledger->availableMinor(AccountKind::ProviderPayable, $user->party_id))->toBe(1_000_000) // pre-payout value
        ->and($payout->reversed_at)->not->toBeNull()
        ->and($payout->ledger_transaction_id)->not->toBeNull()         // original still there
        ->and($payout->reversal_transaction_id)->not->toBeNull()       // plus the reversal
        ->and(LedgerTransaction::where('kind', TxnKind::Payout->value)->count())->toBe(1)
        ->and(LedgerTransaction::where('kind', TxnKind::PayoutReversal->value)->count())->toBe(1);
});
