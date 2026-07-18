<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Facts\FactDeriver;
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
        // Phase 1/6 register real resolvers here, e.g.:
        //
        // $facts->register(Fact::IdentityVerified, function (User $user): FactResult {
        //     return FactResult::tier($user->verification_tier ?? 0);
        // });
        //
        // Left empty in Phase 0: every fact safely derives to `unmet` until proven.
    }
}
