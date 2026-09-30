<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\Gateways\PayoutRequest;
use App\Domain\Money\InsufficientPayable;
use App\Domain\Money\Ledger;
use App\Domain\Money\PaymentMethod;
use App\Domain\Money\PaymentStatus;
use App\Domain\Money\UnknownMobileRail;
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
 *
 * TWO STEPS, and the split is the point (CLAUDE.md "never call an external service from inside a
 * database transaction"):
 *
 *   1. RESERVE. A short transaction locks the payable account, checks the balance against
 *      already-reserved payouts, and writes the `pending` row. Then it COMMITS.
 *   2. DISBURSE. The gateway is called with no transaction open and no lock held, and the result is
 *      written back.
 *
 * It used to be one transaction with the gateway call inside it, which was wrong twice over. The
 * lock on the provider's payable account was held for the length of an HTTP round trip to CinetPay.
 * Worse, a timeout rolled the transaction back — destroying the `pending` row that is the ONLY
 * record of the reservation — while the transfer may already have been accepted. The provider could
 * then request the same money again: a double disbursement, with nothing in our database that had
 * ever seen the first one.
 *
 * Committing the reservation first means a failed or timed-out gateway call leaves the money
 * reserved and the payout visibly `pending` with no `external_ref` — which is exactly the state
 * {@see dispatchReserved()} picks up — whether a client retries under the same Idempotency-Key or
 * the `payouts:reconcile` sweep gets there first — so the same payout goes forward instead of a
 * second one being created. The gateway carries `$payout->id` as its own
 * client reference, so it dedupes the retry on its side too.
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
        ?PaymentMethod $method = null,
    ): Payout {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Payout amount must be positive.');
        }
        $method ??= PaymentMethod::fromMsisdn($msisdn) ?? throw new UnknownMobileRail($msisdn);
        if (! $method->isMobile()) {
            throw new InvalidArgumentException('A payout goes out on a mobile rail.');
        }

        $existing = Payout::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $this->dispatchReserved($existing);
        }

        $payable = $this->ledger->account(AccountKind::ProviderPayable, $provider->party_id, $currency);

        // Step 1 — reserve. Commits before anything touches the network.
        //
        // The unique-violation recovery is OUTSIDE the transaction on purpose. It used to be
        // inside, next to the failing INSERT, where it could never have worked: in Postgres a
        // failed statement aborts the whole transaction, so the SELECT that was meant to recover
        // the winner's row would itself fail with "current transaction is aborted". Out here the
        // transaction has already rolled back and the connection is clean — which is the same
        // reason ProcessPaymentWebhook wraps its dedup insert in a transaction of its own.
        try {
            $payout = DB::transaction(function () use ($provider, $payable, $amountMinor, $msisdn, $idempotencyKey, $currency, $method): Payout {
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

                return Payout::query()->create([
                    'party_id' => $provider->party_id,
                    'amount_minor' => $amountMinor,
                    'currency' => $currency,
                    'msisdn' => $msisdn,
                    'gateway' => $this->gateway->name(),
                    'method' => $method->value,
                    'status' => PaymentStatus::Pending->value,
                    'idempotency_key' => $idempotencyKey,
                ]);
            });
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'payouts_idempotency_key_unique')) {
                throw $e;
            }

            // A request with this key won the race while we were checking the balance.
            $payout = Payout::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }

        // Step 2 — disburse, outside the transaction.
        return $this->dispatchReserved($payout);
    }

    /**
     * Send a reserved-but-unsent payout to the gateway and record what came back.
     *
     * A no-op for anything already on its way or finished, which is what makes this safe to call on
     * both paths: the fresh reservation above, and a retry that found an existing row. "Unsent" is
     * `pending` with no `external_ref` — the state a reservation commits in, and the state a
     * gateway timeout leaves behind. {@see ResolvePayout} deliberately skips those rows (it has no
     * reference to ask about), so without this they would stay reserved for ever.
     *
     * Every value comes off the ROW, never off the request that happens to be resuming it, so a
     * retry cannot quietly disburse to a different number than the one that was reserved.
     */
    public function dispatchReserved(Payout $payout): Payout
    {
        if ($payout->status !== PaymentStatus::Pending || $payout->external_ref !== null) {
            return $payout;
        }

        $result = $this->gateway->requestPayout(new PayoutRequest(
            reference: $payout->id,
            amountMinor: $payout->amount_minor,
            currency: (string) $payout->currency,
            msisdn: (string) $payout->msisdn,
            description: 'handy-man payout',
            method: $this->methodOf($payout),
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
    }

    /**
     * The rail a reserved payout goes out on, read off the row.
     *
     * `payouts.method` is nullable — it arrived in a later migration that backfills from the
     * operator prefix and leaves anything it cannot recognise alone — so fall back to the same
     * inference the request path uses rather than letting `from()` throw on an older row.
     */
    private function methodOf(Payout $payout): PaymentMethod
    {
        $stored = $payout->method === null ? null : PaymentMethod::tryFrom((string) $payout->method);

        return $stored
            ?? PaymentMethod::fromMsisdn((string) $payout->msisdn)
            ?? throw new UnknownMobileRail((string) $payout->msisdn);
    }
}
