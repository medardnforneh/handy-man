<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePreferencesRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;

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
}
