<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Models\CommsLog;
use App\Models\User;

/**
 * The per-user, per-channel send budget (doc 07, rule 2). Counts real sends from `comms_log` over
 * each configured rolling window; if any window is at its cap, the channel is over budget. `in_app`
 * is unlimited. A suppressed follow-up is a product signal — routine suppression means over-sending.
 */
final class CommsBudget
{
    public function allows(User $user, FollowUpChannel $channel): bool
    {
        if ($channel === FollowUpChannel::InApp) {
            return true;
        }

        /** @var array<string, int> $limits */
        $limits = config('followups.budget.'.$channel->value, []);

        foreach ($limits as $window => $max) {
            $since = match ($window) {
                'week' => now()->subWeek(),
                default => now()->subDay(),
            };

            $count = CommsLog::query()
                ->where('user_id', $user->id)
                ->where('channel', $channel->value)
                ->where('sent_at', '>=', $since)
                ->count();

            if ($count >= $max) {
                return false;
            }
        }

        return true;
    }
}
