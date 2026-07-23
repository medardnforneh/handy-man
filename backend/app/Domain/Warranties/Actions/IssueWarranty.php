<?php

declare(strict_types=1);

namespace App\Domain\Warranties\Actions;

use App\Domain\Warranties\WarrantyStatus;
use App\Models\Engagement;
use App\Models\Warranty;

/**
 * Issues a warranty on a completed engagement (build plan P6-11, doc 06). One per engagement
 * (DB-unique). The window runs from now for the given duration; the terms are free text (bilingual,
 * doc 09). Marketed as the reason to stay on-platform — the warranty only exists here.
 */
final class IssueWarranty
{
    public function handle(Engagement $engagement, int $durationDays, ?string $terms = null): Warranty
    {
        $startsAt = now();

        return Warranty::query()->create([
            'engagement_id' => $engagement->id,
            'duration_days' => $durationDays,
            'starts_at' => $startsAt,
            'expires_at' => $startsAt->copy()->addDays($durationDays),
            'terms' => $terms,
            'status' => WarrantyStatus::Active->value,
        ]);
    }
}
