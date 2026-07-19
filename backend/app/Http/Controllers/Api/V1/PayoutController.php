<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Money\Actions\RequestPayout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestPayoutRequest;
use App\Http\Resources\Api\V1\PayoutResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Provider payouts (build plan P3-08). A provider withdraws their available payable balance to their
 * mobile-money wallet. Payout-method verification (doc 10 `has_payout_method`) lands in Phase 6.
 */
final class PayoutController extends Controller
{
    public function store(RequestPayoutRequest $request, RequestPayout $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payout = $action->handle(
            provider: $user,
            amountMinor: (int) $request->integer('amount_minor'),
            msisdn: $request->string('msisdn')->toString(),
            idempotencyKey: (string) $request->header('Idempotency-Key'),
        );

        return PayoutResource::make($payout)->response()->setStatusCode(201);
    }
}
