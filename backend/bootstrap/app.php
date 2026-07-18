<?php

use App\Domain\Access\PreconditionUnmetException;
use App\Domain\Identity\OtpException;
use App\Http\Middleware\EnforceAppVersion;
use App\Http\Middleware\Idempotency;
use App\Http\Middleware\SetLocale;
use App\Support\Problem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        // Force-update kill switch runs first on every API request (build plan P0-08); the
        // idempotency guard wraps mutating requests (P0-06, CLAUDE.md rule #3).
        $middleware->api(
            prepend: [EnforceAppVersion::class],
            append: [Idempotency::class],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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

        $exceptions->render(function (OtpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return Problem::make(
                type: $e->problemType,
                title: $e->title,
                status: $e->status,
                detail: $e->getMessage(),
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
