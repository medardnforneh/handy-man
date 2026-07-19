<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProviderProfile;
use App\Models\ServiceArea;
use Illuminate\Database\Eloquent\Factories\Factory;
use MatanYadaev\EloquentSpatial\Enums\Srid;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * @extends Factory<ServiceArea>
 */
final class ServiceAreaFactory extends Factory
{
    protected $model = ServiceArea::class;

    /**
     * @return array<model-property<ServiceArea>, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_profile_id' => ProviderProfile::factory(),
            'center' => new Point(3.848, 11.502, Srid::WGS84->value), // Yaoundé centre
            'radius_m' => 5000,
            'created_at' => now(),
        ];
    }

    public function at(float $latitude, float $longitude, int $radiusM = 5000): static
    {
        return $this->state(fn () => [
            'center' => new Point($latitude, $longitude, Srid::WGS84->value),
            'radius_m' => $radiusM,
        ]);
    }
}
