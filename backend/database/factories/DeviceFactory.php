<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
final class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<model-property<Device>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => 'android',
            'push_token' => 'fcm_'.fake()->unique()->sha256(),
            'app_version' => '1.0.0',
            'last_seen_at' => now(),
            'created_at' => now(),
        ];
    }
}
