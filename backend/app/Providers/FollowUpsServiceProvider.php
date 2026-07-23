<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\FollowUps\Listeners\FollowUpOrchestrator;
use App\Events\OutboxMessagePublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the follow-up orchestrator (build plan P7-02). It subscribes to the outbox seam so follow-ups
 * are scheduled/cancelled only for committed domain events (schedule on event, cancel on event).
 */
final class FollowUpsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OutboxMessagePublished::class, FollowUpOrchestrator::class);
    }
}
