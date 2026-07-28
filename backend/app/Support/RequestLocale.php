<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;

/**
 * Which language a bilingual API payload should speak (doc 09). Mirrors {@see SetLocale}'s
 * documented precedence — explicit `?locale=`, then the authenticated user's stored preference,
 * then Accept-Language, then the app default.
 *
 * This exists as a helper rather than reusing the middleware because SetLocale is registered on the
 * WEB group only, and even on the api group it would run before route middleware — so
 * `$request->user()` would still be null there and the user's stored locale could never win. Inside
 * a Resource the user IS resolved, so the precedence can actually be honoured.
 */
final class RequestLocale
{
    public const SUPPORTED = ['fr', 'en'];

    public static function for(Request $request): string
    {
        $query = $request->query('locale');
        if (is_string($query) && in_array($query, self::SUPPORTED, true)) {
            return $query;
        }

        $userLocale = $request->user()?->getAttribute('locale');
        if (is_string($userLocale) && in_array($userLocale, self::SUPPORTED, true)) {
            return $userLocale;
        }

        foreach ($request->getLanguages() as $language) {
            $short = strtolower(substr((string) $language, 0, 2));
            if (in_array($short, self::SUPPORTED, true)) {
                return $short;
            }
        }

        return (string) config('app.locale');
    }
}
