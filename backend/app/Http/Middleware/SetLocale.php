<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the UI language for web (Blade) requests (doc 09). Precedence:
 *   1. an explicit ?lang=fr|en (lets a link force a language),
 *   2. the authenticated user's stored locale (added in P1),
 *   3. the Accept-Language header,
 *   4. the app default (en), which is only reached when nothing above matched.
 *
 * Only fr/en are supported; anything else falls back to the default.
 */
final class SetLocale
{
    private const SUPPORTED = ['fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);
        App::setLocale($locale);

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $query = $request->query('lang');
        if (is_string($query) && in_array($query, self::SUPPORTED, true)) {
            return $query;
        }

        // `users.locale` lands in P1-05b; read defensively so this works before that migration.
        $userLocale = $request->user()?->getAttribute('locale');
        if (is_string($userLocale) && in_array($userLocale, self::SUPPORTED, true)) {
            return $userLocale;
        }

        // Walk the client's languages in preference order; take the first supported one. When no
        // Accept-Language is sent, getLanguages() is empty and we fall back to the app default.
        foreach ($request->getLanguages() as $language) {
            $short = strtolower(substr((string) $language, 0, 2));
            if (in_array($short, self::SUPPORTED, true)) {
                return $short;
            }
        }

        return (string) config('app.locale');
    }
}
