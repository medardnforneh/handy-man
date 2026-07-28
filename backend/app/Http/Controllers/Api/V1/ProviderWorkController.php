<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Jobs\JobStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProviderWorkDetailResource;
use App\Http\Resources\Api\V1\ProviderWorkResource;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The provider's active-work list (build plan P5-03) — their engagements still in flight (not yet
 * completed or cancelled), newest first. Scoped to the caller as the engagement's provider (the
 * individual-provider case; the company dispatcher's per-worker view lands with the workforce UI).
 * Read-only and self-scoped, so no Action or Policy.
 */
final class ProviderWorkController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $engagements = Engagement::query()
            ->where('provider_party_id', $user->party_id)
            ->whereHas('job', fn ($q) => $q->whereNotIn('status', [
                JobStatus::Completed->value,
                JobStatus::Cancelled->value,
            ]))
            ->with(['job.customer'])
            ->orderByDesc('accepted_at')
            ->get();

        return ProviderWorkResource::collection($engagements);
    }

    /**
     * One engagement's execution view. Authorised by the SAME boundary the execution Actions use —
     * an active assignment on this engagement (WorkSessionController::assignmentFor) — so any
     * engagement this screen can read is one whose check-in/status/report the caller may actually
     * drive. Not the provider-party scope of the list: a company's dispatcher reads the job through
     * the admin surface, a worker through their assignment.
     */
    public function show(Request $request, Engagement $engagement): ProviderWorkDetailResource
    {
        /** @var User $user */
        $user = $request->user();

        $assignment = Assignment::query()
            ->where('engagement_id', $engagement->id)
            ->where('worker_user_id', $user->id)
            ->whereNull('removed_at')
            ->first();

        abort_if($assignment === null, 403, 'You are not assigned to this engagement.');

        $engagement->load(['job.customer', 'job.address']);

        return ProviderWorkDetailResource::make($engagement)->forAssignment($assignment);
    }
}
