<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gzip for text responses, from the application itself.
 *
 * The public pages are the one surface a customer reaches before any bundle loads, and the launch
 * checklist holds them to Lighthouse ≥ 90 on a throttled 3G profile. The home page is ~77 KB of
 * HTML — inline icons, twenty-six trade cards — which at 700 kbps is the whole of the first
 * paint. Compressed it is ~15 KB. A web server in front of PHP would normally do this, but the
 * product is not yet deployed, and a guarantee that lives in nginx config is only as real as the
 * host it is on. This makes it the application's: measurable on `artisan serve`, true on any host,
 * and harmless behind one that compresses too (a response already carrying Content-Encoding is
 * left alone, and a proxy will not re-encode an encoded body).
 *
 * Only text (HTML, CSS, JS, JSON, SVG, XML) above a small floor; never file downloads or streams,
 * whose bodies are not in memory to encode. Level 6 is the usual balance of ratio and CPU.
 */
final class CompressResponse
{
    private const MIN_BYTES = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->wants($request) || ! $this->compressible($response)) {
            return $response;
        }

        $body = $response->getContent();
        if ($body === false || strlen($body) < self::MIN_BYTES) {
            return $response;
        }

        $encoded = gzencode($body, 6);
        if ($encoded === false) {
            return $response;
        }

        $response->setContent($encoded);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($encoded));
        // Caches must keep the encoded and plain bodies apart.
        $vary = $response->headers->get('Vary');
        $response->headers->set('Vary', $vary === null ? 'Accept-Encoding' : "{$vary}, Accept-Encoding");

        return $response;
    }

    private function wants(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept-Encoding', '')), 'gzip');
    }

    private function compressible(Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }
        if ($response->headers->has('Content-Encoding') || $response->getStatusCode() === 204) {
            return false;
        }

        $type = strtolower((string) $response->headers->get('Content-Type', ''));

        return str_starts_with($type, 'text/')
            || str_contains($type, 'json')
            || str_contains($type, 'javascript')
            || str_contains($type, 'xml')
            || str_contains($type, 'svg');
    }
}
