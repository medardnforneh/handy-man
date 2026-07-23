<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            'storage_path' => $this->storage_path,
            'sha256' => $this->sha256,
            'bytes' => $this->bytes,
            'captured_at' => $this->captured_at?->toIso8601String(),
        ];
    }
}
