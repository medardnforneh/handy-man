<?php

declare(strict_types=1);

namespace App\Support;

use App\Events\OutboxMessagePublished;
use App\Models\OutboxMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The consumer side of the transactional outbox — the relay worker (build plan P0-07).
 *
 * Drains committed, due, unprocessed rows and fans them out via {@see OutboxMessagePublished}.
 * Rows are claimed with `FOR UPDATE SKIP LOCKED` so multiple relay workers can run concurrently
 * without processing the same row twice. A message that fails to dispatch is left unprocessed with
 * its error recorded and attempt count bumped, so it is retried on the next pass.
 */
final class OutboxRelay
{
    /**
     * Process up to $batch due messages. Returns the number successfully published.
     */
    public function drain(int $batch = 100): int
    {
        return DB::transaction(function () use ($batch): int {
            /** @var Collection<int, OutboxMessage> $messages */
            $messages = OutboxMessage::query()
                ->pending()
                ->due()
                ->orderBy('id')
                ->limit($batch)
                ->lock('for update skip locked')
                ->get();

            $published = 0;

            foreach ($messages as $message) {
                try {
                    OutboxMessagePublished::dispatch($message->type, $message->payload, $message);

                    $message->processed_at = now();
                    $message->attempts = $message->attempts + 1;
                    $message->last_error = null;
                    $message->save();

                    $published++;
                } catch (Throwable $e) {
                    // Leave it unprocessed for the next pass; record why.
                    $message->attempts = $message->attempts + 1;
                    $message->last_error = $e->getMessage();
                    $message->save();
                }
            }

            return $published;
        });
    }
}
