<?php

declare(strict_types=1);

namespace App\Domain\Access;

/**
 * An account's lifecycle state — the PHP side of the `user_status` Postgres enum, which sits on
 * BOTH `parties.status` and `users.status` (P1-01).
 *
 * The enum and both columns have existed since the first migration and nothing ever read them.
 * `suspended` could not be reached (no admin action wrote it) and would not have mattered if it
 * were (no code checked it), so "suspend" meant, at most, hiding a provider from search while they
 * kept signing in, accepting offers, messaging customers and requesting payouts. This enum, the
 * machine beside it and {@see \App\Http\Middleware\EnsureAccountActive} are what give it teeth.
 *
 * `pending` is NOT a punitive state: it is where a party starts before a phone is proven, and
 * `VerifyOtp` promotes it. It authenticates, or nobody could ever finish signing up.
 */
enum AccountStatus: string
{
    /** Created, phone not yet proven. Promoted to Active by a successful OTP verify. */
    case Pending = 'pending';

    case Active = 'active';

    /** Barred by staff. Authenticates nowhere; existing sessions are revoked on the way in. */
    case Suspended = 'suspended';

    /** Erased or closed. Terminal — nothing moves out of here. */
    case Closed = 'closed';

    /**
     * Whether an account in this state may hold a session.
     *
     * This is the single predicate the auth paths ask (OTP verify, refresh rotation, the bearer
     * middleware, the admin panel), so there is one answer rather than four.
     */
    public function canAuthenticate(): bool
    {
        return $this === self::Pending || $this === self::Active;
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }
}
