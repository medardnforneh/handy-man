<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Safety\Actions\RaiseOverdueCheckIns;
use Illuminate\Console\Command;

/**
 * The check-in-overdue watchdog (build plan P6-06). Scheduled to run frequently; raises a
 * `check_in_overdue` safety alert for any worker overdue on an on-site booking.
 */
final class CheckInWatchdog extends Command
{
    protected $signature = 'safety:check-in-watchdog';

    protected $description = 'Flag workers who are overdue to check in on an on-site booking';

    public function handle(RaiseOverdueCheckIns $action): int
    {
        $count = $action->handle();

        $this->info("Raised {$count} check-in-overdue alert(s).");

        return self::SUCCESS;
    }
}
