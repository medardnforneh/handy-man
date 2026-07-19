<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Jobs\JobStatus;
use App\Domain\Offers\Actions\CreateDirectOffer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOfferRequest;
use App\Http\Resources\Api\V1\OfferResource;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Http\JsonResponse;

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
}
