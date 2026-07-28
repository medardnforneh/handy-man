<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Workspace\Listeners\BroadcastOnOutboxMessage;
use App\Events\OutboxMessagePublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * The engagement workspace (doc 06). Subscribes the live-broadcast fan-out to the outbox seam,
 * mirroring how {@see NotificationsServiceProvider} wires push — both ride committed messages.
 */
final class WorkspaceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OutboxMessagePublished::class, BroadcastOnOutboxMessage::class);
    }
}
