<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Warranties\Actions\FileWarrantyClaim;
use App\Domain\Warranties\Actions\IssueWarranty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FileWarrantyClaimRequest;
use App\Http\Requests\Api\V1\IssueWarrantyRequest;
use App\Http\Resources\Api\V1\WarrantyClaimResource;
use App\Http\Resources\Api\V1\WarrantyResource;
use App\Models\Engagement;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Warranties + claims (build plan P6-11). The provider issues a warranty on their engagement; the
 * customer files a claim, which spawns a real remedy job.
 */
final class WarrantyController extends Controller
{
    public function issue(IssueWarrantyRequest $request, Engagement $engagement, IssueWarranty $action): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($engagement->provider_party_id === $user->party_id, 403, 'Only the provider can issue a warranty.');

        $warranty = $action->handle($engagement, (int) $request->integer('duration_days'), $request->input('terms'));

        return WarrantyResource::make($warranty)->response()->setStatusCode(201);
    }

    public function claim(FileWarrantyClaimRequest $request, Warranty $warranty, FileWarrantyClaim $action): JsonResponse
    {
        $user = $this->user($request);
        $customerPartyId = $warranty->engagement()->firstOrFail()->job()->firstOrFail()->customer_party_id;
        abort_unless($customerPartyId === $user->party_id, 403, 'Only the customer can file a claim.');

        // WarrantyNotClaimable (409) renders as problem+json automatically.
        $claim = $action->handle($warranty, $user->party_id, $request->string('description')->toString());

        return WarrantyClaimResource::make($claim)->response()->setStatusCode(201);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
