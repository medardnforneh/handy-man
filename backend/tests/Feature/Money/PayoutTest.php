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

it('keeps the reservation when the gateway call never completes, and resumes it on retry', function () {
    // The reservation and the disbursement used to be one transaction with the HTTP call inside
    // it. A timeout rolled that back — destroying the pending row that is the ONLY record of the
    // reservation — while the transfer may already have been accepted, so the provider could ask
    // for the same money again with nothing in our database that had seen the first request.
    //
    // The reservation now commits first. This is the state a timed-out call leaves behind:
    // reserved, pending, no external_ref.
    $user = User::factory()->create();
    grantPayable($user, 1_000_000);
    $key = (string) Str::uuid();

    $stranded = Payout::query()->create([
        'party_id' => $user->party_id,
        'amount_minor' => 400_000,
        'currency' => 'XAF',
        'msisdn' => '+237650000000',
        'gateway' => app(PaymentGateway::class)->name(),
        'method' => 'mtn_momo',
        'status' => PaymentStatus::Pending->value,
        'idempotency_key' => $key,
    ]);

    // The funds are reserved by that row, so a second request cannot spend them twice.
    expect(fn () => app(RequestPayout::class)->handle($user, 700_000, '+237650000000', (string) Str::uuid()))
        ->toThrow(InsufficientPayable::class);

    // ResolvePayout cannot help — it has no reference to ask the gateway about.
    $this->artisan('payouts:reconcile')->assertSuccessful();
    expect($stranded->fresh()->external_ref)->toBeNull();

    // A retry under the SAME Idempotency-Key drives that payout forward instead of creating a
    // second one. The gateway carries the payout id as its own client reference, so it dedupes
    // the retry on its side too.
    $resumed = app(RequestPayout::class)->handle($user, 400_000, '+237650000000', $key);

    expect($resumed->id)->toBe($stranded->id)
        ->and($resumed->external_ref)->not->toBeNull()
        ->and($resumed->status)->toBe(PaymentStatus::Processing)
        ->and(Payout::count())->toBe(1);
});

it('does not re-send a payout that is already on its way', function () {
    $user = User::factory()->create();
    grantPayable($user, 1_000_000);
    $key = (string) Str::uuid();

    $first = app(RequestPayout::class)->handle($user, 300_000, '+237650000000', $key);
    $ref = $first->external_ref;

    // Same key again: the row is already `processing` with a reference, so this is a read.
    $again = app(RequestPayout::class)->handle($user, 300_000, '+237650000000', $key);

    expect($again->id)->toBe($first->id)
        ->and($again->external_ref)->toBe($ref)
        ->and(Payout::count())->toBe(1);
});

it('parallel payout requests → one payout (doc 05 testing floor, third named concurrency test)', function () {
    // The testing floor names three concurrency tests as non-negotiable. Two existed (parallel
    // offer accepts, duplicate webhooks); this one did not, and it is the one guarding money
    // leaving the platform. Sequential here for the same reason the offer test is: the guarantee is
    // the payable account's row lock plus the reserved-payout sum, and a truly parallel run
    // converges on the same answer through the same lock.
    $user = User::factory()->create();
    grantPayable($user, 100_000);

    $accepted = 0;
    $refused = 0;

    foreach (range(1, 10) as $_) {
        try {
            // Ten DISTINCT keys: this is ten separate requests for the whole balance, not one
            // request retried — idempotency must not be what saves us here.
            app(RequestPayout::class)->handle($user, 100_000, '+237650000000', (string) Str::uuid());
            $accepted++;
        } catch (InsufficientPayable) {
            $refused++;
        }
    }

    expect($accepted)->toBe(1)
        ->and($refused)->toBe(9)
        ->and(Payout::count())->toBe(1)
        // And the balance was never over-committed: one reservation for the whole amount.
        ->and((int) Payout::query()->sum('amount_minor'))->toBe(100_000);
});
