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
