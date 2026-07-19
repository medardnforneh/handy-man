<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Jobs\JobStatus;
use App\Domain\Offers\Actions\AcceptOfferAction;
use App\Domain\Offers\Actions\CreateDirectOffer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOfferRequest;
use App\Http\Resources\Api\V1\EngagementResource;
use App\Http\Resources\Api\V1\OfferResource;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Direct offers (build plan P2-05). The customer offers their job to a chosen provider.
 */
final class OfferController extends Controller
{
    public function store(CreateOfferRequest $request, Job $job, CreateDirectOffer $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($job->customer_party_id === $user->party_id, 403);
        abort_unless(
            in_array($job->status, [JobStatus::Open, JobStatus::Offered], true),
            409,
            'This job is not open for offers.',
        );

        $providerPartyId = $request->string('provider_party_id')->toString();

        abort_if(
            JobOffer::query()->where('job_id', $job->id)->where('provider_party_id', $providerPartyId)->exists(),
            409,
            'An offer to this provider already exists for this job.',
        );

        $offer = $action->handle(
            job: $job,
            providerPartyId: $providerPartyId,
            amountMinor: $request->has('amount_minor') ? (int) $request->integer('amount_minor') : null,
            message: $request->input('message'),
        );

        return OfferResource::make($offer)->response()->setStatusCode(201);
    }

    /**
     * The provider accepts an offer made to them (P2-06). The Action owns the fact gate (P2-06b), the
     * concurrency-safe transition, and the engagement + auto-assignment. The route is only reachable by
     * the offer's own provider; a mismatched viewer is rejected as `offer-not-acceptable` inside the
     * Action, so we don't leak the offer's existence.
     */
    public function accept(Request $request, JobOffer $offer, AcceptOfferAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $engagement = $action->handle($user, $offer);

        return EngagementResource::make($engagement->load('assignments'))->response()->setStatusCode(201);
    }
}
