<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Money\Actions\ApproveMilestone;
use App\Domain\Money\Actions\RefundEngagement;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EngagementResource;
use App\Models\Engagement;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Escrow release and refund (build plan P3-10/14). The job's customer approves a milestone (releasing
 * its slice from escrow to the provider, net of commission) or refunds the remaining escrow. Money
 * movement is enforced in the domain Actions; the controller only authorizes.
 */
final class EscrowController extends Controller
{
    public function approveMilestone(Request $request, Milestone $milestone, ApproveMilestone $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $engagement = $milestone->engagement()->with('job')->firstOrFail();
        abort_unless($engagement->job->customer_party_id === $user->party_id, 403);

        $action->handle($milestone);

        return EngagementResource::make($engagement->load('milestones'))->response();
    }

    public function refund(Request $request, Engagement $engagement, RefundEngagement $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $job = $engagement->job()->firstOrFail();
        abort_unless($job->customer_party_id === $user->party_id, 403);

        $action->handle($engagement, (string) $request->input('reason', 'refund'));

        return response()->json(['data' => ['status' => 'refunded']]);
    }
}
