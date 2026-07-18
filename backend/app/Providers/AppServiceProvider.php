<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Reference\Policies\NotePolicy;
use App\Models\Note;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Domain policies live under their module (not App\Policies), so Laravel's policy
     * auto-discovery can't find them. Bind them explicitly here — one line per policy.
     *
     * @var array<class-string, class-string>
     */
    private const POLICIES = [
        Note::class => NotePolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
