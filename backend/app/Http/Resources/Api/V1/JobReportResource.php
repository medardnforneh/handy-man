<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\JobReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobReport
 */
final class JobReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'summary' => $this->summary,
            'materials' => $this->materials,
            'extra_charges_minor' => $this->extra_charges_minor,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'media' => MediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
