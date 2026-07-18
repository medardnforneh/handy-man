<?php

declare(strict_types=1);

use App\Broadcasting\ChannelAccess;
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

Broadcast::channel('user.{userId}', [ChannelAccess::class, 'ownsUserChannel']);

Broadcast::channel('engagement.{engagementId}', [ChannelAccess::class, 'isEngagementParticipant']);
