<?php

declare(strict_types=1);

/**
 * P0-08 acceptance: /api/v1 scaffold, app-version header, force-update kill switch (426),
 * and RFC 7807 problem+json error shape.
 */
it('serves the v1 meta endpoint', function () {
    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonStructure(['api_version', 'min_app_version', 'server_time'])
        ->assertJsonPath('api_version', 'v1');
});

it('rejects an app build older than the minimum with 426 Upgrade Required', function () {
    config()->set('api.min_app_version', '1.4.0');

    $response = $this->getJson('/api/v1/meta', ['X-App-Version' => '1.3.9']);

    $response->assertStatus(426)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', 426)
        ->assertJsonPath('min_app_version', '1.4.0')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'trace_id']);

    expect($response->json('type'))->toContain('upgrade-required');
});

it('allows a current or newer app build through', function () {
    config()->set('api.min_app_version', '1.4.0');

    $this->getJson('/api/v1/meta', ['X-App-Version' => '1.4.0'])->assertOk();
    $this->getJson('/api/v1/meta', ['X-App-Version' => '2.0.1'])->assertOk();
});

it('does not gate requests that send no app-version header', function () {
    config()->set('api.min_app_version', '99.0.0');

    // No header → cannot identify an outdated build → not gated.
    $this->getJson('/api/v1/meta')->assertOk();
});

it('returns problem+json for an unknown api route', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'trace_id']);
});

it('answers an unauthenticated API request with 401 problem+json even without an Accept header', function () {
    // `getJson` sets Accept: application/json, which is the path that always worked. A client that
    // omits it — curl, a proxy that strips it, an older HTTP library — used to hit Laravel's default
    // guest redirect to `route('login')`, a route this app has never defined, and got a **500** for
    // what is only an expired token. The RFC 7807 renderer never saw the exception at all.
    $this->get('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://errors.handyman.cm/unauthenticated')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'trace_id']);
});
