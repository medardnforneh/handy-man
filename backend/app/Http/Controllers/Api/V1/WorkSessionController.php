<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Execution\Actions\CheckIn;
use App\Domain\Execution\Actions\CheckOut;
use App\Domain\Execution\Actions\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecordStatusRequest;
use App\Http\Requests\Api\V1\WorkSessionRequest;
use App\Http\Resources\Api\V1\MessageResource;
use App\Http\Resources\Api\V1\WorkSessionResource;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The provider execution surface (build plan P5-03/P5-06, doc 06). The acting user must be an active
 * assigned worker on the engagement — the provider section only ever queries `assignments`, never
 * branching on individual-vs-company. Check-in/out capture geo; status signals narrate the timeline.
 */
final class WorkSessionController extends Controller
{
    public function checkIn(WorkSessionRequest $request, Engagement $engagement, CheckIn $action): JsonResponse
    {
        $assignment = $this->assignmentFor($engagement, $this->user($request));

        $session = $action->handle($assignment, $request->latitude(), $request->longitude(), $request->accuracyM());

        return WorkSessionResource::make($session)->response()->setStatusCode(201);
    }

    public function checkOut(WorkSessionRequest $request, Engagement $engagement, CheckOut $action): JsonResponse
    {
        $assignment = $this->assignmentFor($engagement, $this->user($request));

        $session = $action->handle($assignment, $request->latitude(), $request->longitude(), $request->accuracyM());

        return WorkSessionResource::make($session)->response();
    }

    public function status(RecordStatusRequest $request, Engagement $engagement, RecordStatus $action): JsonResponse
    {
        $assignment = $this->assignmentFor($engagement, $this->user($request));

        $message = $action->handle($assignment, $request->status());

        return MessageResource::make($message)->response()->setStatusCode(201);
    }

    /**
     * The acting user's active assignment on this engagement, or a 403. This is the provider-section
     * boundary: only a dispatched worker can act on the engagement's execution.
     */
    private function assignmentFor(Engagement $engagement, User $user): Assignment
    {
        $assignment = Assignment::query()
            ->where('engagement_id', $engagement->id)
            ->where('worker_user_id', $user->id)
            ->whereNull('removed_at')
            ->first();

        abort_if($assignment === null, 403, 'You are not assigned to this engagement.');

        return $assignment;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
