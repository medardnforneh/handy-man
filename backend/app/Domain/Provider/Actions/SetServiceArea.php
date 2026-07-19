<?php

declare(strict_types=1);

namespace App\Domain\Provider\Actions;

use App\Domain\Identity\Consent\ConsentGuard;
use App\Models\ProviderProfile;
use App\Models\ServiceArea;
use App\Models\User;
use MatanYadaev\EloquentSpatial\Enums\Srid;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * Sets a service area — where the provider works (build plan P1-08). Writing a geo location requires
 * `location_tracking` consent (doc 04).
 */
final class SetServiceArea
{
    public function __construct(
        private readonly ConsentGuard $consent,
    ) {}

    public function handle(User $user, float $latitude, float $longitude, int $radiusMeters): ServiceArea
    {
        $this->consent->assertGranted($user, 'location_tracking');

        $profile = ProviderProfile::query()->where('party_id', $user->party_id)->firstOrFail();

        return ServiceArea::query()->create([
            'provider_profile_id' => $profile->id,
            'center' => new Point($latitude, $longitude, Srid::WGS84->value),
            'radius_m' => $radiusMeters,
            'created_at' => now(),
        ]);
    }
}
