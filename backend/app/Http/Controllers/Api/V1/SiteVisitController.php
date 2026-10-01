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
use App\Support\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Site visits (build plan P2.5-04). A provider schedules a visit for a job and later completes it,
 * optionally linking the quotation it produced — which makes a chargeable visit's fee creditable on
 * quote acceptance (see AcceptQuotation).
 */
final class SiteVisitController extends Controller
{
    /**
     * The caller's own site visits, still open ones first (P2.5-04).
     *
     * This read did not exist, and its absence is what made `complete` unreachable rather than
     * merely unbuilt: a visit is never narrated into a thread and appears in no list, so its id
     * survived only inside the session that scheduled it. A provider who scheduled a visit on
     * Monday had, by Tuesday, no way to close it.
     *
     * Narrating it — the fix warranties got — is not available here. A site visit happens BEFORE
     * any engagement, and a conversation enrols both parties, so it would introduce the provider to
     * a customer who is not yet entitled to identify them (P2-03). A self-scoped read discloses
     * nothing new in either direction: the provider reads their own rows, and the embedded job
     * carries whatever `JobResource` already decides a pre-engagement provider may see — the coarse
     * quarter and city, never the exact address.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $limit = Cursor::limit($request->query('limit'), 50);

        // Scheduled before completed, then soonest first: this list is a to-do, and the visit that
        // still has to happen outranks the one that already did.
        //
        // That makes the sort key COMPOUND, so the cursor carries the bucket too — a keyset
        // comparison has to compare exactly what the ordering compares, or the boundary between the
        // last scheduled visit and the first completed one falls in the wrong place and a page is
        // silently skipped. Ascending, so paging forward means `>` (see Cursor::applyAfterBucketed).
        $bucket = "case when status = 'scheduled' then 0 else 1 end";

        $query = SiteVisit::query()
            ->where('provider_party_id', $user->party_id)
            ->with(['job.address', 'job.skill'])
            ->orderByRaw($bucket)
            ->orderBy('scheduled_for')
            ->orderBy('id');

        Cursor::applyAfterBucketed($query, $bucket, 'scheduled_for', $request->query('before'));

        $visits = $query->limit($limit + 1)->get();
        $hasMore = $visits->count() > $limit;
        $visits = $visits->take($limit);
        $last = $visits->last();

        return SiteVisitResource::collection($visits)->additional(['meta' => [
            'has_more' => $hasMore,
            'next_cursor' => $hasMore && $last !== null
                ? Cursor::encodeBucketed(
                    $last->status === SiteVisitStatus::Scheduled ? 0 : 1,
                    $last->scheduled_for->toIso8601String(),
                    $last->id,
                )
                : null,
        ]]);
    }

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
