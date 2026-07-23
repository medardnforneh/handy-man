<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkSession
 */
final class WorkSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'is_open' => $this->isOpen(),
            'start' => $this->point('start'),
            'end' => $this->point('end'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function point(string $side): ?array
    {
        $point = $this->{"{$side}_point"};
        $accuracy = $this->{"{$side}_accuracy_m"};

        if ($point === null && $accuracy === null) {
            return null;
        }

        return [
            'latitude' => $point?->latitude,
            'longitude' => $point?->longitude,
            'accuracy_m' => $accuracy,
        ];
    }
}
