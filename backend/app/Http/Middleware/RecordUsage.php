<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\UsageDay;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records that a person used the API today, and from what (doc 08's switch trigger #2 — see the
 * `usage_days` migration for why this exists and why it is a day, not a request).
 *
 * Terminable: the row is written AFTER the response has gone out, so instrumentation never adds
 * to a request's latency on the networks this product is built for. Throttled through the cache
 * so a person's hundredth call of the day costs one cache read, not an upsert. Best effort — a
 * failure here is logged by the framework and changes nothing for the caller.
 *
 * The platform is what the client declares (`X-Client-Platform`: android / ios / web, sent by the
 * app from launch) with `web` split by user agent into a phone's browser and a desktop's, because
 * that is exactly the line the trigger is drawn on. A request with no declaration is a browser.
 */
final class RecordUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $user = Auth::guard('sanctum')->user();
        if ($user === null) {
            return;
        }

        $day = now()->toDateString();
        $platform = self::platformOf($request);
        $key = "usage:{$user->getKey()}:{$day}:{$platform}";

        if (Cache::has($key)) {
            return;
        }

        UsageDay::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'day' => $day, 'platform' => $platform],
            ['first_seen_at' => now()],
        );

        Cache::put($key, true, now()->endOfDay());
    }

    public static function platformOf(Request $request): string
    {
        $declared = strtolower(trim((string) $request->header('X-Client-Platform', '')));

        return match ($declared) {
            'android' => UsageDay::PLATFORM_ANDROID,
            'ios' => UsageDay::PLATFORM_IOS,
            default => self::isMobileBrowser($request) ? UsageDay::PLATFORM_WEB_MOBILE : UsageDay::PLATFORM_WEB_DESKTOP,
        };
    }

    /**
     * The coarse cut every analytics product makes: a handset's browser says "Mobile" (Android,
     * iPhone) or "Android"; a tablet is a large screen for this purpose and counts as desktop.
     */
    private static function isMobileBrowser(Request $request): bool
    {
        $ua = (string) $request->userAgent();

        return (bool) preg_match('/\bMobile\b|Android|iPhone|Opera Mini|KaiOS/i', $ua);
    }
}
