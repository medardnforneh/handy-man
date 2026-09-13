<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Builds RFC 7807 `application/problem+json` responses — the single error shape for the whole
 * API (see CLAUDE.md "API conventions"). Every error carries:
 *   - `type`   a stable, machine-readable URI slug clients switch on (never localise this),
 *   - `title`  a short human summary,
 *   - `status` the HTTP status,
 *   - `detail` a human-readable, localisable explanation,
 *   - `trace_id` the id support can search in logs.
 *
 * Additional members (e.g. `errors`, `missing_fact`, `resolve`) may be merged in via $extra.
 */
final class Problem
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public static function make(
        string $type,
        string $title,
        int $status,
        string $detail,
        array $extra = [],
        ?string $traceId = null,
    ): JsonResponse {
        $base = rtrim((string) config('api.problem_type_base'), '/');

        $body = array_merge([
            'type' => str_starts_with($type, 'http') ? $type : "{$base}/{$type}",
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            // The request's id (RequestId middleware): the same string is on the response header and
            // on every log line this request wrote, so support can actually search for it.
            'trace_id' => $traceId ?? Context::get('request_id') ?? (string) Str::uuid(),
        ], $extra);

        return new JsonResponse($body, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
