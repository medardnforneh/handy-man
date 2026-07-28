<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Offers\OfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\JobResource;
use App\Http\Resources\Api\V1\OfferResource;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The provider's opportunity feed — their live incoming direct offers (build plan P2-05/06). Each is a
 * customer's offer the provider can accept to form an engagement (POST /offers/{offer}/accept). The
 * embedded job is PII-minimised by {@see JobResource}: a provider viewing a
 * pre-engagement job sees only the coarse area, never the street address or coordinates.
 *
 * Read-only and self-scoped (the caller's own offers), so no Action or Policy.
 */
final class ProviderOpportunityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $offers = JobOffer::query()
            ->where('provider_party_id', $user->party_id)
            ->where('status', OfferStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->with(['job.address'])
            ->latest()
            ->get();

        return OfferResource::collection($offers);
    }
}
