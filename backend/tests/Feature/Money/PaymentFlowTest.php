<?php

declare(strict_types=1);

use App\Domain\Money\Actions\InitiatePaymentIntent;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\PaymentPurpose;
use App\Domain\Money\PaymentStatus;
use App\Domain\Money\TxnKind;
use App\Models\LedgerTransaction;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P3-04/05 acceptance (doc 03): a payment intent starts a MoMo collection and lives in the pending
 * state; the webhook verifies its signature, deduplicates by insert, and applies the authoritative
 * (fetched) status once — so N duplicate deliveries produce exactly one ledger transaction.
 */

/** Initiate a lead-credits intent for a fresh provider, returning [user, intent]. */
function initiatedIntent(int $amount = 1_000_000): array
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = test()->postJson('/api/v1/payment-intents',
        ['purpose' => 'lead_credits', 'amount_minor' => $amount, 'msisdn' => '+237650000000'],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated();

    return [$user, PaymentIntent::findOrFail($response->json('data.id'))];
}

/** POST a signed fake webhook for a reference. */
function fakeWebhook(string $ref, string $eventType = 'fake.notification')
{
    return test()->postJson('/api/v1/webhooks/payments/fake',
        ['reference' => $ref, 'status' => 'succeeded', 'event_type' => $eventType],
        ['X-Fake-Signature' => 'valid'],
    );
}

it('initiates a payment intent that rests in the processing state', function () {
    [, $intent] = initiatedIntent();

    expect($intent->status)->toBe(PaymentStatus::Processing)
        ->and($intent->external_ref)->toBe($intent->id)   // the fake echoes our reference
        ->and($intent->expires_at)->not->toBeNull();
});

it('is idempotent on the idempotency key (no second intent)', function () {
    $user = User::factory()->create();
    $action = app(InitiatePaymentIntent::class);
    $key = (string) Str::uuid();

    $a = $action->handle($user, PaymentPurpose::LeadCredits, 500_000, '+237650000000', $key);
    $b = $action->handle($user, PaymentPurpose::LeadCredits, 500_000, '+237650000000', $key);

    expect($a->id)->toBe($b->id)
        ->and(PaymentIntent::where('idempotency_key', $key)->count())->toBe(1);
});

it('resolves the intent and posts the ledger on a successful webhook', function () {
    [$user, $intent] = initiatedIntent(2_000_000);

    // The gateway confirms success (fetched, not taken from the callback body).
    app(PaymentGateway::class)->settle($intent->external_ref, GatewayStatus::Succeeded);

    fakeWebhook($intent->external_ref)->assertNoContent(200);

    $intent->refresh();
    expect($intent->status)->toBe(PaymentStatus::Succeeded)
        ->and($intent->ledger_transaction_id)->not->toBeNull();

    // Collection posting: DR gateway_receivable / CR lead_credit_liability(pro), for 2,000,000.
    $txn = LedgerTransaction::where('kind', TxnKind::LeadCreditPurchase->value)->firstOrFail();
    expect($txn->entries()->count())->toBe(2)
        ->and((int) $txn->entries()->where('direction', 'debit')->sum('amount_minor'))->toBe(2_000_000);
});

it('turns 10 duplicate webhooks into exactly one ledger transaction', function () {
    [, $intent] = initiatedIntent(1_500_000);
    app(PaymentGateway::class)->settle($intent->external_ref, GatewayStatus::Succeeded);

    foreach (range(1, 10) as $_) {
        fakeWebhook($intent->external_ref)->assertNoContent(200);
    }

    expect(PaymentEvent::where('external_ref', $intent->external_ref)->count())->toBe(1)
        ->and(LedgerTransaction::where('kind', TxnKind::LeadCreditPurchase->value)->count())->toBe(1)
        ->and(PaymentIntent::findOrFail($intent->id)->status)->toBe(PaymentStatus::Succeeded);
});

it('rejects an unsigned webhook (401) and applies nothing', function () {
    [, $intent] = initiatedIntent();
    app(PaymentGateway::class)->settle($intent->external_ref, GatewayStatus::Succeeded);

    test()->postJson('/api/v1/webhooks/payments/fake',
        ['reference' => $intent->external_ref, 'status' => 'succeeded'],
    )->assertNoContent(401);

    expect(PaymentIntent::findOrFail($intent->id)->status)->toBe(PaymentStatus::Processing)
        ->and(LedgerTransaction::where('kind', TxnKind::LeadCreditPurchase->value)->count())->toBe(0)
        ->and(PaymentEvent::where('signature_valid', false)->count())->toBe(1);
});

it('treats a late webhook after a terminal state as a no-op', function () {
    [, $intent] = initiatedIntent(800_000);
    app(PaymentGateway::class)->settle($intent->external_ref, GatewayStatus::Succeeded);

    // First (distinct) event resolves the intent.
    fakeWebhook($intent->external_ref, 'evt.1')->assertNoContent(200);
    // A later, different event for the same payment — passes dedupe but the intent is already terminal.
    fakeWebhook($intent->external_ref, 'evt.2')->assertNoContent(200);

    expect(LedgerTransaction::where('kind', TxnKind::LeadCreditPurchase->value)->count())->toBe(1);
});
