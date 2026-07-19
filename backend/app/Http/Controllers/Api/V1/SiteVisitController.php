<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\Actions\CompleteSiteVisit;
use App\Domain\Quotations\Actions\ScheduleSiteVisit;
use App\Domain\Quotations\SiteVisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteSiteVisitRequest;
use App\Http\Requests\Api\V1\ScheduleSiteVisitRequest;
use App\Http\Resources\Api\V1\SiteVisitResource;
use App\Models\Job;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Site visits (build plan P2.5-04). A provider schedules a visit for a job and later completes it,
 * optionally linking the quotation it produced — which makes a chargeable visit's fee creditable on
 * quote acceptance (see AcceptQuotation).
 */
final class SiteVisitController extends Controller
{
    public function store(ScheduleSiteVisitRequest $request, Job $job, ScheduleSiteVisit $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            in_array($job->status, [JobStatus::Open, JobStatus::Offered], true),
            409,
            'This job is not accepting site visits.',
        );

        $visit = $action->handle($user, $job, $request->scheduledFor(), $request->isChargeable(), $request->feeMinor());

        return SiteVisitResource::make($visit)->response()->setStatusCode(201);
    }

    public function complete(CompleteSiteVisitRequest $request, SiteVisit $siteVisit, CompleteSiteVisit $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($siteVisit->provider_party_id === $user->party_id, 403);
        abort_unless($siteVisit->status === SiteVisitStatus::Scheduled, 409, 'This site visit is not open.');

        $resultingQuote = null;
        if ($request->filled('resulting_quotation_id')) {
            $resultingQuote = Quotation::query()->findOrFail($request->string('resulting_quotation_id')->toString());
            // The linked quote must be this provider's, on this job.
            abort_unless(
                $resultingQuote->job_id === $siteVisit->job_id
                    && $resultingQuote->provider_party_id === $siteVisit->provider_party_id,
                422,
                'The resulting quotation must belong to this visit’s job and provider.',
            );
        }

        $visit = $action->handle($siteVisit, $request->input('outcome_notes'), $resultingQuote);

        return SiteVisitResource::make($visit)->response()->setStatusCode(200);
    }
}
