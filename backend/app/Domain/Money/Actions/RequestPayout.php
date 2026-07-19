<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\Gateways\PayoutRequest;
use App\Domain\Money\InsufficientPayable;
use App\Domain\Money\Ledger;
use App\Domain\Money\PaymentStatus;
use App\Models\LedgerAccount;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A provider requests a payout (build plan P3-08, doc 03). Idempotent on `idempotency_key`. Funds are
 * reserved by the pending payout ROW, not a ledger entry — the ledger posting waits for gateway
 * confirmation (see {@see ResolvePayout}). The available balance therefore subtracts already-pending
 * payouts, and the provider's payable account row is locked so concurrent requests can't double-spend.
 */
final class RequestPayout
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly Ledger $ledger,
    ) {}

    public function handle(
        User $provider,
        int $amountMinor,
        string $msisdn,
        string $idempotencyKey,
        string $currency = 'XAF',
    ): Payout {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Payout amount must be positive.');
        }

        $existing = Payout::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $payable = $this->ledger->account(AccountKind::ProviderPayable, $provider->party_id, $currency);

        return DB::transaction(function () use ($provider, $payable, $amountMinor, $msisdn, $idempotencyKey, $currency): Payout {
            LedgerAccount::query()->whereKey($payable->id)->lockForUpdate()->firstOrFail();

            $balance = -$payable->balanceMinor(); // credit-normal → owed to the provider
            $reserved = (int) Payout::query()
                ->where('party_id', $provider->party_id)
                ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
                ->sum('amount_minor');

            $available = $balance - $reserved;
            if ($available < $amountMinor) {
                throw new InsufficientPayable($available, $amountMinor, $currency);
            }

            try {
                $payout = Payout::query()->create([
                    'party_id' => $provider->party_id,
                    'amount_minor' => $amountMinor,
                    'currency' => $currency,
                    'msisdn' => $msisdn,
                    'gateway' => $this->gateway->name(),
                    'status' => PaymentStatus::Pending->value,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'payouts_idempotency_key_unique')) {
                    return Payout::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
                }
                throw $e;
            }

            $result = $this->gateway->requestPayout(new PayoutRequest(
                reference: $payout->id,
                amountMinor: $amountMinor,
                currency: $currency,
                msisdn: $msisdn,
                description: 'handy-man payout',
            ));

            $failed = $result->status === GatewayStatus::Failed;
            $payout->update([
                'external_ref' => $result->externalRef,
                'status' => $failed ? PaymentStatus::Failed->value : PaymentStatus::Processing->value,
                'raw' => $result->raw,
                'failure_code' => $failed ? $result->failureCode : null,
                'resolved_at' => $failed ? now() : null,
            ]);

            return $payout->refresh();
        });
    }
}
