<?php

declare(strict_types=1);

namespace App\Domain\Execution\Actions;

use App\Domain\Media\StoreMedia;
use App\Models\Assignment;
use App\Models\JobReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * A worker submits an on-site job report (build plan P5-04, doc 02/06): what was done, materials,
 * extra charges, and before/after photos. Each photo is stored through {@see StoreMedia}, so it is
 * EXIF-stripped and its geo recorded in the DB — never embedded in the served file.
 */
final class SubmitJobReport
{
    public function __construct(private readonly StoreMedia $storeMedia) {}

    /**
     * @param  array<int, array{label: string, qty: int|float, unit_cost_minor: int}>  $materials
     * @param  array<int, array{file: UploadedFile, kind: string, latitude?: ?float, longitude?: ?float}>  $photos
     */
    public function handle(
        Assignment $assignment,
        string $summary,
        array $materials = [],
        int $extraChargesMinor = 0,
        array $photos = [],
    ): JobReport {
        $ownerPartyId = $assignment->worker()->firstOrFail()->party_id;
        $disk = (string) config('filesystems.default');

        return DB::transaction(function () use ($assignment, $summary, $materials, $extraChargesMinor, $photos, $ownerPartyId, $disk): JobReport {
            $report = JobReport::query()->create([
                'assignment_id' => $assignment->id,
                'summary' => $summary,
                'materials' => $materials,
                'extra_charges_minor' => $extraChargesMinor,
                'submitted_at' => now(),
            ]);

            foreach ($photos as $photo) {
                $this->storeMedia->handle(
                    file: $photo['file'],
                    ownerPartyId: $ownerPartyId,
                    attachableType: 'job_report',
                    attachableId: $report->id,
                    kind: $photo['kind'],
                    latitude: $photo['latitude'] ?? null,
                    longitude: $photo['longitude'] ?? null,
                    disk: $disk,
                );
            }

            return $report;
        });
    }
}
