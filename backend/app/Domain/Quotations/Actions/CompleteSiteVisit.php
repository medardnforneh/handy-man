<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Quotations\SiteVisitStatus;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * The provider marks a site visit completed (build plan P2.5-04), optionally linking the quotation it
 * produced. That link is what makes the visit fee creditable on quote acceptance.
 */
final class CompleteSiteVisit
{
    public function __construct(private readonly Outbox $outbox) {}

    public function handle(SiteVisit $visit, ?string $outcomeNotes, ?Quotation $resultingQuote): SiteVisit
    {
        return DB::transaction(function () use ($visit, $outcomeNotes, $resultingQuote): SiteVisit {
            $visit->forceFill([
                'status' => SiteVisitStatus::Completed->value,
                'completed_at' => now(),
                'outcome_notes' => $outcomeNotes,
                'resulting_quotation_id' => $resultingQuote?->id,
            ])->save();

            $this->outbox->publish('site_visit.completed', [
                'site_visit_id' => $visit->id,
                'job_id' => $visit->job_id,
                'resulting_quotation_id' => $resultingQuote?->id,
            ]);

            return $visit;
        });
    }
}
