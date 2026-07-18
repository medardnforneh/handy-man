<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Problem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force-update kill switch (build plan P0-08).
 *
 * The mobile app sends `X-App-Version: MAJOR.MINOR.PATCH` on every request. If the build is older
 * than `config('api.min_app_version')`, we refuse with 426 Upgrade Required so a shipped build
 * with a broken or unsafe contract can be shut off centrally.
 *
 * Requests without the header (e.g. server-to-server, the Blade web surface) are not gated — the
 * gate only fires when we can positively identify an outdated build.
 */
final class EnforceAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $min = config('api.min_app_version');
        $header = (string) config('api.app_version_header');
        $sent = $request->header($header);

        if ($min !== null && is_string($sent) && $this->isValidSemver($sent)) {
            if (version_compare($sent, (string) $min, '<')) {
                return Problem::make(
                    type: 'upgrade-required',
                    title: 'Upgrade required',
                    status: Response::HTTP_UPGRADE_REQUIRED, // 426
                    detail: "This app version ({$sent}) is no longer supported. Please update to continue.",
                    extra: [
                        'min_app_version' => $min,
                        'current_app_version' => $sent,
                    ],
                );
            }
        }

        return $next($request);
    }

    private function isValidSemver(string $value): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+$/', $value);
    }
}
