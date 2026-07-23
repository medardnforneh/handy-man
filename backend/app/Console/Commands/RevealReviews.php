<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reviews\Actions\RevealDueReviews;
use Illuminate\Console\Command;

/**
 * Reveals reviews whose double-blind window has closed (build plan P6-08). Scheduled to run
 * regularly so a lone review publishes promptly once its 14-day window expires.
 */
final class RevealReviews extends Command
{
    protected $signature = 'reviews:reveal';

    protected $description = 'Publish pending reviews whose double-blind window has closed';

    public function handle(RevealDueReviews $action): int
    {
        $count = $action->handle();

        $this->info("Revealed reviews for {$count} engagement(s).");

        return self::SUCCESS;
    }
}
