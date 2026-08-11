<?php

declare(strict_types=1);

namespace App\Domain\Safety\Actions;

use App\Domain\Safety\ReportAlreadyClosed;
use App\Models\Report;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A human closes a report (build plan P6-07). Reports feed a queue and never auto-penalise anyone,
 * so this records a decision and nothing else — no suspension, no rating effect, no money.
 *
 * The reports table carries no reviewer column, so attribution goes to the append-only activity log
 * (P6-02) rather than a schema change: who decided, what they decided, and the note they left.
 * Once-only — a second decision on a closed report is refused rather than silently overwriting the
 * first one's attribution.
 */
final class ReviewReport
{
    /** Terminal states a human may move a report into. */
    public const DECISIONS = ['reviewing', 'resolved', 'dismissed'];

    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(Report $report, User $reviewer, string $decision, string $note, ?string $ip = null): Report
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw new InvalidArgumentException("Unknown report decision [{$decision}].");
        }

        if (in_array($report->status, ['resolved', 'dismissed'], true)) {
            throw new ReportAlreadyClosed($report->status);
        }

        return DB::transaction(function () use ($report, $reviewer, $decision, $note, $ip): Report {
            $report->update([
                'status' => $decision,
                // 'reviewing' is a step on the way, not an outcome, so it does not stamp a close time.
                'resolved_at' => in_array($decision, ['resolved', 'dismissed'], true) ? now() : null,
            ]);

            $this->log->log(
                'report.reviewed',
                $report,
                $reviewer->id,
                ['decision' => $decision, 'note' => $note, 'category' => $report->category],
                $ip,
            );

            return $report->refresh();
        });
    }
}
