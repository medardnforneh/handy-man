<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Address;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;
use MatanYadaev\EloquentSpatial\Enums\Srid;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * @extends Factory<Address>
 *
 * Points scatter around Yaoundé so seeded data is geographically realistic (dispatch ranking
 * tested against uniformly random points is not tested — build plan testing floor).
 */
final class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * @return array<model-property<Address>, mixed>
     */
    public function definition(): array
    {
        // Yaoundé centre ~ (3.848, 11.502); jitter within roughly the city.
        $lat = 3.848 + (fake()->randomFloat(5, -0.08, 0.08));
        $lng = 11.502 + (fake()->randomFloat(5, -0.08, 0.08));

        return [
            'party_id' => Party::factory()->individual(),
            'label' => fake()->randomElement(['Maison', 'Bureau', 'Chantier']),
            'line1' => fake()->streetAddress(),
            'quarter' => fake()->randomElement(['Bastos', 'Mvog-Mbi', 'Nsam', 'Biyem-Assi', 'Mokolo']),
            'city' => 'Yaoundé',
            'region' => 'Centre',
            'country_code' => 'CM',
            'point' => new Point($lat, $lng, Srid::WGS84->value),
            'landmark_note' => fake()->optional()->sentence(),
        ];
    }

    public function at(float $latitude, float $longitude): static
    {
        return $this->state(fn () => ['point' => new Point($latitude, $longitude, Srid::WGS84->value)]);
    }
}
