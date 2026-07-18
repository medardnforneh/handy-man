<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Skill>
 */
final class SkillFactory extends Factory
{
    protected $model = Skill::class;

    /**
     * @return array<model-property<Skill>, mixed>
     */
    public function definition(): array
    {
        $fr = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($fr).'-'.fake()->unique()->numberBetween(1, 99999),
            'name_fr' => ucfirst($fr),
            'name_en' => ucfirst(fake()->words(2, true)),
            'is_leaf' => true,
            'requires_license' => false,
            'risk_tier' => 1,
        ];
    }

    public function category(): static
    {
        return $this->state(fn () => ['is_leaf' => false, 'parent_id' => null]);
    }
}
