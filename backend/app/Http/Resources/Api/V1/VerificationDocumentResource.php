<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\VerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VerificationDocument
 */
final class VerificationDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The storage path is never exposed — access is only ever via a signed short-TTL URL.
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'grants_tier' => $this->grants_tier,
            'reject_reason' => $this->reject_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
