<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\OutboxRelay;
use Illuminate\Console\Command;

/**
 * Drains the transactional outbox (build plan P0-07). Run continuously in production (a long-lived
 * worker, restarted by Horizon/supervisor); use --once in tests and one-shot invocations.
 */
final class RelayOutbox extends Command
{
    protected $signature = 'outbox:relay
        {--once : Process a single batch and exit}
        {--batch=100 : Maximum messages per batch}
        {--sleep=1 : Seconds to wait between empty passes}';

    protected $description = 'Relay committed transactional-outbox messages to their handlers';

    public function handle(OutboxRelay $relay): int
    {
        $batch = (int) $this->option('batch');
        $sleep = (int) $this->option('sleep');

        do {
            $published = $relay->drain($batch);

            if ($published > 0) {
                $this->info("Relayed {$published} outbox message(s).");
            } elseif ($this->option('once')) {
                $this->info('No due messages.');
            }

            if ($this->option('once')) {
                return self::SUCCESS;
            }

            if ($published === 0) {
                sleep(max(1, $sleep));
            }
        } while (true);
    }
}
