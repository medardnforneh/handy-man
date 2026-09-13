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
    protected $signature = 'data:retain';

    protected $description = 'Destroy personal data past its retention period (config/retention.php)';

    public function handle(ApplyRetention $action): int
    {
        $report = $action->handle();

        foreach ($report as $rule => $count) {
            $this->line(sprintf('%-24s %d', $rule, $count));
        }
        Log::info('retention.applied', $report);

        return self::SUCCESS;
    }
}
