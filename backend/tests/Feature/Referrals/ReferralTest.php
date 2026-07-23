<?php

declare(strict_types=1);

use App\Domain\Engagements\Actions\CompleteEngagement;
use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Domain\Referrals\ReferralService;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\Referral;
use App\Models\User;
use App\Support\OutboxRelay;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P8-01 acceptance (doc 04): referrals — codes, qualify-on-first-completed-paid-job, ledger-backed.
 * Self-referral and a duplicate referee are blocked.
 */
it('lets a referee claim a code, then qualifies + rewards on completion (ledger-backed)', function () {
    $referrer = User::factory()->create();
    $referee = User::factory()->create();
    $code = app(ReferralService::class)->codeFor($referrer->party_id);

    Sanctum::actingAs($referee);
    $this->postJson('/api/v1/referrals/claim', ['code' => $code], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    // The referee completes their first job → the referral qualifies and the reward is booked.
    $job = Job::factory()->create(['customer_party_id' => $referee->party_id, 'created_by_user_id' => $referee->id]);
    $engagement = Engagement::factory()->create(['job_id' => $job->id]);
    app(CompleteEngagement::class)->handle($engagement);
    app(OutboxRelay::class)->drain();

    $referral = Referral::query()->firstOrFail();
    expect($referral->status)->toBe('qualified')
        ->and($referral->reward_transaction_id)->not->toBeNull();

    // The reward is a real, balanced ledger transaction crediting the referrer's promo liability.
    $reward = (int) config('referrals.reward_minor');
    $debits = (int) LedgerEntry::query()->where('transaction_id', $referral->reward_transaction_id)->where('direction', 'debit')->sum('amount_minor');
    $credits = (int) LedgerEntry::query()->where('transaction_id', $referral->reward_transaction_id)->where('direction', 'credit')->sum('amount_minor');
    expect($debits)->toBe($credits)->toBe($reward)
        ->and(app(Ledger::class)->availableMinor(AccountKind::PromoLiability, $referrer->party_id))->toBe($reward);
});

it('blocks a self-referral', function () {
    $user = User::factory()->create();
    $code = app(ReferralService::class)->codeFor($user->party_id);

    Sanctum::actingAs($user);
    $this->postJson('/api/v1/referrals/claim', ['code' => $code], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('blocks a duplicate referral for the same referee', function () {
    $referrer = User::factory()->create();
    $referee = User::factory()->create();
    $code = app(ReferralService::class)->codeFor($referrer->party_id);
    app(ReferralService::class)->claim($referee->party_id, $code);

    Sanctum::actingAs($referee);
    $this->postJson('/api/v1/referrals/claim', ['code' => $code], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('rejects an unknown code', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/referrals/claim', ['code' => 'HM-NOPE00'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});
