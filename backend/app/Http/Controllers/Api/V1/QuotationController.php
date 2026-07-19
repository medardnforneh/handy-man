<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\Actions\ReviseQuotation;
use App\Domain\Quotations\Actions\SubmitQuotation;
use App\Domain\Quotations\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitQuotationRequest;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Models\Job;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Quotations (build plan P2.5-01). A provider submits a priced quote for a job, and revises it as a
 * new version — never an in-place edit (doc 06 / rule #9).
 */
final class QuotationController extends Controller
{
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
}
