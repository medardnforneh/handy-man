<?php

declare(strict_types=1);

namespace App\Domain\Access;

/**
 * The account lifecycle gatekeeper (CLAUDE.md rule #8). Every move of `parties.status` or
 * `users.status` goes through here; nothing sets one of those columns directly.
 *
 * The matrix is deliberately small:
 *
 *   pending   → active     a phone was proven (VerifyOtp)
 *   pending   → suspended  barred before ever finishing signup — a throwaway used for abuse
 *   pending   → closed     erased, or abandoned
 *   active    → suspended  barred by staff
 *   active    → closed     erased, or closed on request
 *   suspended → active     reinstated by staff
 *   suspended → closed     erased while barred — which is a right, not a reward
 *   closed    → nothing    TERMINAL. An erased party's row survives for the ledger's FKs, and
 *                          bringing it back would resurrect an identity that was destroyed.
 *
 * Re-entering the state you are already in is allowed and is a no-op, so an admin double-click or
 * a replayed request does not raise.
 */
final class AccountStatusMachine
{
    /** @var array<string, list<AccountStatus>> */
    private const TRANSITIONS = [
        AccountStatus::Pending->value => [AccountStatus::Active, AccountStatus::Suspended, AccountStatus::Closed],
        AccountStatus::Active->value => [AccountStatus::Suspended, AccountStatus::Closed],
        AccountStatus::Suspended->value => [AccountStatus::Active, AccountStatus::Closed],
        AccountStatus::Closed->value => [],
    ];

    public function allows(AccountStatus $from, AccountStatus $to): bool
    {
        return $from === $to || in_array($to, self::TRANSITIONS[$from->value], true);
    }

    /**
     * @throws IllegalAccountTransition
     */
    public function assert(AccountStatus $from, AccountStatus $to): void
    {
        if (! $this->allows($from, $to)) {
            throw new IllegalAccountTransition($from, $to);
        }
    }

    /**
     * Every state an account in `$from` may legally move to, itself excluded. What an admin UI
     * should offer, so the buttons and the machine cannot disagree.
     *
     * @return list<AccountStatus>
     */
    public function nextFrom(AccountStatus $from): array
    {
        return self::TRANSITIONS[$from->value];
    }
}
