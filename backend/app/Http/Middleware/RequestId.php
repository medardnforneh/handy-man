<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * One id per request, in three places at once: the `X-Request-Id` response header, every log line
 * written while handling it (via {@see Context}, which Laravel folds into each record — and which
 * Sentry attaches to its events), and the `trace_id` of any problem+json the request produces.
 *
 * `Problem` had promised "the id support can search in logs" from the first commit and minted a
 * random UUID per response that appeared nowhere else. A person could read the id off their
 * screen and nobody could do anything with it. Now the same string is on the wire, in the logs
 * and in the error report.
 *
 * An inbound `X-Request-Id` is honoured when it looks like one (the edge or a retrying client
 * may set it) so a retry chain shares an id; anything else is replaced, never trusted into logs.
 */
final class RequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $inbound = (string) $request->headers->get(self::HEADER, '');
        $id = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $inbound) === 1 ? $inbound : (string) Str::uuid();

        $request->headers->set(self::HEADER, $id);
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
