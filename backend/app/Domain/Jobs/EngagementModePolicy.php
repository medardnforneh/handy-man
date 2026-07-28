<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Models\Job;

/**
 * The feature-applicability matrix for engagement modes (doc 06). This is the SINGLE place that
 * branches on mode — everywhere else asks this object "does this job support X?" instead of writing
 * `if ($mode === 'remote')`. Geography, dispatch, check-in, panic and share-my-job exist only for
 * physical work; remote work substitutes deliverables + escrow.
 *
 * | Feature      | onsite | remote | hybrid |
 * |--------------|--------|--------|--------|
 * | address      |   ✅   |   ❌   |   ✅   |
 * | dispatch     |   ✅   |   ❌   |   ✅   |
 * | check-in     |   ✅   |   ❌   |   ✅   |
 * | panic        |   ✅   |   ❌   |   ✅   |
 * | share-my-job |   ✅   |   ❌   |   ✅   |
 * | site visit   |   ✅   |   ❌   |   ✅   |
 * | job report   |   ✅   |   ❌   |   ✅   |
 * | deliverables |   ❌   |   ✅   |   ✅   |
 *
 * The job report (P5-04) sits with the physical features, not with deliverables: it records
 * materials consumed and before/after photos OF A PLACE. A remote engagement proves its work
 * through deliverables, so it exposes no report affordance and the server refuses one.
 */
final class EngagementModePolicy
{
    /** @var array<string, array<string, bool>> */
    private const MATRIX = [
        'onsite' => ['address' => true, 'dispatch' => true, 'check_in' => true, 'panic' => true, 'share_job' => true, 'site_visit' => true, 'job_report' => true, 'deliverables' => false],
        'remote' => ['address' => false, 'dispatch' => false, 'check_in' => false, 'panic' => false, 'share_job' => false, 'site_visit' => false, 'job_report' => false, 'deliverables' => true],
        'hybrid' => ['address' => true, 'dispatch' => true, 'check_in' => true, 'panic' => true, 'share_job' => true, 'site_visit' => true, 'job_report' => true, 'deliverables' => true],
    ];

    public function requiresAddress(EngagementMode $mode): bool
    {
        return self::MATRIX[$mode->value]['address'];
    }

    public function supportsDispatch(EngagementMode $mode): bool
    {
        return self::MATRIX[$mode->value]['dispatch'];
    }

    public function supportsCheckIn(EngagementMode $mode): bool
    {
        return self::MATRIX[$mode->value]['check_in'];
    }

    public function supportsPanic(EngagementMode $mode): bool
    {
        return self::MATRIX[$mode->value]['panic'];
    }

    public function supportsShareJob(EngagementMode $mode): bool
    {
        return self::MATRIX[$mode->value]['share_job'];
    }

    public function supportsSiteVisit(EngagementMode $mode): bool
    {
        return self::MATRIX[$mode->value]['site_visit'];
    }

    public function supportsJobReport(EngagementMode $mode): bool
    {
        return self::MATRIX[$mode->value]['job_report'];
    }

    public function usesDeliverables(EngagementMode $mode): bool
    {
        return self::MATRIX[$mode->value]['deliverables'];
    }

    /**
     * The whole applicability row for a job — handy for a client to render the right affordances.
     *
     * @return array<string, bool>
     */
    public function forJob(Job $job): array
    {
        return self::MATRIX[$job->engagement_mode->value];
    }
}
