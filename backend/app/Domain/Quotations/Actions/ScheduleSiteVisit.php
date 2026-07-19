<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Quotations\SiteVisitStatus;
use App\Models\Job;
use App\Models\SiteVisit;
use App\Models\User;
use App\Support\Outbox;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * A provider schedules a site visit for a job (build plan P2.5-04). Chargeable visits carry a fee
 * that is later credited against the quote the visit produces (see {@see AcceptQuotation}).
 */
final class ScheduleSiteVisit
{
    public function __construct(private readonly Outbox $outbox) {}

    public function handle(
        User $provider,
        Job $job,
        CarbonInterface $scheduledFor,
        bool $chargeable,
        int $feeMinor,
    ): SiteVisit {
        return DB::transaction(function () use ($provider, $job, $scheduledFor, $chargeable, $feeMinor): SiteVisit {
            $visit = SiteVisit::query()->create([
                'job_id' => $job->id,
                'provider_party_id' => $provider->party_id,
                'scheduled_for' => $scheduledFor,
                'is_chargeable' => $chargeable,
                'fee_minor' => $chargeable ? $feeMinor : 0,
                'currency' => 'XAF',
                'status' => SiteVisitStatus::Scheduled->value,
            ]);

            $this->outbox->publish('site_visit.scheduled', [
                'site_visit_id' => $visit->id,
                'job_id' => $job->id,
                'provider_party_id' => $provider->party_id,
            ]);

            return $visit;
        });
    }
}
