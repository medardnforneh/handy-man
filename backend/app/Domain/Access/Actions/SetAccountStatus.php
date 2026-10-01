<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\AccountStatus;
use App\Domain\Access\AccountStatusMachine;
use App\Models\Party;
use App\Models\ProviderProfile;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Suspend or reinstate a party (the enforcement half of P6-07's report queue).
 *
 * `user_status` carried `suspended` from the first migration and nothing ever wrote it. Staff could
 * receive a report about a dangerous provider, record a decision on it, and have no way to stop
 * that person working — `ReviewReport` deliberately "records a decision and nothing else", which is
 * right, but the something-else it hands off to did not exist. This is it.
 *
 * Suspension is one act across three places, and all three matter:
 *
 *   1. `parties.status` AND every `users.status` under it. The party is the identity; the user rows
 *      are what authenticate. Setting only one leaves a door open.
 *   2. Every live session. A suspension that waits for a 15-minute access token to lapse — and for
 *      a 30-day refresh token to not be used — is not a suspension. Refresh families are revoked
 *      and Sanctum tokens deleted, so the next request is the last one.
 *   3. `provider_profiles.suspended_at`, which is what search, the public directory and ranking
 *      already read. It was the ONLY thing suspension used to mean; now it is the discovery half of
 *      something that also has an authentication half.
 *
 * Reinstating restores the statuses and clears `suspended_at`, but does NOT bring sessions back:
 * the tokens are gone and the person signs in again. That is the safe direction to be wrong in.
 *
 * Both directions go through {@see AccountStatusMachine} (CLAUDE.md rule #8) and both are recorded
 * in the append-only activity log with the actor, the reason and the previous state — `parties` has
 * no reviewer column, and the same reasoning as `ReviewReport` applies: attribution lives in the
 * log, not in a schema change.
 */
final class SetAccountStatus
{
    public function __construct(
        private readonly AccountStatusMachine $machine,
        private readonly ActivityLogger $log,
        private readonly Outbox $outbox,
    ) {}

    /**
     * Bar the party from the platform.
     */
    public function suspend(Party $party, User $actor, string $reason, ?string $ip = null): Party
    {
        return $this->move($party, AccountStatus::Suspended, $actor, $reason, $ip);
    }

    /**
     * Let them back in. They sign in again — the revoked sessions are not restored.
     */
    public function reinstate(Party $party, User $actor, string $reason, ?string $ip = null): Party
    {
        return $this->move($party, AccountStatus::Active, $actor, $reason, $ip);
    }

    /**
     * Close the party for good. Used by erasure (P1-10), which has already destroyed the
     * identifiers by the time it gets here — this is the status half, routed through the machine
     * rather than written straight onto the row.
     */
    public function close(Party $party, ?User $actor = null, string $reason = 'erasure', ?string $ip = null): Party
    {
        return $this->move($party, AccountStatus::Closed, $actor, $reason, $ip);
    }

    private function move(Party $party, AccountStatus $to, ?User $actor, string $reason, ?string $ip): Party
    {
        $from = AccountStatus::from($party->status);
        $this->machine->assert($from, $to);

        if ($from === $to) {
            return $party; // idempotent: a double-click is not an error
        }

        return DB::transaction(function () use ($party, $from, $to, $actor, $reason, $ip): Party {
            $party->forceFill(['status' => $to->value])->save();

            /** @var list<string> $userIds */
            $userIds = [];
            foreach (User::query()->where('party_id', $party->id)->get() as $user) {
                // Per user, through the machine: a party's users are not guaranteed to share its
                // state (one may already be closed), and the matrix decides each one.
                $userFrom = AccountStatus::from($user->status);
                if (! $this->machine->allows($userFrom, $to) || $userFrom === $to) {
                    continue;
                }

                $user->forceFill(['status' => $to->value])->save();
                $userIds[] = (string) $user->getKey();
            }

            if ($to !== AccountStatus::Active) {
                $this->revokeSessions($party);
            }

            ProviderProfile::query()
                ->where('party_id', $party->id)
                ->update(['suspended_at' => $to === AccountStatus::Active ? null : now()]);

            $this->log->log(
                "party.{$to->value}",
                $party,
                $actor?->getKey(),
                ['from' => $from->value, 'to' => $to->value, 'reason' => $reason, 'users' => $userIds],
                $ip,
            );

            // Downstream cares: push registrations, the follow-up ladder, anything holding its own
            // list of who is reachable. Ids only, no PII (rule #6).
            $this->outbox->publish("party.{$to->value}", [
                'party_id' => $party->id,
                'from' => $from->value,
                'to' => $to->value,
            ]);

            return $party->refresh();
        });
    }

    /**
     * End every session the party holds, on every device.
     *
     * Both halves, because either alone leaves a usable session: deleting Sanctum's access tokens
     * without the refresh family means the app mints a fresh access token a minute later, and
     * revoking the family without the access tokens leaves the current one live for its 15 minutes.
     */
    private function revokeSessions(Party $party): void
    {
        $users = User::query()->where('party_id', $party->id)->get();

        foreach ($users as $user) {
            $user->tokens()->delete();
        }

        RefreshToken::query()
            ->whereIn('user_id', $users->modelKeys())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
