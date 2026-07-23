<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Safety\SafetyAlertKind;
use App\Models\SafetyAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafetyAlert>
 */
final class SafetyAlertFactory extends Factory
{
    protected $model = SafetyAlert::class;

    /**
     * @return array<model-property<SafetyAlert>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => SafetyAlertKind::Panic->value,
            'status' => 'open',
            'created_at' => now(),
        ];
    }
}
