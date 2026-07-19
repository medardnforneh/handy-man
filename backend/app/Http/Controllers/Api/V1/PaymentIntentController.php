<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Money\Actions\InitiatePaymentIntent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InitiatePaymentIntentRequest;
use App\Http\Resources\Api\V1\PaymentIntentResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Payment intents (build plan P3-04). The caller starts a MoMo collection; the response lives in the
 * pending state until the payer answers the USSD prompt (resolved by webhook or poll).
 */
final class PaymentIntentController extends Controller
{
    public function store(InitiatePaymentIntentRequest $request, InitiatePaymentIntent $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Tie the intent's idempotency to the request's Idempotency-Key (required by middleware).
        $idempotencyKey = (string) $request->header('Idempotency-Key');

        $intent = $action->handle(
            user: $user,
            purpose: $request->purpose(),
            amountMinor: (int) $request->integer('amount_minor'),
            msisdn: $request->string('msisdn')->toString(),
            idempotencyKey: $idempotencyKey,
            engagementId: $request->input('engagement_id'),
        );

        return PaymentIntentResource::make($intent)->response()->setStatusCode(201);
    }
}
