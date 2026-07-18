<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\IssueAuthTokens;
use App\Domain\Identity\Actions\RequestOtp;
use App\Domain\Identity\Actions\VerifyOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RequestOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;

/**
 * OTP auth (build plan P1-02). Phone-first, OTP-first. Signup and login are the same flow — the
 * user is created on first successful verify. Token issuance is wired in P1-03.
 */
final class OtpController extends Controller
{
    public function request(RequestOtpRequest $request, RequestOtp $action): JsonResponse
    {
        $challenge = $action->handle(
            phoneE164: $request->string('phone_e164')->toString(),
            purpose: $request->string('purpose')->toString(),
            ip: $request->ip(),
            deviceId: $request->header(config('api.device_id_header')),
        );

        // Never return the code. The client just needs to know a challenge is live.
        return response()->json([
            'challenge_id' => $challenge->id,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ], 202);
    }

    public function verify(VerifyOtpRequest $request, VerifyOtp $verify, IssueAuthTokens $issue): JsonResponse
    {
        $result = $verify->handle(
            phoneE164: $request->string('phone_e164')->toString(),
            code: $request->string('code')->toString(),
            purpose: $request->string('purpose')->toString(),
        );

        $user = $result['user']->load('party');
        $tokens = $issue->handle($user, deviceId: $request->header(config('api.device_id_header')));

        return UserResource::make($user)
            ->additional([
                'registered' => $result['registered'],
                'tokens' => $tokens->toArray(),
            ])
            ->response()
            ->setStatusCode($result['registered'] ? 201 : 200);
    }
}
