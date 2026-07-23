<?php

declare(strict_types=1);

use App\Domain\Disputes\Actions\AdjudicateDispute;
use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Models\ActivityLog;
use App\Models\Dispute;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P6-10 acceptance (doc 04): a party raises a dispute; a human admin adjudicates. Every adjudication
 * is attributable to a named admin, and any money effect is a balanced adjustment transaction — never
 * an edit of history.
 */

/**
 * @return array{customer: User, engagement: Engagement}
 */
function disputeEngagement(): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->create(['customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id]);
    $engagement = Engagement::factory()->create(['job_id' => $job->id]);

    return ['customer' => $customer, 'engagement' => $engagement];
}

it('lets a party raise a dispute and alerts staff', function () {
    ['customer' => $customer, 'engagement' => $engagement] = disputeEngagement();
    Sanctum::actingAs($customer);

    $this->postJson("/api/v1/engagements/{$engagement->id}/disputes", [
        'category' => 'quality', 'body' => 'Work was left unfinished.',
    ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.category', 'quality');

    expect(Dispute::query()->count())->toBe(1)
        ->and(OutboxMessage::query()->where('type', 'dispute.raised')->exists())->toBeTrue();
});

it('forbids a non-party from raising a dispute', function () {
    ['engagement' => $engagement] = disputeEngagement();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/engagements/{$engagement->id}/disputes", [
        'category' => 'quality', 'body' => 'Not mine.',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
});

it('adjudicates with a balanced adjustment attributed to the admin', function () {
    ['engagement' => $engagement] = disputeEngagement();
    $dispute = Dispute::factory()->create(['engagement_id' => $engagement->id]);
    $admin = User::factory()->create();
    $ledger = app(Ledger::class);

    $entries = [
        LedgerEntryInput::debit($ledger->account(AccountKind::EscrowLiability), 50_000),
        LedgerEntryInput::credit($ledger->account(AccountKind::PlatformCash), 50_000),
    ];

    $resolved = app(AdjudicateDispute::class)->handle($dispute, $admin, 'resolved', 'Partial refund agreed.', $entries, 'Dispute refund');

    expect($resolved->status)->toBe('resolved')
        ->and($resolved->resolution_transaction_id)->not->toBeNull()
        ->and($resolved->resolved_by_user_id)->toBe($admin->id);

    // The adjustment is a real balanced transaction, stamped with the admin.
    $txnId = $resolved->resolution_transaction_id;
    $debits = (int) LedgerEntry::query()->where('transaction_id', $txnId)->where('direction', 'debit')->sum('amount_minor');
    $credits = (int) LedgerEntry::query()->where('transaction_id', $txnId)->where('direction', 'credit')->sum('amount_minor');
    expect($debits)->toBe($credits)->toBe(50_000);

    $log = ActivityLog::query()->where('action', 'dispute.adjudicated')->firstOrFail();
    expect($log->actor_user_id)->toBe($admin->id);
});

it('adjudicates with no money movement (dismissed)', function () {
    $dispute = Dispute::factory()->create();
    $admin = User::factory()->create();

    $resolved = app(AdjudicateDispute::class)->handle($dispute, $admin, 'rejected', 'Unfounded.');

    expect($resolved->status)->toBe('rejected')
        ->and($resolved->resolution_transaction_id)->toBeNull()
        ->and($resolved->resolved_by_user_id)->toBe($admin->id);
});

it('rejects adjudicating an already-decided dispute', function () {
    $dispute = Dispute::factory()->create(['status' => 'resolved']);
    $admin = User::factory()->create();

    expect(fn () => app(AdjudicateDispute::class)->handle($dispute, $admin, 'resolved', 'again'))
        ->toThrow(RuntimeException::class);
});
