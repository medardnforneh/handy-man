<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Referrals\ReferralService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Referrals (build plan P8-01). A party fetches their shareable code; a referee claims one (guarded
 * against self-referral and duplicates). Qualification + the ledger-backed reward happen on the
 * referee's first completed job (server-side).
 */
final class ReferralController extends Controller
{
    public function code(Request $request, ReferralService $referrals): JsonResponse
    {
        return response()->json(['data' => ['code' => $referrals->codeFor($this->user($request)->party_id)]]);
    }

    public function claim(Request $request, ReferralService $referrals): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:32']]);

        // ReferralRefused (422) renders as problem+json automatically.
        $referral = $referrals->claim($this->user($request)->party_id, $validated['code']);

        return response()->json(['data' => ['id' => $referral->id, 'status' => $referral->status]], 201);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
