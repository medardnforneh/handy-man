<?php

declare(strict_types=1);

use App\Domain\Money\AccountKind;
use App\Domain\Money\Actions\RequestPayout;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * The provider Earnings read model (P3-07/08): payable available (net of reserved pending payouts),
 * the reserved amount, lead credits, and payout history. It must mirror what RequestPayout will allow.
 */

/** Credit a provider's payable balance (as an escrow release would). */
function grantEarningsPayable(User $user, int $amount): void
{
    $ledger = app(Ledger::class);
    $ledger->post(TxnKind::Adjustment, [
        LedgerEntryInput::debit($ledger->account(AccountKind::PlatformCash), $amount),
        LedgerEntryInput::credit($ledger->account(AccountKind::ProviderPayable, $user->party_id), $amount),
    ]);
}

it('reports payable available net of reserved pending payouts, plus history', function () {
    $user = User::factory()->create();
    grantEarningsPayable($user, 1_000_000);

    // Reserve 400k via a pending payout — available should drop by exactly that.
    app(RequestPayout::class)->handle($user, 400_000, '+237650000000', (string) Str::uuid());

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/provider/earnings')
        ->assertOk()
        ->assertJsonPath('data.payable_available.amount_minor', 600_000)
        ->assertJsonPath('data.payable_pending.amount_minor', 400_000)
        ->assertJsonPath('data.payable_available.currency', 'XAF')
        ->assertJsonCount(1, 'data.payouts')
        ->assertJsonPath('data.payouts.0.amount.amount_minor', 400_000);
});

it('reports the prepaid lead-credit balance', function () {
    $user = User::factory()->create();
    $ledger = app(Ledger::class);
    // Purchase lands as DR gateway_receivable / CR lead_credit_liability (P3-07).
    $ledger->post(TxnKind::LeadCreditPurchase, [
        LedgerEntryInput::debit($ledger->account(AccountKind::GatewayReceivable), 250_000),
        LedgerEntryInput::credit($ledger->account(AccountKind::LeadCreditLiability, $user->party_id), 250_000),
    ]);

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/provider/earnings')
        ->assertOk()
        ->assertJsonPath('data.lead_credits.amount_minor', 250_000);
});

it('returns zeroes and an empty history for a provider with no money yet', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/provider/earnings')
        ->assertOk()
        ->assertJsonPath('data.payable_available.amount_minor', 0)
        ->assertJsonPath('data.payable_pending.amount_minor', 0)
        ->assertJsonPath('data.lead_credits.amount_minor', 0)
        ->assertJsonCount(0, 'data.payouts');
});

it('requires authentication', function () {
    $this->getJson('/api/v1/provider/earnings')->assertUnauthorized();
});
