<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ProviderSkill;
use App\Support\RequestLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderSkill
 */
final class ProviderSkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The taxonomy is bilingual (P1-07), so the label travels with the row rather than making
        // every client keep its own id→name table.
        $locale = RequestLocale::for($request);

        return [
            'id' => $this->id,
            'skill_id' => $this->skill_id,
            'name' => $this->whenLoaded('skill', fn () => $this->skill->name($locale)),
            'price_model' => $this->price_model,
            // Money is always {amount_minor, currency} — never a float (CLAUDE.md).
            'rate' => $this->rate_minor === null ? null : [
                'amount_minor' => $this->rate_minor,
                'currency' => $this->currency,
            ],
            'years_experience' => $this->years_experience,
        ];
    }
}
