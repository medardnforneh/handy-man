<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\OutboxMessage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by the relay once a committed outbox message is dispatched. This is the seam where real
 * fan-out handlers (notifications, broadcasts, webhooks) subscribe by inspecting `$type`. Kept
 * deliberately generic so the outbox stays decoupled from any specific consumer.
 */
final class OutboxMessagePublished
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $type,
        public array $payload,
        public OutboxMessage $message,
    ) {}
}
