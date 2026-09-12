<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Models\Job;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Models\User;

/**
 * Launch checklist (doc 05): "Milestone sums proven to equal engagement totals under a fuzz test."
 *
 * The deferred trigger enforces SUM(milestones) = agreed_amount at commit; the unit tests prove
 * it for a handful of round numbers. This throws random money at the one code path that builds
 * a milestone plan — quote acceptance, with its deposit and site-visit-credit arithmetic — and
 * checks the invariants that the trigger cannot: that the plan is the RIGHT shape for the money
 * (deposit + balance, or one full payment), that no milestone is negative, that the credit never
 * exceeds the subtotal, and that the deposit collected is the deposit agreed less the credit.
 *
 * The seed is fixed so a failure is reproducible; change it locally to explore.
 */
const FUZZ_SEED = 20260912;
const FUZZ_ROUNDS = 60;

it('keeps SUM(milestones) = agreed amount for random subtotals, deposits and visit credits', function () {
    mt_srand(FUZZ_SEED);
    $action = app(AcceptQuotation::class);

    // Edge values are drawn deliberately, then the rest is random — a fuzz that never lands on
    // 0, 1 or a deposit equal to the subtotal has not tested the edges the code branches on.
    $edges = [1, 2, 999, 1_000, 1_000_000, 987_654_321];

    for ($round = 0; $round < FUZZ_ROUNDS; $round++) {
        $subtotal = $round < count($edges) ? $edges[$round] : mt_rand(1, 50_000_000);
        // Deposit: none, all, or in between. Not MORE than all: the first run of this fuzz tried it
        // and the database refused the row (`quotations_deposit_check`), so that branch of the plan
        // builder is unreachable by construction, which is the better proof.
        $deposit = match (mt_rand(0, 3)) {
            0 => 0,
            1 => $subtotal,
            default => mt_rand(0, $subtotal),
        };
        // Visit credit: none, some, or more than the whole job (capped at the subtotal).
        $credit = match (mt_rand(0, 3)) {
            0, 1 => 0,
            2 => mt_rand(1, max(1, intdiv($subtotal, 3))),
            default => $subtotal + mt_rand(0, 5_000),
        };

        $customer = User::factory()->create();
        $provider = User::factory()->create();
        $job = Job::factory()->remote()->status(JobStatus::Open)->create(['customer_party_id' => $customer->party_id]);
        $quote = Quotation::factory()->submitted()->create([
            'job_id' => $job->id,
            'provider_party_id' => $provider->party_id,
            'subtotal_minor' => $subtotal,
            'deposit_minor' => $deposit,
            'valid_until' => now()->addDays(3),
        ]);
        if ($credit > 0) {
            SiteVisit::factory()->chargeable($credit)->completed()->create([
                'job_id' => $job->id,
                'provider_party_id' => $provider->party_id,
                'resulting_quotation_id' => $quote->id,
            ]);
        }

        $engagement = $action->handle($customer, $quote)->fresh(['milestones']);
        $milestones = $engagement->milestones->sortBy('position')->values();

        $expectedCredit = min($credit, $subtotal);
        $expectedAgreed = $subtotal - $expectedCredit;
        $expectedDeposit = max(0, $deposit - $expectedCredit);
        $case = sprintf('round %d: subtotal=%d deposit=%d credit=%d (seed %d)', $round, $subtotal, $deposit, $credit, FUZZ_SEED);

        expect($engagement->visit_credit_minor)->toBe($expectedCredit, $case)
            ->and($engagement->agreed_amount_minor)->toBe($expectedAgreed, $case)
            ->and($milestones->sum('amount_minor'))->toBe($expectedAgreed, $case)
            ->and($milestones->min('amount_minor'))->toBeGreaterThanOrEqual(0, $case)
            ->and($milestones->pluck('position')->all())->toBe(range(0, $milestones->count() - 1), $case);

        if ($expectedDeposit > 0 && $expectedDeposit < $expectedAgreed) {
            expect($milestones)->toHaveCount(2, $case)
                ->and($milestones[0]->amount_minor)->toBe($expectedDeposit, $case)
                ->and($milestones[1]->amount_minor)->toBe($expectedAgreed - $expectedDeposit, $case);
        } else {
            expect($milestones)->toHaveCount(1, $case)
                ->and($milestones[0]->amount_minor)->toBe($expectedAgreed, $case);
        }
    }
});
