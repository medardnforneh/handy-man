<?php

declare(strict_types=1);

namespace App\Domain\Access\Facts;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Derives {@see Fact}s about a user and caches them (doc 10: "Facts are derived and cached,
 * recomputed on the events that change them").
 *
 * Each fact has a registered resolver — a closure that computes a {@see FactResult} from the user
 * (and optional context, e.g. the acting organization). Phase 1/6 register the real resolvers as
 * the underlying tables land (verification_documents, provider_profiles, payout methods); until
 * then an unregistered fact derives to `unmet`, which is the correct default — nothing is trusted
 * that hasn't been proven.
 *
 * Invalidation is explicit: the event handler for "verification approved", "payout method added",
 * "skill listed" calls {@see forget()} for the affected user+fact, same discipline as the outbox.
 */
final class FactDeriver
{
    /** @var array<string, Closure(User, array<string, mixed>): FactResult> */
    private array $resolvers = [];

    private int $ttlSeconds;

    public function __construct(?int $ttlSeconds = null)
    {
        $this->ttlSeconds = $ttlSeconds ?? (int) config('access.fact_cache_ttl', 300);
    }

    /**
     * Register how a fact is computed. Later registration overrides earlier (tests override).
     *
     * @param  Closure(User, array<string, mixed>): FactResult  $resolver
     */
    public function register(Fact $fact, Closure $resolver): void
    {
        $this->resolvers[$fact->value] = $resolver;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function derive(User $user, Fact $fact, array $context = []): FactResult
    {
        return Cache::remember(
            $this->cacheKey($user, $fact, $context),
            $this->ttlSeconds,
            fn (): FactResult => $this->compute($user, $fact, $context),
        );
    }

    /**
     * Drop the cached value for a user+fact so the next derive recomputes. Call this from the
     * event handler for whatever changed the fact.
     */
    public function forget(User $user, Fact $fact): void
    {
        // Context-specific variants share the prefix; for the common context-free facts this is
        // an exact key. Context-bearing facts should be re-derived rather than relied upon stale.
        Cache::forget($this->cacheKey($user, $fact, []));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function compute(User $user, Fact $fact, array $context): FactResult
    {
        $resolver = $this->resolvers[$fact->value] ?? null;

        if ($resolver === null) {
            return FactResult::unmet();
        }

        return $resolver($user, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function cacheKey(User $user, Fact $fact, array $context): string
    {
        $suffix = $context === [] ? '' : ':'.md5(json_encode($context, JSON_THROW_ON_ERROR));

        return "facts:{$user->getKey()}:{$fact->value}{$suffix}";
    }
}
