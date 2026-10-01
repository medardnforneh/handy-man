<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Privacy\ApplyRetention;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Applies the retention schedule (doc 04, `config/retention.php`). Nightly; the counts it logs are
 * the evidence, for the processing register, that personal data does not outlive its purpose.
 */
final class RetainData extends Command
{
    protected $signature = 'data:retain {--dry-run : Report what each rule would destroy, and destroy nothing}';

    protected $description = 'Destroy personal data past its retention period (config/retention.php)';

    public function handle(ApplyRetention $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $report = $action->handle($dryRun);

        if ($dryRun) {
            // The workspace periods in config/retention.php are a starting point, not a finding.
            // Nobody should have to learn what a number means by watching it delete a year of
            // someone's threads, so this is how you find out first.
            $this->warn('DRY RUN — nothing was destroyed. These are the counts each rule would have taken.');
        }

        foreach ($report as $rule => $count) {
            $this->line(sprintf('%-24s %d', $rule, $count));
        }

        Log::info($dryRun ? 'retention.dry_run' : 'retention.applied', $report);

        return self::SUCCESS;
    }
}
