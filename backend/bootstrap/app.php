<?php

use App\Domain\Access\PreconditionUnmetException;
use App\Http\Middleware\CompressResponse;
use App\Http\Middleware\EnforceAppVersion;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\Idempotency;
use App\Http\Middleware\RecordUsage;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\SetLocale;
use App\Support\Problem;
use App\Support\ProblemAware;
use App\Support\ProvidesProblemExtras;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve fr/en for every Blade/web request (doc 09).
        $middleware->web(append: [SetLocale::class]);

        // Gzip every text response, on any host (the public pages' 3G budget — see the class).
        // Prepended, so it wraps everything and encodes the final body.
        $middleware->web(prepend: [CompressResponse::class]);
        $middleware->api(prepend: [CompressResponse::class]);

        // One id per request — on the response, on every log line, in every problem+json — for
        // every route on every host. Outermost, so even a failure inside another middleware is
        // logged under it.
        $middleware->prepend(RequestId::class);

        // Force-update kill switch runs first on every API request (build plan P0-08); the
        // idempotency guard wraps mutating requests (P0-06, CLAUDE.md rule #3).
        //
        // `throttle:api` is prepended AHEAD of both, because a limiter that runs after the work is
        // not a limiter. Laravel 11 took `throttle:api` out of the default API group and stopped
        // defining the limiter, and this file never put either back — so until now nothing in the
        // app bounded request rate anywhere. The limiter itself is defined in AppServiceProvider;
        // the stricter per-route limits (`auth`, `webhooks`) are named in routes/api.php.
        // EnsureAccountActive sits between the two: after the cheap gates, before anything a
        // suspended account could actually do. It reads the sanctum guard itself, so it does not
        // need to wait for the route's `auth:sanctum` (see the class).
        $middleware->api(
            prepend: ['throttle:api', EnforceAppVersion::class, EnsureAccountActive::class],
            // RecordUsage is terminable and writes after the response — doc 08's switch trigger.
            append: [Idempotency::class, RecordUsage::class],
        );

        // Never redirect a guest to a login page. Laravel's default sends anyone who fails `auth`
        // and does not "expect JSON" to `route('login')` — a route this app has never had, because
        // no web route is behind `auth` (Filament runs its own auth, the public pages are public).
        // The result was that an unauthenticated API request WITHOUT an `Accept: application/json`
        // header died with `Route [login] not defined` — a **500** for what is simply an expired
        // token, and the RFC 7807 renderer below never even saw it. Clients that omit the header
        // are ordinary (curl, a proxy that strips it, an older HTTP library). Returning null keeps
        // the AuthenticationException intact so it renders as the documented 401 (P0-08).
        $middleware->redirectGuestsTo(fn (Request $request) => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Unhandled exceptions go to Sentry when SENTRY_DSN is set (doc 11 "Monitoring"); without
        // a DSN the SDK is inert. Rendered problems below are not "unhandled" and are not reported.
        Integration::handles($exceptions);

        // Render every API error as RFC 7807 application/problem+json (CLAUDE.md API conventions).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return Problem::make(
                type: 'validation-failed',
                title: 'Validation failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                detail: 'One or more fields failed validation.',
                extra: ['errors' => $e->errors()],
            );
        });

        // A throttled request is an HttpException, so the generic handler below would already
        // render it — but it would drop `Retry-After` and the `X-RateLimit-*` headers the limiter
        // attached, and give it the catch-all `http-error` type. A client that cannot tell "slow
        // down, for this many seconds" from "something went wrong" retries immediately and makes
        // it worse, and the offline write queue is precisely such a client.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

            return Problem::make(
                type: 'rate-limited',
                title: 'Too many requests',
                status: Response::HTTP_TOO_MANY_REQUESTS,
                detail: 'Too many requests. Please slow down and try again shortly.',
                extra: array_filter(['retry_after_seconds' => $retryAfter === null ? null : (int) $retryAfter]),
            )->withHeaders($e->getHeaders());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return Problem::make(
                type: 'unauthenticated',
                title: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
                detail: 'Authentication is required to access this resource.',
            );
        });

        // doc 10: a fact-gated capability that isn't satisfied is NOT a 403 — it's a first-class,
        // machine-readable prompt naming the missing fact and a deep link to resolve it inline.
        $exceptions->render(function (PreconditionUnmetException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return Problem::make(
                type: 'precondition-unmet',
                title: 'Precondition unmet',
                status: Response::HTTP_CONFLICT, // 409
                detail: 'This action requires a verified fact that is not yet satisfied.',
                extra: array_filter([
                    'error' => 'precondition_unmet',
                    'capability' => $e->capability,
                    'missing_fact' => $e->missingFact->value,
                    'required_tier' => $e->requiredTier,
                    'resolve' => $e->resolve,
                ], fn ($v) => $v !== null),
            );
        });

        // Any domain exception that implements ProblemAware renders itself (OTP, refresh tokens,
        // consent, …). ProvidesProblemExtras contributes extra machine-readable members.
        $exceptions->render(function (ProblemAware $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return Problem::make(
                type: $e->problemType(),
                title: $e->problemTitle(),
                status: $e->problemStatus(),
                detail: $e instanceof Throwable ? $e->getMessage() : '',
                extra: $e instanceof ProvidesProblemExtras ? $e->problemExtras() : [],
            );
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return Problem::make(
                type: 'forbidden',
                title: 'Forbidden',
                status: Response::HTTP_FORBIDDEN,
                detail: $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.',
            );
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();

            return Problem::make(
                type: 'http-error',
                title: Response::$statusTexts[$status] ?? 'Error',
                status: $status,
                detail: $e->getMessage() !== '' ? $e->getMessage() : (Response::$statusTexts[$status] ?? 'Error'),
            );
        });
    })->create();
