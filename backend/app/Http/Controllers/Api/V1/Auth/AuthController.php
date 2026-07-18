<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\RotateRefreshToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RefreshRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Session lifecycle (build plan P1-03): refresh the access token by rotating the refresh token,
 * fetch the current user, and log out.
 */
final class AuthController extends Controller
{
    public function refresh(RefreshRequest $request, RotateRefreshToken $rotate): JsonResponse
    {
        $tokens = $rotate->handle($request->string('refresh_token')->toString());

        return response()->json($tokens->toArray());
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return UserResource::make($user->load('party'));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'ok']);
    }
}
