<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Consent\ConsentGuard;
use App\Models\Address;
use App\Models\User;
use MatanYadaev\EloquentSpatial\Enums\Srid;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * Creates an address for the user's party (build plan P1-06). Writing a user's geo location is
 * gated on `location_tracking` consent (doc 04) — revoking it blocks the write.
 *
 * @phpstan-type AddressData array{label?: ?string, line1: string, quarter?: ?string, city: string, region?: ?string, country_code?: ?string, landmark_note?: ?string}
 */
final class CreateAddress
{
    public function __construct(
        private readonly ConsentGuard $consent,
    ) {}

    /**
     * @param  AddressData  $data
     */
    public function handle(User $user, array $data, float $latitude, float $longitude): Address
    {
        $this->consent->assertGranted($user, 'location_tracking');

        return Address::query()->create([
            'party_id' => $user->party_id,
            'label' => $data['label'] ?? null,
            'line1' => $data['line1'],
            'quarter' => $data['quarter'] ?? null,
            'city' => $data['city'],
            'region' => $data['region'] ?? null,
            'country_code' => $data['country_code'] ?? 'CM',
            'point' => new Point($latitude, $longitude, Srid::WGS84->value),
            'landmark_note' => $data['landmark_note'] ?? null,
        ]);
    }
}
