<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A provider's prepaid lead-credit balance (build plan P3-07). Purchases arrive via payment intents
 * (purpose `lead_credits`); spends happen when bidding. Here we just report what's available.
 */
final class LeadCreditController extends Controller
{
    public function balance(Request $request, Ledger $ledger): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $available = $ledger->availableMinor(AccountKind::LeadCreditLiability, $user->party_id);

        return response()->json([
            'data' => [
                'available' => ['amount_minor' => $available, 'currency' => Money::XAF],
            ],
        ]);
    }
}
