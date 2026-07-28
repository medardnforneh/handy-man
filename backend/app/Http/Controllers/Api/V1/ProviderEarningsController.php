<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Actions\RequestPayout;
use App\Domain\Money\Ledger;
use App\Domain\Money\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PayoutResource;
use App\Models\Payout;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The provider's earnings summary (build plan P3-07/08) — the read model behind the Earnings screen.
 * `payable_available` mirrors what {@see RequestPayout} will let the provider
 * withdraw: the provider_payable balance minus funds already reserved by pending/processing payouts.
 * Read-only and self-scoped (the caller's own party), so no Action or Policy — like the credits balance.
 */
final class ProviderEarningsController extends Controller
{
    public function show(Request $request, Ledger $ledger): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payable = $ledger->availableMinor(AccountKind::ProviderPayable, $user->party_id);
        $reserved = (int) Payout::query()
            ->where('party_id', $user->party_id)
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->sum('amount_minor');
        $available = max(0, $payable - $reserved);
        $leadCredits = $ledger->availableMinor(AccountKind::LeadCreditLiability, $user->party_id);

        $payouts = Payout::query()
            ->where('party_id', $user->party_id)
            ->orderByDesc('requested_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'payable_available' => ['amount_minor' => $available, 'currency' => Money::XAF],
                'payable_pending' => ['amount_minor' => $reserved, 'currency' => Money::XAF],
                'lead_credits' => ['amount_minor' => $leadCredits, 'currency' => Money::XAF],
                'payouts' => PayoutResource::collection($payouts),
            ],
        ]);
    }
}
