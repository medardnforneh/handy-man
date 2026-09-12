<?php

declare(strict_types=1);

/**
 * The public pages' 3G budget (launch checklist, doc 05: Lighthouse ≥ 90 on a throttled 3G profile)
 * depends on the HTML being compressed, and that guarantee is the application's, not the host's.
 */
it('gzips the home page for a client that accepts it', function () {
    $response = $this->get('/', ['Accept-Encoding' => 'gzip, deflate, br']);

    $response->assertOk()
        ->assertHeader('Content-Encoding', 'gzip')
        ->assertHeader('Vary', 'Accept-Encoding');

    $encoded = $response->getContent();
    $plain = gzdecode($encoded);
    expect($plain)->toBeString()
        ->and(strlen($encoded))->toBeLessThan(strlen($plain) / 3)
        ->and($plain)->toContain('<h1');
});

it('leaves the body alone when the client does not accept gzip', function () {
    $response = $this->get('/');

    $response->assertOk()->assertHeaderMissing('Content-Encoding');
    expect($response->getContent())->toContain('<h1');
});

it('compresses JSON from the API too, once it is worth the CPU', function () {
    // /meta is a few hundred bytes: below the floor, sent as is — encoding it would cost more
    // than it saves. The skills catalogue is the public taxonomy and always clears the floor.
    $this->getJson('/api/v1/meta', ['Accept-Encoding' => 'gzip'])
        ->assertOk()
        ->assertHeaderMissing('Content-Encoding');

    $this->seed(\Database\Seeders\SkillsSeeder::class);
    $response = $this->getJson('/api/v1/skills', ['Accept-Encoding' => 'gzip']);

    $response->assertOk()->assertHeader('Content-Encoding', 'gzip');
    expect(json_decode((string) gzdecode($response->getContent()), true))->toBeArray();
});
