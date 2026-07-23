<?php

declare(strict_types=1);

namespace App\Domain\Referrals;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Referrals (build plan P8-01, doc 04). Codes, claim-with-guards, and a **ledger-backed** reward that
 * qualifies only on the referee's first completed paid job — a referral credit is a real liability
 * (`promo_liability`), not a fictional number. Self-referral and a duplicate referee are refused.
 */
final class ReferralService
{
    public function __construct(private readonly Ledger $ledger) {}

    /**
     * The party's shareable code, minting one on first use.
     */
    public function codeFor(string $partyId): string
    {
        return ReferralCode::query()->firstOrCreate(
            ['party_id' => $partyId],
            ['code' => $this->generateCode(), 'created_at' => now()],
        )->code;
    }

    /**
     * A referee claims a code. Guards: unknown code, self-referral, already-referred.
     */
    public function claim(string $refereePartyId, string $code): Referral
    {
        $referrerPartyId = ReferralCode::query()->where('code', $code)->value('party_id');

        if (! is_string($referrerPartyId)) {
            throw new ReferralRefused('unknown_code');
        }
        if ($referrerPartyId === $refereePartyId) {
            throw new ReferralRefused('self_referral');
        }
        if (Referral::query()->where('referee_party_id', $refereePartyId)->exists()) {
            throw new ReferralRefused('already_referred');
        }

        // Fraud control (P8-02): a referrer over the weekly velocity limit gets their new referrals
        // flagged for a human — not blocked (a legit power-referrer exists), not auto-rewarded.
        $recent = Referral::query()
            ->where('referrer_party_id', $referrerPartyId)
            ->where('created_at', '>=', now()->subWeek())
            ->count();
        $overLimit = $recent >= (int) config('referrals.weekly_velocity_limit', 5);

        return Referral::query()->create([
            'referrer_party_id' => $referrerPartyId,
            'referee_party_id' => $refereePartyId,
            'status' => 'pending',
            'flagged_for_review' => $overLimit,
            'flag_reason' => $overLimit ? 'weekly_velocity' : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Qualify the referee's pending referral (on their first completed paid job) and book the reward:
     * a real liability to the referrer. Idempotent — only a pending referral qualifies.
     */
    public function qualify(string $refereePartyId): ?Referral
    {
        return DB::transaction(function () use ($refereePartyId): ?Referral {
            $referral = Referral::query()
                ->where('referee_party_id', $refereePartyId)
                ->where('status', 'pending')
                ->where('flagged_for_review', false) // a flagged referral waits for admin review
                ->lockForUpdate()
                ->first();

            if ($referral === null) {
                return null;
            }

            $reward = (int) config('referrals.reward_minor', 1000);

            $txn = $this->ledger->post(
                TxnKind::ReferralReward,
                [
                    // The promo cost comes out of revenue; the referrer is now owed the credit.
                    LedgerEntryInput::debit($this->ledger->account(AccountKind::PlatformRevenue, currency: Money::XAF), $reward),
                    LedgerEntryInput::credit($this->ledger->account(AccountKind::PromoLiability, $referral->referrer_party_id, Money::XAF), $reward),
                ],
                referenceType: 'referral',
                referenceId: $referral->id,
                memo: 'Referral reward',
            );

            $referral->update(['status' => 'qualified', 'qualified_at' => now(), 'reward_transaction_id' => $txn->id]);

            return $referral;
        });
    }

    /**
     * An admin clears a flagged referral (P8-02); if the referee already completed a job while it was
     * held, it qualifies immediately.
     */
    public function clearReview(Referral $referral): void
    {
        $referral->update(['flagged_for_review' => false, 'flag_reason' => null]);
        $this->qualify($referral->referee_party_id);
    }

    private function generateCode(): string
    {
        do {
            $code = 'HM-'.Str::upper(Str::random(6));
        } while (ReferralCode::query()->where('code', $code)->exists());

        return $code;
    }
}
