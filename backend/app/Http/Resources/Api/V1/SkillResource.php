<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Skill
 */
final class SkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = in_array($request->query('locale'), ['fr', 'en'], true)
            ? (string) $request->query('locale')
            : (string) config('app.locale');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name($locale),
            'is_leaf' => $this->is_leaf,
            'risk_tier' => $this->risk_tier,
            'requires_license' => $this->requires_license,
            'children' => SkillResource::collection($this->whenLoaded('children')),
        ];
    }
}
