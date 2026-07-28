<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Provider\Actions\AddProviderSkill;
use App\Domain\Provider\Actions\CreateProviderProfile;
use App\Domain\Provider\Actions\SetServiceArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Provider\AddProviderSkillRequest;
use App\Http\Requests\Api\V1\Provider\SetServiceAreaRequest;
use App\Http\Requests\Api\V1\Provider\StoreProviderProfileRequest;
use App\Http\Resources\Api\V1\ProviderProfileResource;
use App\Http\Resources\Api\V1\ProviderSkillResource;
use App\Http\Resources\Api\V1\ServiceAreaResource;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The provider section (build plan P1-08, doc 10). Creating a profile is always allowed — you
 * become a provider by using this section. Listing a skill is fact-gated on having a profile;
 * setting a service area requires location_tracking consent.
 */
final class ProviderController extends Controller
{
    public function showProfile(Request $request): ProviderProfileResource
    {
        /** @var User $user */
        $user = $request->user();

        $profile = ProviderProfile::query()
            ->where('party_id', $user->party_id)
            // `party` for the display name and `skills.skill` for the bilingual labels — without
            // these the resource's whenLoaded fields silently vanish from the response.
            ->with(['party', 'skills.skill', 'serviceAreas'])
            ->first();

        if ($profile === null) {
            throw new NotFoundHttpException('No provider profile yet.');
        }

        return ProviderProfileResource::make($profile);
    }

    public function storeProfile(StoreProviderProfileRequest $request, CreateProviderProfile $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $profile = $action->handle($user, $request->only(['headline', 'bio', 'bio_language']));

        return ProviderProfileResource::make($profile)->response()->setStatusCode(200);
    }

    public function storeSkill(AddProviderSkillRequest $request, AddProviderSkill $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $providerSkill = $action->handle(
            user: $user,
            skillId: $request->string('skill_id')->toString(),
            priceModel: $request->string('price_model')->toString(),
            rateMinor: $request->has('rate_minor') ? (int) $request->integer('rate_minor') : null,
            yearsExperience: $request->has('years_experience') ? (int) $request->integer('years_experience') : null,
        );

        return ProviderSkillResource::make($providerSkill)->response()->setStatusCode(201);
    }

    public function storeServiceArea(SetServiceAreaRequest $request, SetServiceArea $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $area = $action->handle(
            user: $user,
            latitude: (float) $request->float('latitude'),
            longitude: (float) $request->float('longitude'),
            radiusMeters: (int) $request->integer('radius_m'),
        );

        return ServiceAreaResource::make($area)->response()->setStatusCode(201);
    }
}
