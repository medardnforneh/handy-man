<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OutboxMessage;
use Illuminate\Support\Carbon;

/**
 * The producer side of the transactional outbox (CLAUDE.md "Architecture conventions").
 *
 * Call {@see publish()} INSIDE the same DB transaction as the state change it announces. Because
 * the row is written in that transaction, a rollback un-publishes it automatically — the outbox
 * can never announce work that didn't commit.
 *
 * Never dispatch a queue job, fire a broadcast, or call a webhook directly from inside a
 * transaction. Publish to the outbox; the relay fans out after commit.
 */
final class Outbox
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(
        string $type,
        array $payload,
        ?string $partitionKey = null,
        ?Carbon $availableAt = null,
    ): OutboxMessage {
        return OutboxMessage::query()->create([
            'type' => $type,
            'payload' => $payload,
            'partition_key' => $partitionKey,
            'created_at' => now(),
            'available_at' => $availableAt ?? now(),
        ]);
    }
}
