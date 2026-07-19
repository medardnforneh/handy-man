<?php

declare(strict_types=1);

use App\Domain\Money\AccountKind;
use App\Domain\Money\Actions\RequestPayout;
use App\Domain\Money\Actions\ResolvePayout;
use App\Domain\Money\Actions\ReversePayout;
use App\Domain\Money\Actions\SpendLeadCredits;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\InsufficientLeadCredits;
use App\Domain\Money\InsufficientPayable;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * P3-12 acceptance (doc 03): the global money invariant. For ANY sequence of flows, the sum of all
 * debits equals the sum of all credits — the ledger never leaks or invents a franc. We drive a long
 * randomized mix of real flows (credit purchase/spend, payable grants, payouts + reversals) and
 * assert the invariant holds after every single step.
 */
it('keeps global debits == credits across a randomized sequence of money flows', function () {
    mt_srand(20260719); // deterministic
    $ledger = app(Ledger::class);
    $gateway = app(PaymentGateway::class);
    $providers = User::factory()->count(3)->create();

    /** @var list<string> $succeededPayoutIds */
    $succeededPayoutIds = [];

    $assertGloballyBalanced = function () {
        $debits = (int) LedgerEntry::query()->where('direction', 'debit')->sum('amount_minor');
        $credits = (int) LedgerEntry::query()->where('direction', 'credit')->sum('amount_minor');

        expect($debits)->toBe($credits);
    };

    for ($step = 0; $step < 60; $step++) {
        /** @var User $provider */
        $provider = $providers->random();
        $amount = mt_rand(1, 50) * 100; // XAF, multiples of 100
        $op = mt_rand(1, 5);

        try {
            switch ($op) {
                case 1: // purchase lead credits (collection posting)
                    $ledger->post(TxnKind::LeadCreditPurchase, [
                        LedgerEntryInput::debit($ledger->account(AccountKind::GatewayReceivable), $amount),
                        LedgerEntryInput::credit($ledger->account(AccountKind::LeadCreditLiability, $provider->party_id), $amount),
                    ]);
                    break;

                case 2: // spend lead credits (may be insufficient — that's fine)
                    app(SpendLeadCredits::class)->handle($provider, $amount, 'bid');
                    break;

                case 3: // an escrow release would credit provider_payable; simulate a payable grant
                    $ledger->post(TxnKind::Adjustment, [
                        LedgerEntryInput::debit($ledger->account(AccountKind::PlatformCash), $amount),
                        LedgerEntryInput::credit($ledger->account(AccountKind::ProviderPayable, $provider->party_id), $amount),
                    ]);
                    break;

                case 4: // request + confirm a payout
                    $payout = app(RequestPayout::class)->handle($provider, $amount, '+237650000000', (string) Str::uuid());
                    $gateway->settle($payout->external_ref, GatewayStatus::Succeeded);
                    app(ResolvePayout::class)->handle($payout->fresh());
                    if ($payout->fresh()->status->value === 'succeeded') {
                        $succeededPayoutIds[] = $payout->id;
                    }
                    break;

                case 5: // reverse a previously-succeeded payout
                    if ($succeededPayoutIds !== []) {
                        $id = array_shift($succeededPayoutIds);
                        app(ReversePayout::class)->handle(Payout::findOrFail($id), 'bounced');
                    }
                    break;
            }
        } catch (InsufficientLeadCredits|InsufficientPayable) {
            // Legitimate refusals — the invariant must still hold.
        }

        $assertGloballyBalanced();
    }

    // And something actually happened.
    expect(LedgerEntry::query()->count())->toBeGreaterThan(0);
});
