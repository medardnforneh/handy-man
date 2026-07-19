<?php

declare(strict_types=1);

use App\Domain\Money\Actions\InitiatePaymentIntent;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\PaymentPurpose;
use App\Domain\Money\PaymentStatus;
use App\Domain\Money\TxnKind;
use App\Models\LedgerTransaction;
use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * P3-06 acceptance (doc 03): never trust the webhook alone. A lost webhook still resolves via the
 * reconciliation poll, and an intent stuck past its expiry is force-expired (timeout is a state).
 */
it('resolves a lost-webhook payment via the reconciliation poll', function () {
    $user = User::factory()->create();
    $intent = app(InitiatePaymentIntent::class)->handle(
        $user, PaymentPurpose::LeadCredits, 1_000_000, '+237650000000', (string) Str::uuid(),
    );

    // The payment actually succeeded at the gateway, but NO webhook arrived.
    app(PaymentGateway::class)->settle($intent->external_ref, GatewayStatus::Succeeded);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect(PaymentIntent::findOrFail($intent->id)->status)->toBe(PaymentStatus::Succeeded)
        ->and(LedgerTransaction::where('kind', TxnKind::LeadCreditPurchase->value)->count())->toBe(1);
});

it('force-expires an intent stuck past its deadline', function () {
    // Processing, past expiry, and the gateway still reports pending (default fake status).
    $intent = PaymentIntent::factory()->create([
        'status' => PaymentStatus::Processing->value,
        'expires_at' => now()->subMinute(),
        'external_ref' => (string) Str::uuid(),
        'gateway' => 'fake',
    ]);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect(PaymentIntent::findOrFail($intent->id)->status)->toBe(PaymentStatus::Expired)
        ->and(LedgerTransaction::count())->toBe(0); // expiry posts nothing
});

it('leaves a still-pending, not-yet-expired intent alone', function () {
    $intent = PaymentIntent::factory()->create([
        'status' => PaymentStatus::Processing->value,
        'expires_at' => now()->addMinutes(10),
        'external_ref' => (string) Str::uuid(),
        'gateway' => 'fake',
    ]);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect(PaymentIntent::findOrFail($intent->id)->status)->toBe(PaymentStatus::Processing);
});
