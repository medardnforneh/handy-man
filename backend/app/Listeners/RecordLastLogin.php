<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Identity\Actions\VerifyOtp;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Stamps `last_login_at` on a session login — which, in this system, means the admin panel.
 *
 * The column was written only by {@see VerifyOtp}, the app's token
 * flow, so it was accurate for everyone EXCEPT the people whose sign-ins matter most: a staff member
 * who only ever opens the panel read "never signed in" forever. That makes the dormant-account
 * column on the staff roster — the one that tells you whose access is safe to revoke — worse than
 * absent, because it reads as a fact rather than as a gap.
 */
final class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // Not `touch()`: `updated_at` means "this account record changed", and signing in does not
        // change the account.
        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}
