<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Actions\ErasePartyData;
use App\Domain\Identity\Actions\ExportPersonalData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePreferencesRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User preferences (build plan P1-05b). UI language and comms language, independently settable.
 */
final class ProfileController extends Controller
{
    public function updatePreferences(UpdatePreferencesRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $user->fill($request->only(['locale', 'comms_locale']))->save();

        return UserResource::make($user->load('party'));
    }

    /** DSAR — the data subject's right of access (P1-10). */
    public function dataExport(Request $request, ExportPersonalData $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $action->handle($user)]);
    }

    /** Right to erasure — crypto-shred (P1-10). Irreversible. */
    public function erase(Request $request, ErasePartyData $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user);

        return response()->json(['message' => 'Your account and personal data have been erased.']);
    }
}
