<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use App\Support\Problem;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Idempotency middleware (build plan P0-06, CLAUDE.md rule #3).
 *
 * Protocol for a mutating request carrying `Idempotency-Key`:
 *   1. Atomically claim the key by INSERTing a `processing` row. The unique index makes exactly
 *      one of N racing requests win — that is the concurrency lock, no advisory lock needed.
 *   2. The winner executes the request, stores the response, marks the row `completed`.
 *   3. A replay of a completed key returns the stored response verbatim + `Idempotency-Replayed`.
 *   4. A replay while the first is still in flight → 409 (retry shortly).
 *   5. The same key with a DIFFERENT request body → 422 (the client is misusing the key).
 *
 * Transient failures (5xx or a thrown exception) RELEASE the claim so a retry can succeed;
 * deterministic responses (2xx–4xx) are stored and replayed.
 *
 * A claim belongs to the CALLER, not to the key alone (see the
 * `scope_idempotency_keys_to_user` migration): the unique index is `(user_id, idempotency_key)`,
 * and every lookup here carries the same user, so one caller's key can never replay another
 * caller's stored response.
 *
 * The user is read off the `sanctum` guard explicitly. This middleware is in the `api` GROUP, so it
 * runs before the route's own `auth:sanctum` — and the default guard is `web` (session), which a
 * Bearer-only mobile client never satisfies. `$request->user()` therefore returned null for every
 * app request, which is why `user_id` was recorded as null on every row this table ever held.
 * Same reason `RecordUsage` names its guard.
 */
final class Idempotency
{
    private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::MUTATING, true)) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        $header = (string) config('api.idempotency.header');
        $key = $request->header($header);

        if (! is_string($key) || trim($key) === '') {
            if (config('api.idempotency.require_on_mutations')) {
                return Problem::make(
                    type: 'idempotency-key-required',
                    title: 'Idempotency-Key required',
                    status: Response::HTTP_BAD_REQUEST,
                    detail: "Mutating requests must include an {$header} header.",
                );
            }

            return $next($request);
        }

        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());
        $identifier = Auth::guard('sanctum')->user()?->getAuthIdentifier();
        $userId = $identifier === null ? null : (string) $identifier;

        // Atomic claim via INSERT ... ON CONFLICT DO NOTHING. Exactly one racing request inserts;
        // the rest get 0 rows and fall through to replay/conflict. This never raises, so it is
        // safe even if the caller happens to be inside an outer DB transaction (a caught unique
        // violation would otherwise poison that transaction).
        $claimed = IdempotencyKey::query()->insertOrIgnore([
            'idempotency_key' => $key,
            'user_id' => $userId,
            'request_method' => $request->method(),
            'request_path' => $request->path(),
            'request_hash' => $hash,
            'status' => IdempotencyKey::STATUS_PROCESSING,
            'created_at' => now(),
            'expires_at' => now()->addHours((int) config('api.idempotency.ttl_hours')),
        ]);

        if ($claimed === 0) {
            return $this->handleExisting($key, $userId, $hash, $header);
        }

        $record = $this->claim($key, $userId)->firstOrFail();

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // Nothing durable was produced — release the claim so the client can retry.
            $record->delete();

            throw $e;
        }

        if ($response->getStatusCode() >= 500) {
            // Transient server error: don't freeze it against the key; allow a retry.
            $record->delete();

            return $response;
        }

        $this->store($record, $response);
        $response->headers->set($header, $key);

        return $response;
    }

    /**
     * This caller's claim on this key. Both halves of the unique index, always — a lookup by key
     * alone would reach across callers, which is the whole thing the index now prevents.
     *
     * @return Builder<IdempotencyKey>
     */
    private function claim(string $key, ?string $userId): Builder
    {
        $query = IdempotencyKey::query()->where('idempotency_key', $key);

        return $userId === null
            ? $query->whereNull('user_id')
            : $query->where('user_id', $userId);
    }

    private function handleExisting(string $key, ?string $userId, string $hash, string $header): Response
    {
        $existing = $this->claim($key, $userId)->first();

        if ($existing === null) {
            // The claim was released between our failed insert and this read (transient failure).
            return Problem::make(
                type: 'idempotency-conflict',
                title: 'Idempotency conflict',
                status: Response::HTTP_CONFLICT,
                detail: 'A previous request with this key did not complete. Please retry.',
            );
        }

        if ($existing->request_hash !== $hash) {
            return Problem::make(
                type: 'idempotency-key-reused',
                title: 'Idempotency-Key reused for a different request',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                detail: 'This Idempotency-Key was already used for a different request payload.',
            );
        }

        if (! $existing->isCompleted()) {
            return Problem::make(
                type: 'idempotency-conflict',
                title: 'Request already in progress',
                status: Response::HTTP_CONFLICT,
                detail: 'An identical request is still being processed. Please retry shortly.',
            );
        }

        return $this->replay($existing, $header, $key);
    }

    private function replay(IdempotencyKey $record, string $header, string $key): Response
    {
        $response = new Response(
            $record->response_body ?? '',
            $record->response_status ?? Response::HTTP_OK,
            $record->response_headers ?? [],
        );

        $response->headers->set('Idempotency-Replayed', 'true');
        $response->headers->set($header, $key);

        return $response;
    }

    private function store(IdempotencyKey $record, Response $response): void
    {
        $record->response_status = $response->getStatusCode();
        $record->response_headers = $this->replayableHeaders($response);
        $record->response_body = $response->getContent() === false ? null : $response->getContent();
        $record->status = IdempotencyKey::STATUS_COMPLETED;
        $record->completed_at = now();
        $record->save();
    }

    /**
     * Keep only headers that are safe and meaningful to replay. Drop hop-by-hop and per-response
     * headers that must not be reused (Date, Set-Cookie, transfer framing).
     *
     * @return array<string, array<int, string|null>>
     */
    private function replayableHeaders(Response $response): array
    {
        $drop = ['date', 'set-cookie', 'transfer-encoding', 'connection', 'content-length'];

        return collect($response->headers->all())
            ->reject(fn ($_, string $name) => in_array(strtolower($name), $drop, true))
            ->all();
    }

    private function isExempt(Request $request): bool
    {
        foreach ((array) config('api.idempotency.exempt_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
