<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A stored file as the API describes it: where to FETCH it (the entitlement-checked media route),
 * never where it LIVES. The storage key is bucket layout — the same rule the verification and
 * message resources already keep, and the one that lets the object store move without an API change.
 *
 * @mixin Media
 */
final class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'url' => route('api.v1.media.show', ['media' => $this->id]),
            'sha256' => $this->sha256,
            'bytes' => $this->bytes,
            'captured_at' => $this->captured_at?->toIso8601String(),
        ];
    }
}
