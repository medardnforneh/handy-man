<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Disputes\Actions\RaiseDispute;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RaiseDisputeRequest;
use App\Http\Resources\Api\V1\DisputeResource;
use App\Models\Dispute;
use App\Models\Engagement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Disputes (build plan P6-10). A party to an engagement raises a dispute; adjudication is an admin
 * action in the panel (never here). Money effects are balanced adjustment transactions attributed to
 * the admin.
 */
final class DisputeController extends Controller
{
    public function store(RaiseDisputeRequest $request, Engagement $engagement, RaiseDispute $action): JsonResponse
    {
        $user = $this->user($request);

        $customerPartyId = $engagement->job()->firstOrFail()->customer_party_id;
        $isParty = in_array($user->party_id, [$customerPartyId, $engagement->provider_party_id], true);
        abort_unless($isParty, 403, 'You are not a party to this engagement.');

        $dispute = $action->handle($engagement, $user->party_id, $request->string('category')->toString(), $request->string('body')->toString());

        return DisputeResource::make($dispute)->response()->setStatusCode(201);
    }

    public function index(Request $request): JsonResponse
    {
        $disputes = Dispute::query()
            ->where('raised_by_party_id', $this->user($request)->party_id)
            ->latest('created_at')
            ->get();

        return DisputeResource::collection($disputes)->response();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
