<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Workspace\Actions\AutoApproveDeliverables as AutoApproveAction;
use Illuminate\Console\Command;

/**
 * Auto-approves deliverables past the review window (build plan P3-11). Scheduled hourly.
 */
final class AutoApproveDeliverables extends Command
{
    protected $signature = 'deliverables:auto-approve';

    protected $description = 'Auto-approve submitted deliverables the customer left un-reviewed past the window';

    public function handle(AutoApproveAction $action): int
    {
        $count = $action->handle();

        $this->info("Auto-approved {$count} deliverable(s).");

        return self::SUCCESS;
    }
}
