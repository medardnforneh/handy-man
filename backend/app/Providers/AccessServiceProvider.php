<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Facts\Fact;
use App\Domain\Access\Facts\FactDeriver;
use App\Domain\Access\Facts\FactResult;
use App\Models\ProviderProfile;
use App\Models\ProviderSkill;
use App\Models\User;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the doc-10 access model. The {@see FactDeriver} is a singleton so fact resolvers
 * registered here (and, in tests, on the resolved instance) persist for the request.
 *
 * As Phase 1/6 land the underlying tables, register the REAL fact resolvers in
 * {@see registerFactResolvers()} — e.g. identity_verified from verification_documents,
 * has_payout_method from a confirmed MoMo number, skill_listed from provider_skills. Until then a
 * fact with no resolver derives to `unmet`, which is the correct, safe default.
 */
final class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FactDeriver::class, fn (): FactDeriver => new FactDeriver);
    }

    public function boot(): void
    {
        $this->registerFactResolvers($this->app->make(FactDeriver::class));
    }

    private function registerFactResolvers(FactDeriver $facts): void
    {
        // has_provider_profile — you "become" a provider by having a profile (doc 10, P1-08).
        $facts->register(Fact::HasProviderProfile, function (User $user): FactResult {
            return ProviderProfile::query()->where('party_id', $user->party_id)->exists()
                ? FactResult::met()
                : FactResult::unmet();
        });

        // skill_listed — at least one provider_skill exists (P1-08).
        $facts->register(Fact::SkillListed, function (User $user): FactResult {
            $listed = ProviderSkill::query()
                ->whereHas('providerProfile', fn ($q) => $q->where('party_id', $user->party_id))
                ->exists();

            return $listed ? FactResult::met() : FactResult::unmet();
        });

        // identity_verified — the STRONGER of: the "lighter check" (a confirmed phone → tier 1,
        // doc 10's remote requirement) and the provider profile's ID verification tier (2..3, the
        // on-site requirement; the approval flow that raises it lands in Phase 6).
        $facts->register(Fact::IdentityVerified, function (User $user): FactResult {
            $idTier = (int) (ProviderProfile::query()->where('party_id', $user->party_id)->value('verification_tier') ?? 0);
            $phoneTier = $user->phone_verified_at !== null ? 1 : 0;

            return FactResult::tier(max($idTier, $phoneTier));
        });
    }
}
