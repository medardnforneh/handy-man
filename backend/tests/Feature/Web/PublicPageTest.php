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

/**
 * These three assert on a phrase that only appears in one language, because that is the only way to
 * prove the locale actually resolved rather than the page merely rendering. They used to key off
 * `nav.customer` / `nav.provider`, which were headings on the old two-box landing page; the page was
 * redesigned, so they now key off the hero call to action — copy that is load-bearing on the new
 * page and therefore unlikely to vanish silently. Deliberately chosen without apostrophes, since
 * Blade escapes those and `assertSee(escape: false)` would then miss them.
 */
it('falls back to French for an unsupported language', function () {
    // A browser advertising only German gets the app default (fr, the majority locale).
    $this->get('/', ['Accept-Language' => 'de-DE,de;q=0.9'])
        ->assertOk()
        ->assertSee('lang="fr"', escape: false)
        ->assertSee('Proposer mes services', escape: false); // public.hero_cta_secondary (fr)
});

it('serves French to a French browser', function () {
    $this->get('/', ['Accept-Language' => 'fr-CM,fr;q=0.9'])
        ->assertOk()
        ->assertSee('lang="fr"', escape: false);
});

it('honours ?lang=en', function () {
    $this->get('/?lang=en')
        ->assertOk()
        ->assertSee('Find a professional', escape: false) // public.hero_cta_primary (en)
        ->assertSee('lang="en"', escape: false);
});

it('honours the Accept-Language header', function () {
    $this->get('/', ['Accept-Language' => 'en-US,en;q=0.9'])
        ->assertOk()
        ->assertSee('Offer your services', escape: false); // public.hero_cta_secondary (en)
});
