<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;

/**
 * Reconcile one payment intent against the gateway (build plan P3-06). Webhooks get lost, so we poll
 * `fetchStatus` for the truth; whichever of webhook/poll resolves the intent first wins and the other
 * is a no-op (the apply step is idempotent against an already-resolved intent). An intent still
 * pending past its expiry is force-expired — timeout is a state, not an error (doc 03).
 */
final class ReconcilePaymentIntent
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ApplyGatewayResult $apply,
    ) {}

    public function handle(PaymentIntent $intent): void
    {
        DB::transaction(function () use ($intent): void {
            $locked = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();

            if ($locked->isResolved()) {
                return;
            }

            // With no gateway reference we can't poll; only expiry can resolve it.
            if ($locked->external_ref !== null) {
                $status = $this->gateway->fetchStatus($locked->external_ref)->status;

                if ($status->isTerminal()) {
                    $this->apply->handle($locked, $status);

                    return;
                }
            }

            if ($locked->expires_at->isPast()) {
                $this->apply->handle($locked, GatewayStatus::Expired);
            }
        });
    }
}
