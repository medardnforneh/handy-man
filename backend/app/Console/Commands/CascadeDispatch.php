<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Jobs\Actions\RedispatchStaleJobs;
use Illuminate\Console\Command;

/**
 * The dispatch offer-expiry cascade (build plan P8-03). Scheduled to run after `offers:expire`.
 */
final class CascadeDispatch extends Command
{
    protected $signature = 'dispatch:cascade';

    protected $description = 'Re-dispatch stale dispatch-mode jobs to the next batch of ranked providers';

    public function handle(RedispatchStaleJobs $action): int
    {
        $count = $action->handle();

        $this->info("Cascaded {$count} new offer(s).");

        return self::SUCCESS;
    }
}
