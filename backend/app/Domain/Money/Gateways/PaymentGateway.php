<?php

declare(strict_types=1);

namespace App\Domain\Money\Gateways;

use Illuminate\Http\Request;

/**
 * The payment-gateway abstraction (doc 03). One implementation per provider under this namespace,
 * selected by config; the rest of the app never names a provider. "Build the abstraction anyway —
 * you WILL switch providers."
 */
interface PaymentGateway
{
    /** A stable machine name for this gateway, e.g. 'cinetpay' — stored on intents/payouts. */
    public function name(): string;

    /** Push a USSD/redirect collection to the payer. */
    public function requestCollection(CollectionRequest $request): GatewayResult;

    /** Disburse to a payee's wallet. */
    public function requestPayout(PayoutRequest $request): GatewayResult;

    /** Poll the authoritative status for a reference (reconciliation). */
    public function fetchStatus(string $externalRef): GatewayResult;

    /** Verify a webhook's signature BEFORE trusting any of its contents. */
    public function verifyWebhook(Request $request): bool;

    /** Extract the affected payment's identity from a (verified) webhook. */
    public function parseWebhook(Request $request): GatewayEvent;
}
