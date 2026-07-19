<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Jobs\Actions\CreateJob;
use App\Domain\Jobs\Actions\PublishJob;
use App\Domain\Jobs\JobStatus;
use App\Domain\Jobs\ProviderSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateJobRequest;
use App\Http\Resources\Api\V1\JobResource;
use App\Http\Resources\Api\V1\ProviderProfileResource;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Jobs (build plan P2-03). Responses are PII-minimised by JobResource — a pre-engagement provider
 * never sees a customer's exact address (rule #6).
 */
final class JobController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $jobs = Job::query()
            ->where('customer_party_id', $user->party_id)
            ->with(['photos', 'address'])
            ->latest('created_at')
            ->get();

        return JobResource::collection($jobs);
    }

    public function store(CreateJobRequest $request, CreateJob $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $job = $action->handle($user, $request->validated());

        return JobResource::make($job->load(['photos', 'address']))->response()->setStatusCode(201);
    }

    public function show(Request $request, Job $job): JobResource
    {
        /** @var User $user */
        $user = $request->user();

        // A draft is invisible to anyone but its owner — don't even reveal it exists.
        if ($job->status === JobStatus::Draft && $job->customer_party_id !== $user->party_id) {
            throw new NotFoundHttpException;
        }

        return JobResource::make($job->load(['photos', 'address']));
    }

    public function publish(Request $request, Job $job, PublishJob $action): JobResource
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($job->customer_party_id === $user->party_id, 403);

        $action->handle($job);

        return JobResource::make($job->load(['photos', 'address']));
    }

    /** Matching providers for a job — skill + coverage (geo skipped for remote). Owner only. */
    public function providers(Request $request, Job $job, ProviderSearch $search): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($job->customer_party_id === $user->party_id, 403);

        return ProviderProfileResource::collection($search->forJob($job)->load('party'));
    }
}
