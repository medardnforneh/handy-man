<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Offers\OfferStatus;
use App\Models\JobOffer;
use Illuminate\Console\Command;

/**
 * Expires pending offers past their deadline (build plan P2-05). Scheduled to run frequently; also
 * the safety net behind the offer-expiry cascade (P8-03).
 */
final class ExpireOffers extends Command
{
    protected $signature = 'offers:expire';

    protected $description = 'Mark pending job offers past their expiry as expired';

    public function handle(): int
    {
        $count = JobOffer::query()
            ->where('status', OfferStatus::Pending->value)
            ->where('expires_at', '<', now())
            ->update(['status' => OfferStatus::Expired->value]);

        $this->info("Expired {$count} offer(s).");

        return self::SUCCESS;
    }
}
