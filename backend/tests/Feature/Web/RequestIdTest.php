<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/**
 * One id per request, in three places (doc 11 "Monitoring"): the response header, every log line
 * the request wrote, and the problem+json `trace_id` — the promise `Problem` had made since its
 * first commit and never kept (it minted a UUID that appeared nowhere else).
 */
it('puts the same id on the response, in the logs and in a problem+json', function () {
    Route::middleware('api')->prefix('api')->get('/_request-id', function () {
        Log::info('inside the request');
        abort(418, 'short and stout');
    });
    // The line as it is WRITTEN — Context rides a Monolog processor, not the log() call's context.
    $file = tempnam(sys_get_temp_dir(), 'log');
    config()->set('logging.channels.probe', ['driver' => 'single', 'path' => $file]);
    config()->set('logging.default', 'probe');

    $response = $this->getJson('/api/_request-id');

    $id = (string) $response->headers->get('X-Request-Id');
    $line = (string) file_get_contents($file);
    expect($id)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($response->json('trace_id'))->toBe($id)
        ->and($line)->toContain('inside the request')
        ->and($line)->toContain("\"request_id\":\"{$id}\"");
});

it('keeps a well-formed inbound id so a retry chain shares one, and replaces anything else', function () {
    Sanctum::actingAs(User::factory()->create());

    expect($this->getJson('/api/v1/me', ['X-Request-Id' => 'edge-7f3a9c1e2b'])->headers->get('X-Request-Id'))->toBe('edge-7f3a9c1e2b')
        ->and($this->getJson('/api/v1/me', ['X-Request-Id' => "<script>alert(1)</script>\n"])->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/');
});

it('covers the public site and the admin, not only the API', function () {
    expect($this->get('/')->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/');
});
