<?php

declare(strict_types=1);

namespace App\Domain\Access\Capabilities;

use App\Domain\Access\Facts\Fact;
use App\Models\User;

/**
 * Listing a skill requires a provider profile (doc 10). If missing, the client is prompted inline
 * to create one first (a one-tap step) via the `precondition_unmet` deep link.
 */
final class ListSkill extends Capability
{
    public function key(): string
    {
        return 'list_skill';
    }

    public function authorize(User $user, array $context = []): void
    {
        $this->require(
            user: $user,
            fact: Fact::HasProviderProfile,
            resolve: ['type' => 'provider_profile', 'deep_link' => '/provider/profile'],
        );
    }
}
