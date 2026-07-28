<?php

declare(strict_types=1);

use App\Broadcasting\ChannelAccess;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels (Reverb) — authorization
|--------------------------------------------------------------------------
|
| Every private channel names WHO may subscribe. Reverb only broadcasts ephemeral events; it is
| never the source of truth (CLAUDE.md stack table). The REST refetch is authoritative on
| reconnect (P4-07). Authorization rules live in App\Broadcasting\ChannelAccess (unit-tested).
|
*/

/*
 * Both guards are named explicitly: the Blade/Filament side authenticates by session (`web`) while
 * the mobile client is Bearer-only (`sanctum`, via POST /api/v1/broadcasting/auth). Without
 * `sanctum` here a token client is refused its own channel, because Broadcast resolves the user
 * through the channel's guards rather than trusting whatever the route already authenticated.
 */
$guards = ['guards' => ['web', 'sanctum']];

/*
 * These MUST be closures, not `[ChannelAccess::class, 'method']` array callables. Laravel reflects
 * the callback to extract the channel's route parameters, and that reflection only accepts a
 * Closure or a class name — an array callable throws a TypeError, so the rule never runs and every
 * subscription is refused. The logic still lives in the unit-tested ChannelAccess; these are only
 * the adapters.
 */
Broadcast::channel(
    'user.{userId}',
    fn (User $user, string $userId): bool => ChannelAccess::ownsUserChannel($user, $userId),
    $guards,
);

Broadcast::channel(
    'engagement.{engagementId}',
    fn (User $user, string $engagementId): bool => ChannelAccess::isEngagementParticipant($user, $engagementId),
    $guards,
);
