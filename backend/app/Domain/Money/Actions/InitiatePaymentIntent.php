<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\Gateways\CollectionRequest;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\PaymentPurpose;
use App\Domain\Money\PaymentStatus;
use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Start a MoMo collection (build plan P3-04). Idempotent on `idempotency_key`: re-initiating with the
 * same key returns the existing intent, never a second charge (the unique index is the backstop under
 * a race). The intent's id is the gateway reference, so the webhook can find it by `external_ref`.
 * `pending`/`processing` is the normal resting state — the payer still has to answer the USSD prompt.
 */
final class InitiatePaymentIntent
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function handle(
        User $user,
        PaymentPurpose $purpose,
        int $amountMinor,
        string $msisdn,
        string $idempotencyKey,
        ?string $engagementId = null,
        string $currency = 'XAF',
    ): PaymentIntent {
        $existing = PaymentIntent::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            $intent = PaymentIntent::query()->create([
                'party_id' => $user->party_id,
                'engagement_id' => $engagementId,
                'purpose' => $purpose->value,
                'gateway' => $this->gateway->name(),
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'msisdn' => $msisdn,
                'status' => PaymentStatus::Pending->value,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addMinutes(15),
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'payment_intents_idempotency_key_unique')) {
                return PaymentIntent::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
            }
            throw $e;
        }

        $result = $this->gateway->requestCollection(new CollectionRequest(
            reference: $intent->id,
            amountMinor: $amountMinor,
            currency: $currency,
            msisdn: $msisdn,
            description: "handy-man {$purpose->value}",
        ));

        return DB::transaction(function () use ($intent, $result): PaymentIntent {
            $failed = $result->status === GatewayStatus::Failed;

            $intent->update([
                'external_ref' => $result->externalRef,
                'status' => $failed ? PaymentStatus::Failed->value : PaymentStatus::Processing->value,
                'raw' => array_merge($result->raw, ['payment_url' => $result->paymentUrl]),
                'failure_code' => $failed ? $result->failureCode : null,
                'resolved_at' => $failed ? now() : null,
            ]);

            return $intent->refresh();
        });
    }
}
