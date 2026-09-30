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
use App\Support\Cursor;
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
    /** How much history rides along with the summary. The rest is paged from `history()`. */
    private const EMBEDDED_HISTORY = 50;

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

        // The FIRST PAGE of the history, embedded. It stays embedded because removing a field is
        // forbidden (rule #4) and because the screen wants a balance and its recent history in one
        // round trip on a slow connection — but 50 was all there ever was, so a provider past their
        // fiftieth payout could not reach the earlier ones. `GET /provider/payouts` is the rest.
        // One more than we show, purely to answer "is there more behind this".
        $payouts = Payout::query()
            ->where('party_id', $user->party_id)
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->limit(self::EMBEDDED_HISTORY + 1)
            ->get();

        $hasMore = $payouts->count() > self::EMBEDDED_HISTORY;
        $payouts = $payouts->take(self::EMBEDDED_HISTORY);
        $oldest = $payouts->last();

        return response()->json([
            'data' => [
                'payable_available' => ['amount_minor' => $available, 'currency' => Money::XAF],
                'payable_pending' => ['amount_minor' => $reserved, 'currency' => Money::XAF],
                'lead_credits' => ['amount_minor' => $leadCredits, 'currency' => Money::XAF],
                'payouts' => PayoutResource::collection($payouts),
                // Where the embedded page stops. The cursor is minted HERE, not by the client: its
                // encoding is the server's to change, and a client that builds its own breaks on
                // the next deploy. Null means this is the whole history.
                'payouts_next_cursor' => $hasMore && $oldest !== null
                    ? Cursor::encode($oldest->requested_at->toIso8601String(), $oldest->id)
                    : null,
            ],
        ]);
    }

    /**
     * The payout history on its own, cursor-paged.
     *
     * A separate endpoint rather than a cursor on the embedded array, because the array lives
     * inside a composite payload: paging it in place would mean either a cursor that pages one
     * field of a response whose other fields are not a page (confusing, and impossible to describe
     * honestly in the spec), or recomputing the balances on every scroll. The summary keeps its
     * first page for the one-round-trip case; this is how the screen reaches the rest.
     */
    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $limit = Cursor::limit($request->query('limit'), 50);

        $query = Payout::query()
            ->where('party_id', $user->party_id)
            ->orderByDesc('requested_at')
            ->orderByDesc('id');

        Cursor::applyBefore($query, 'requested_at', $request->query('before'));

        $payouts = $query->limit($limit + 1)->get();
        $hasMore = $payouts->count() > $limit;
        $payouts = $payouts->take($limit);
        $last = $payouts->last();

        return PayoutResource::collection($payouts)
            ->additional(['meta' => [
                'has_more' => $hasMore,
                'next_cursor' => $hasMore && $last !== null
                    ? Cursor::encode($last->requested_at->toIso8601String(), $last->id)
                    : null,
            ]])
            ->response();
    }
}
