<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\FollowUps\Actions\DispatchFollowUps as DispatchAction;
use Illuminate\Console\Command;

/**
 * Dispatches due follow-ups (build plan P7-01, doc 07). Scheduled to run frequently.
 */
final class DispatchFollowUps extends Command
{
    protected $signature = 'follow-ups:dispatch';

    protected $description = 'Send due follow-ups, applying the consent gate and channel budget';

    public function handle(DispatchAction $action): int
    {
        $sent = $action->handle();

        $this->info("Sent {$sent} follow-up(s).");

        return self::SUCCESS;
    }
}
