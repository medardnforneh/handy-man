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
 *   3. Apply inside a transaction that LOCKS the intent. Re-check the authoritative status via
 *      fetchStatus (never trust the callback body), then resolve the intent — idempotently, so a
 *      poll that already won makes this a no-op.
 *
 * Always returns 200 for events already handled; a non-200 makes the gateway retry forever.
 */
final class ProcessPaymentWebhook
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ApplyGatewayResult $apply,
    ) {}

    public function handle(Request $request, string $gateway): int
    {
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

        DB::transaction(function () use ($gateway, $event, $row): void {
            $intent = PaymentIntent::query()
                ->where('gateway', $gateway)
                ->where('external_ref', $event->externalRef)
                ->lockForUpdate()
                ->first();

            if ($intent !== null && ! $intent->isResolved()) {
                // The callback only says "something changed" — read the authoritative status.
                $status = $this->gateway->fetchStatus($event->externalRef)->status;
                $this->apply->handle($intent, $status);
            }

            $row->update(['processed_at' => now()]);
        });

        return Response::HTTP_OK;
    }
}
