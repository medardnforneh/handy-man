<?php

declare(strict_types=1);

namespace App\Domain\Safety\Actions;

use App\Models\Report;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * A party files a report about another (build plan P6-07). It queues a human look (admin) and never
 * auto-penalises — an outbox alert notifies staff. Off-platform solicitation is a first-class
 * category because leakage is the platform's core risk.
 */
final class ReportParty
{
    public function __construct(private readonly Outbox $outbox) {}

    public function handle(
        string $reporterPartyId,
        string $subjectPartyId,
        string $category,
        string $body,
        ?string $jobId = null,
    ): Report {
        return DB::transaction(function () use ($reporterPartyId, $subjectPartyId, $category, $body, $jobId): Report {
            $report = Report::query()->create([
                'reporter_party_id' => $reporterPartyId,
                'subject_party_id' => $subjectPartyId,
                'job_id' => $jobId,
                'category' => $category,
                'body' => $body,
                'status' => 'open',
                'created_at' => now(),
            ]);

            $this->outbox->publish('report.filed', [
                'report_id' => $report->id,
                'subject_party_id' => $subjectPartyId,
                'category' => $category,
            ]);

            return $report;
        });
    }
}
