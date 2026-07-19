<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Actions\ReviewDeliverable;
use App\Domain\Workspace\Actions\SubmitDeliverable;
use App\Domain\Workspace\DeliverableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReviewDeliverableRequest;
use App\Http\Requests\Api\V1\SubmitDeliverableRequest;
use App\Http\Resources\Api\V1\DeliverableResource;
use App\Models\Deliverable;
use App\Models\Engagement;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Deliverables (build plan P4-08). The engagement's provider submits; the job's customer reviews.
 */
final class DeliverableController extends Controller
{
    public function store(SubmitDeliverableRequest $request, Engagement $engagement, SubmitDeliverable $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($engagement->provider_party_id === $user->party_id, 403);

        if ($request->filled('milestone_id')) {
            $milestone = Milestone::query()->findOrFail($request->string('milestone_id')->toString());
            abort_unless($milestone->engagement_id === $engagement->id, 422, 'The milestone must belong to this engagement.');
        }

        $deliverable = $action->handle(
            provider: $user,
            engagement: $engagement,
            title: $request->string('title')->toString(),
            mediaUrl: $request->input('media_url'),
            milestoneId: $request->input('milestone_id'),
        );

        return DeliverableResource::make($deliverable)->response()->setStatusCode(201);
    }

    public function review(ReviewDeliverableRequest $request, Deliverable $deliverable, ReviewDeliverable $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $engagement = $deliverable->engagement()->with('job')->firstOrFail();
        abort_unless($engagement->job->customer_party_id === $user->party_id, 403);
        abort_unless($deliverable->status === DeliverableStatus::Submitted, 409, 'This deliverable has already been reviewed.');

        $reviewed = $action->handle($deliverable, $request->isAccept(), $request->input('reject_reason'));

        return DeliverableResource::make($reviewed)->response();
    }
}
