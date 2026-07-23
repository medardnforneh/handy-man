<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Referrals\QualifyReferralOnCompletion;
use App\Events\OutboxMessagePublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires referral qualification to the outbox (build plan P8-01) — a referral qualifies only on a
 * committed engagement completion, and the reward it books is a real ledger liability.
 */
final class ReferralsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OutboxMessagePublished::class, QualifyReferralOnCompletion::class);
    }
}
