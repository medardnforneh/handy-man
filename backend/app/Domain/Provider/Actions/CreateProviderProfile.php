<?php

declare(strict_types=1);

namespace App\Domain\Provider\Actions;

use App\Domain\Access\Facts\Fact;
use App\Domain\Access\Facts\FactDeriver;
use App\Models\ProviderProfile;
use App\Models\User;

/**
 * Creates (or updates) the user's provider profile (build plan P1-08). ALWAYS ALLOWED — this is how
 * you start being a provider (doc 10); there is no grant or approval step. Creating it flips the
 * `has_provider_profile` fact, so its cache is invalidated.
 *
 * @phpstan-type ProfileData array{headline?: ?string, bio?: ?string, bio_language?: ?string}
 */
final class CreateProviderProfile
{
    public function __construct(
        private readonly FactDeriver $facts,
    ) {}

    /**
     * @param  ProfileData  $data
     */
    public function handle(User $user, array $data): ProviderProfile
    {
        $profile = ProviderProfile::query()->updateOrCreate(
            ['party_id' => $user->party_id],
            [
                'headline' => $data['headline'] ?? null,
                'bio' => $data['bio'] ?? null,
                'bio_language' => $data['bio_language'] ?? $user->locale,
            ],
        );

        $this->facts->forget($user, Fact::HasProviderProfile);

        return $profile;
    }
}
