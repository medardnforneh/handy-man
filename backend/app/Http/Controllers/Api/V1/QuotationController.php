<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Domain\Quotations\Actions\ReviseQuotation;
use App\Domain\Quotations\Actions\SubmitQuotation;
use App\Domain\Quotations\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitQuotationRequest;
use App\Http\Resources\Api\V1\EngagementResource;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Models\Job;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quotations (build plan P2.5-01). A provider submits a priced quote for a job, and revises it as a
 * new version — never an in-place edit (doc 06 / rule #9).
 */
final class QuotationController extends Controller
{
    /**
     * The quotations on a job, as the viewer is entitled to see them (P2.5-01).
     *
     * This read did not exist, and its absence was the hole in the middle of the marketplace: a
     * provider could submit a priced quote and the customer had no way to see it, let alone accept
     * it. A quote arrives BEFORE any engagement, so there is no conversation to narrate it into
     * either — the job is the only place it can appear.
     *
     * Who sees what: the job's customer sees every quote that has actually been submitted (a draft
     * is the provider's private working copy and is never disclosed); a provider sees their own
     * quotes on this job and nobody else's, so the field cannot be read off the endpoint. Anyone
     * else gets a 403 rather than an empty list, because "no quotes" and "not your job" are
     * different answers.
     */
    public function index(Request $request, Job $job): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $isCustomer = $job->customer_party_id === $user->party_id;
        abort_unless($isCustomer || $this->hasQuoted($job, $user), 403);

        $quotations = Quotation::query()
            ->where('job_id', $job->id)
            ->when($isCustomer,
                fn ($q) => $q->where('status', '!=', QuoteStatus::Draft->value),
                fn ($q) => $q->where('provider_party_id', $user->party_id),
            )
            ->with(['lines', 'providerProfile.skills.skill'])
            ->orderByDesc('version')
            ->orderByDesc('created_at')
            ->get();

        return QuotationResource::collection($quotations)->response();
    }

    private function hasQuoted(Job $job, User $user): bool
    {
        return Quotation::query()
            ->where('job_id', $job->id)
            ->where('provider_party_id', $user->party_id)
            ->exists();
    }

    public function store(SubmitQuotationRequest $request, Job $job, SubmitQuotation $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            in_array($job->status, [JobStatus::Open, JobStatus::Offered], true),
            409,
            'This job is not accepting quotations.',
        );

        $quote = $action->handle($user, $job, $request->toDraft());

        return QuotationResource::make($quote->load('lines'))->response()->setStatusCode(201);
    }

    public function revise(SubmitQuotationRequest $request, Quotation $quotation, ReviseQuotation $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($quotation->provider_party_id === $user->party_id, 403);
        abort_unless($quotation->status === QuoteStatus::Submitted, 409, 'Only a submitted quotation can be revised.');

        $quote = $action->handle($user, $quotation, $request->toDraft());

        return QuotationResource::make($quote->load('lines'))->response()->setStatusCode(201);
    }

    /**
     * The job's customer accepts a submitted quotation → engagement + milestones (P2.5-05).
     */
    public function accept(Request $request, Quotation $quotation, AcceptQuotation $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $job = $quotation->job()->firstOrFail();
        abort_unless($job->customer_party_id === $user->party_id, 403);

        $engagement = $action->handle($user, $quotation);

        return EngagementResource::make($engagement->load(['assignments', 'milestones']))
            ->response()->setStatusCode(201);
    }
}
