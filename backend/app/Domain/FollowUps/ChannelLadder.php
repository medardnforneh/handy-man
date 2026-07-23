<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Models\Device;
use App\Models\User;

/**
 * The routing ladder (build plan P7-06, doc 07): cheapest and least intrusive first. The follow-up
 * row itself is always the in-app record; this picks the *outbound* delivery channel — push if the
 * user has a live device token, else WhatsApp (the workhorse for this market), else SMS. A caller may
 * still override (e.g. a receipt goes to email).
 */
final class ChannelLadder
{
    public function pick(User $user): FollowUpChannel
    {
        $hasPushToken = Device::query()
            ->where('user_id', $user->id)
            ->whereNotNull('push_token')
            ->whereNull('revoked_at')
            ->exists();

        return $hasPushToken ? FollowUpChannel::Push : FollowUpChannel::WhatsApp;
    }
}
