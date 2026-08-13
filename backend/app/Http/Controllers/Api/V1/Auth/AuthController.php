<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\RotateRefreshToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RefreshRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * End the session.
     *
     * Deleting the access token is not enough, and used to be all this did: a refresh token lives
     * 30 days (P1-03), so a logged-out session could mint a brand-new access token for another
     * month. "Log out" then meant "forget the short-lived half", which is the opposite of what
     * someone handing back a shared phone — or wiping a stolen one remotely — is asking for.
     *
     * Scoped to THIS device rather than the whole account: the refresh token carries the device it
     * was issued to (`refresh_tokens.device_id`), and logging out of a phone should not sign the
     * same person out of their tablet. A device-less token (an older session, or a client that
     * sent no `X-Device-Id`) belongs to nothing we can distinguish, so it goes with the current
     * device's family rather than surviving as an orphan nobody can revoke.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $token = $user?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if ($user !== null) {
            $deviceId = $request->header('X-Device-Id');

            RefreshToken::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->where(function (Builder $query) use ($deviceId): void {
                    $query->whereNull('device_id');
                    if (is_string($deviceId) && $deviceId !== '') {
                        $query->orWhere('device_id', $deviceId);
                    }
                })
                ->update(['revoked_at' => now()]);
        }

        return response()->json(['message' => 'ok']);
    }
}
