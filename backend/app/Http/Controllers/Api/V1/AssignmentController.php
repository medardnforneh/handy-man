<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Engagements\Actions\AssignWorker;
use App\Domain\Engagements\Actions\UnassignWorker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignWorkerRequest;
use App\Http\Resources\Api\V1\AssignmentResource;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dispatcher-driven staffing of an engagement (build plan P2-08). Authorization is the
 * EngagementPolicy — org-internal RBAC (a dispatcher/owner of the provider org, or the individual
 * provider themselves), never the fact-gated access model.
 */
final class AssignmentController extends Controller
{
    public function store(AssignWorkerRequest $request, Engagement $engagement, AssignWorker $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->authorize('assignWorker', $engagement);

        $worker = User::query()->findOrFail($request->string('worker_user_id')->toString());

        $assignment = $action->handle($actor, $engagement, $worker, $request->role());

        return AssignmentResource::make($assignment)->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Engagement $engagement, Assignment $assignment, UnassignWorker $action): JsonResponse
    {
        $this->authorize('removeAssignment', $engagement);

        abort_unless($assignment->engagement_id === $engagement->id, 404);

        $action->handle($assignment);

        return response()->json(['data' => ['status' => 'removed']]);
    }
}
