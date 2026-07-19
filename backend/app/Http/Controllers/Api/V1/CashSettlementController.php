<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Money\Actions\RecordCashSettlement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecordCashSettlementRequest;
use App\Http\Resources\Api\V1\CashSettlementResource;
use App\Models\Engagement;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Cash settlement recording (build plan P3-15). The provider on an engagement records a cash-settled
 * amount; the platform books its commission. Self-reporting is strictly better for the provider — it
 * builds their on-platform history — so we make it a first-class action.
 */
final class CashSettlementController extends Controller
{
    public function store(RecordCashSettlementRequest $request, Engagement $engagement, RecordCashSettlement $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($engagement->provider_party_id === $user->party_id, 403);

        $milestone = null;
        if ($request->filled('milestone_id')) {
            $milestone = Milestone::query()->findOrFail($request->string('milestone_id')->toString());
            abort_unless($milestone->engagement_id === $engagement->id, 422, 'The milestone must belong to this engagement.');
        }

        $settlement = $action->handle($user, $engagement, (int) $request->integer('amount_minor'), $milestone);

        return CashSettlementResource::make($settlement)->response()->setStatusCode(201);
    }
}
