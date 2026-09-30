<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\AccountNotActive;
use App\Domain\Access\AccountStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a request carrying a valid token for an account that may no longer hold one.
 *
 * Suspending someone at the door is not enough. A suspension has to reach the sessions that are
 * already open: {@see \App\Domain\Access\Actions\SetAccountStatus} revokes the refresh families and
 * deletes the access tokens, and this is the belt to that braces — a token issued a moment before
 * the suspension, or one the revocation sweep somehow missed, dies here instead of working for its
 * remaining fifteen minutes.
 *
 * It resolves the user off the `sanctum` guard BY NAME, for the same reason `RecordUsage` and the
 * idempotency guard do: this sits in the `api` group, which runs before the route's own
 * `auth:sanctum`, and the default guard is `web` (session) — which a Bearer client never satisfies.
 * Asking `$request->user()` here would return null for every app request and the check would pass
 * everyone.
 *
 * An UNauthenticated request falls straight through. Deciding whether a route needs authentication
 * is `auth:sanctum`'s job, and answering 403 here for a public endpoint would break the whole
 * public half of the API.
 */
final class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();

        if ($user instanceof User) {
            $status = AccountStatus::from($user->status);

            if (! $status->canAuthenticate()) {
                throw new AccountNotActive($status);
            }
        }

        return $next($request);
    }
}
