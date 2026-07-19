<?php

declare(strict_types=1);

namespace App\Domain\Money\Gateways;

use Illuminate\Http\Request;

/**
 * An in-memory gateway for local dev and tests. It behaves like a real aggregator's shape — a
 * collection starts `pending` and resolves later — but is driven deterministically via {@see settle()}
 * instead of a phone prompt, so the whole intent → webhook → reconciliation → escrow path can be
 * exercised without live credentials. Bound as a singleton so its state survives within a request.
 */
final class FakeGateway implements PaymentGateway
{
    /** @var array<string, GatewayStatus> reference → current status */
    private array $statuses = [];

    public function name(): string
    {
        return 'fake';
    }

    public function requestCollection(CollectionRequest $request): GatewayResult
    {
        $this->statuses[$request->reference] = GatewayStatus::Pending;

        return new GatewayResult(GatewayStatus::Pending, $request->reference, ['reference' => $request->reference]);
    }

    public function requestPayout(PayoutRequest $request): GatewayResult
    {
        $this->statuses[$request->reference] = GatewayStatus::Pending;

        return new GatewayResult(GatewayStatus::Pending, $request->reference, ['reference' => $request->reference]);
    }

    public function fetchStatus(string $externalRef): GatewayResult
    {
        $status = $this->statuses[$externalRef] ?? GatewayStatus::Pending;

        return new GatewayResult($status, $externalRef, ['reference' => $externalRef, 'status' => $status->value]);
    }

    public function verifyWebhook(Request $request): bool
    {
        return $request->header('X-Fake-Signature') === 'valid';
    }

    public function parseWebhook(Request $request): GatewayEvent
    {
        $ref = (string) $request->input('reference', '');
        $status = GatewayStatus::tryFrom((string) $request->input('status', 'pending')) ?? GatewayStatus::Pending;

        return new GatewayEvent($ref, 'fake.notification', $status, (array) $request->all());
    }

    /** Test/dev control: force the outcome a later fetchStatus/reconciliation will report. */
    public function settle(string $reference, GatewayStatus $status): void
    {
        $this->statuses[$reference] = $status;
    }
}
