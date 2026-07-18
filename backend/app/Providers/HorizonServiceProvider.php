<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Who can reach the Horizon dashboard in non-local environments.
     *
     * Deny-by-default: the queue dashboard exposes job payloads and must never be world-readable.
     * Access is granted only to an explicit staff allowlist (config `horizon.dashboard_emails`,
     * comma-separated env `HORIZON_DASHBOARD_EMAILS`) until real staff roles land in P1-09, at
     * which point this switches to a `staff`/`superadmin` role check (Spatie, admin-only).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user): bool {
            if ($user === null) {
                return false;
            }

            return in_array($user->email, $this->staffEmails(), true);
        });
    }

    /**
     * @return array<int, string>
     */
    private function staffEmails(): array
    {
        $raw = (string) config('horizon.dashboard_emails', '');

        return collect(explode(',', $raw))
            ->map(fn (string $e): string => trim($e))
            ->filter()
            ->values()
            ->all();
    }
}
