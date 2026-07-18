<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\OtpException;
use App\Models\OtpChallenge;
use App\Models\Party;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Verifies an OTP code and returns the authenticated user (build plan P1-02). On success the phone
 * is marked verified and the user is created on first login (find-or-create) — signup and login are
 * the same flow, differing only by whether the user already exists. Token issuance is P1-03.
 */
final class VerifyOtp
{
    public function __construct(
        private readonly Outbox $outbox,
    ) {}

    /**
     * @return array{user: User, registered: bool}
     */
    public function handle(string $phoneE164, string $code, string $purpose): array
    {
        $challenge = OtpChallenge::query()
            ->where('phone_e164', $phoneE164)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if ($challenge === null || $challenge->isExpired()) {
            throw OtpException::invalidOrExpired();
        }

        if ($challenge->attempts >= (int) config('otp.max_verify_attempts')) {
            throw OtpException::locked();
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            // Persist the failed attempt OUTSIDE any transaction so it survives the rejection —
            // this is what feeds the hard-lock. (A rolled-back increment would let an attacker
            // brute-force forever.)
            $challenge->increment('attempts');

            throw OtpException::invalidOrExpired();
        }

        // Success path is atomic: re-lock the row, re-check it wasn't consumed by a concurrent
        // verify, mark it consumed, and resolve the user — all or nothing.
        return DB::transaction(function () use ($challenge, $phoneE164): array {
            $locked = OtpChallenge::query()->whereKey($challenge->id)->lockForUpdate()->first();

            if ($locked === null || $locked->isConsumed() || $locked->isExpired()) {
                throw OtpException::invalidOrExpired();
            }

            $locked->forceFill(['consumed_at' => now()])->save();

            return $this->resolveUser($phoneE164);
        });
    }

    /**
     * @return array{user: User, registered: bool}
     */
    private function resolveUser(string $phoneE164): array
    {
        $user = User::query()->where('phone_e164', $phoneE164)->first();
        $registered = false;

        if ($user === null) {
            $party = Party::query()->create([
                'kind' => Party::KIND_INDIVIDUAL,
                'display_name' => $phoneE164, // placeholder until the user sets a name
                'status' => 'active',
            ]);

            $user = User::query()->create([
                'party_id' => $party->id,
                'phone_e164' => $phoneE164,
                'status' => 'active',
                'phone_verified_at' => now(),
                'last_login_at' => now(),
            ]);

            $registered = true;
            $this->outbox->publish('user.registered', ['user_id' => $user->id]);
        } else {
            $user->forceFill([
                'phone_verified_at' => $user->phone_verified_at ?? now(),
                'last_login_at' => now(),
                'status' => $user->status === 'pending' ? 'active' : $user->status,
            ])->save();
        }

        return ['user' => $user, 'registered' => $registered];
    }
}
