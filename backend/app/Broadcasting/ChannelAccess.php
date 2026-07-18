<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;

/**
 * Authorization logic for private Reverb channels (build plan P0-12, doc 06). Extracted from
 * routes/channels.php so the "who may subscribe" rules are unit-testable without standing up the
 * broadcasting HTTP endpoint or a Pusher signature.
 *
 * Channel route parameters always arrive as strings.
 */
final class ChannelAccess
{
    /** A user may subscribe only to their OWN `user.{id}` channel. */
    public static function ownsUserChannel(User $user, string $userId): bool
    {
        return (string) $user->getKey() === $userId;
    }

    /**
     * Only engagement PARTICIPANTS may subscribe to `engagement.{id}` (doc 06 / P4-03). The
     * participant check lands with the engagements schema; until then DENY BY DEFAULT — a private
     * channel must never leak before its authorization is real.
     */
    public static function isEngagementParticipant(User $user, string $engagementId): bool
    {
        // TODO(P4-03): resolve the engagement and return $engagement->hasParticipant($user).
        return false;
    }
}
