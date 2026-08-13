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
        $issued = $action->handle(
            phoneE164: $request->string('phone_e164')->toString(),
            purpose: $request->string('purpose')->toString(),
            ip: $request->ip(),
            deviceId: $request->header(config('api.device_id_header')),
        );

        // Never return the code. The client just needs to know a challenge is live.
        $payload = [
            'challenge_id' => $issued->challenge->id,
            'expires_at' => $issued->challenge->expires_at->toIso8601String(),
        ];

        // ...with exactly one exception: a developer running this on their own machine, where
        // there is no SMS gateway and the code otherwise only exists in a log file. Gated on the
        // ENVIRONMENT, not on a config flag or a header, so it cannot be switched on by accident
        // or by a request — anything other than `local` and this field does not exist. A test
        // asserts its absence in production.
        if (app()->environment('local')) {
            $payload['dev_code'] = $issued->code;
        }

        return response()->json($payload, 202);
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
