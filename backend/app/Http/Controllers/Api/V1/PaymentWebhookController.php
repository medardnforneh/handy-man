<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Money\Actions\ProcessPaymentWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Gateway webhook endpoint (build plan P3-05). Public (the gateway is server-to-server and signs its
 * callback); authenticity is the signature, verified inside the action. Returns an empty body with
 * the status the action decides — 200 for handled/duplicate, 401 for an invalid signature.
 */
final class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $gateway, ProcessPaymentWebhook $action): Response
    {
        $status = $action->handle($request, $gateway);

        return response()->noContent($status);
    }
}
