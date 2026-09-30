<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\Gateways\PaymentGateway;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gateway webhook handler (build plan P3-05, doc 03). The shape that makes replays and lost
 * webhooks harmless:
 *
 *   1. Verify the signature BEFORE trusting anything. Unsigned → record + 401.
 *   2. Deduplicate by INSERT into `payment_events` (unique on gateway+ref+type). A conflict means
 *      we've already seen this event → 200 and stop. This is what turns N duplicate deliveries into
 *      one applied result.
 *   3. Read the authoritative status via fetchStatus (never trust the callback body) with NOTHING
 *      locked, then apply it inside a transaction that LOCKS the intent and re-checks it —
 *      idempotently, so a poll that already won makes this a no-op.
 *
 * Always returns 200 for events already handled; a non-200 makes the gateway retry forever.
 *
 * The `{gateway}` path segment is validated against the configured adapter before any of this. It
 * arrives from the URL, and it is half of the `payment_events` dedup key — so an arbitrary value
 * there would split that key and let the same signed callback be recorded once per spelling.
 */
final class ProcessPaymentWebhook
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ApplyGatewayResult $apply,
    ) {}

    public function handle(Request $request, string $gateway): int
    {
        // The signature is checked against the CONFIGURED adapter, so a callback addressed to any
        // other name was never going to be applied — but it was still recorded under whatever
        // string the URL carried, and `gateway` is half of the (gateway, external_ref, event_type)
        // dedup key. `/webhooks/payments/cinetpay` and `/webhooks/payments/cinetpay-x` were two
        // different keys for one callback. Refuse the mismatch instead of storing it.
        if ($gateway !== $this->gateway->name()) {
            return Response::HTTP_NOT_FOUND;
        }

        if (! $this->gateway->verifyWebhook($request)) {
            PaymentEvent::query()->create([
                'gateway' => $gateway,
                'external_ref' => 'unverified-'.Str::uuid()->toString(),
                'event_type' => 'invalid_signature',
                'signature_valid' => false,
                'payload' => (array) $request->all(),
            ]);

            return Response::HTTP_UNAUTHORIZED;
        }

        $event = $this->gateway->parseWebhook($request);

        // Deduplicate by insert. Wrapped in its own transaction so a unique-violation rolls back to a
        // savepoint (and doesn't abort a surrounding transaction) before we discard the duplicate.
        try {
            $row = DB::transaction(fn (): PaymentEvent => PaymentEvent::query()->create([
                'gateway' => $gateway,
                'external_ref' => $event->externalRef,
                'event_type' => $event->type,
                'signature_valid' => true,
                'payload' => $event->raw,
            ]));
        } catch (UniqueConstraintViolationException) {
            return Response::HTTP_OK; // already seen — success, not error
        }

        // The callback only says "something changed" — read the authoritative status. This is an
        // outbound HTTP call, so it happens with no transaction open and no row locked: it used to
        // sit inside the transaction below, holding a lock on the payment intent for the length of
        // a round trip to the gateway (CLAUDE.md "never call an external service from inside a
        // database transaction"). Reading it early is safe because nothing is decided here — the
        // apply step re-checks under the lock, and a status that moves on in between is picked up
        // by the next callback or by `payments:reconcile`.
        $unresolved = PaymentIntent::query()
            ->where('gateway', $gateway)
            ->where('external_ref', $event->externalRef)
            ->first();

        $status = $unresolved !== null && ! $unresolved->isResolved()
            ? $this->gateway->fetchStatus($event->externalRef)->status
            : null;

        DB::transaction(function () use ($gateway, $event, $row, $status): void {
            if ($status !== null) {
                $intent = PaymentIntent::query()
                    ->where('gateway', $gateway)
                    ->where('external_ref', $event->externalRef)
                    ->lockForUpdate()
                    ->first();

                // Re-checked under the lock: a poll or a racing callback may have resolved it
                // while we were reading the status. `ApplyGatewayResult` is idempotent on top of
                // that, so exactly one ledger transaction is posted either way.
                if ($intent !== null && ! $intent->isResolved()) {
                    $this->apply->handle($intent, $status);
                }
            }

            $row->update(['processed_at' => now()]);
        });

        return Response::HTTP_OK;
    }
}
