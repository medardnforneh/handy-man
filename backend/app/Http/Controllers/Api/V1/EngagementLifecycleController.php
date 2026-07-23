<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Engagements\Actions\CompleteEngagement;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EngagementResource;
use App\Models\Engagement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Engagement lifecycle transitions driven by a participant (build plan P7-02). Completion is what
 * opens the review window and schedules the review follow-ups.
 */
final class EngagementLifecycleController extends Controller
{
    public function complete(Request $request, Engagement $engagement, CompleteEngagement $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $customerPartyId = $engagement->job()->firstOrFail()->customer_party_id;
        $isParty = in_array($user->party_id, [$customerPartyId, $engagement->provider_party_id], true);
        abort_unless($isParty, 403, 'You are not a party to this engagement.');

        return EngagementResource::make($action->handle($engagement))->response();
    }
}
