<?php

declare(strict_types=1);

/**
 * P0-12 (web part): the public landing page is server-rendered and crawlable — a customer can
 * reach it without the app bundle — and it honours the requested language (doc 09).
 */
it('renders the public landing page server-side', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('HandyMan', escape: false)
        ->assertSee('<html', escape: false); // real HTML, not an app shell
});

it('falls back to French for an unsupported language', function () {
    // A browser advertising only German gets the app default (fr, the majority locale).
    $this->get('/', ['Accept-Language' => 'de-DE,de;q=0.9'])
        ->assertOk()
        ->assertSee('lang="fr"', escape: false)
        ->assertSee('Proposer des services', escape: false); // nav.provider (fr), no apostrophe
});

it('serves French to a French browser', function () {
    $this->get('/', ['Accept-Language' => 'fr-CM,fr;q=0.9'])
        ->assertOk()
        ->assertSee('lang="fr"', escape: false);
});

it('honours ?lang=en', function () {
    $this->get('/?lang=en')
        ->assertOk()
        ->assertSee('Find help', escape: false)       // nav.customer (en)
        ->assertSee('lang="en"', escape: false);
});

it('honours the Accept-Language header', function () {
    $this->get('/', ['Accept-Language' => 'en-US,en;q=0.9'])
        ->assertOk()
        ->assertSee('Offer services', escape: false); // nav.provider (en)
});
