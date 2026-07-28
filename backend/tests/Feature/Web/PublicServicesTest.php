<?php

declare(strict_types=1);

use App\Models\Party;
use App\Models\ProviderProfile;
use App\Models\ProviderSkill;
use App\Models\Skill;

/**
 * The crawlable services directory (doc 08). These pages exist so a customer — and a crawler —
 * can find a trade and see who offers it without loading the app bundle. The marquee guarantee is
 * the PII one: every visitor here is anonymous and pre-engagement, so a provider's display name
 * must never appear, exactly as in the API's match list.
 *
 * @return array{category: Skill, leaf: Skill}
 */
function taxonomyPair(): array
{
    $category = Skill::factory()->create([
        'name_en' => 'Plumbing', 'name_fr' => 'Plomberie', 'slug' => 'plumbing', 'is_leaf' => false,
    ]);
    $leaf = Skill::factory()->create([
        'name_en' => 'Leak repair', 'name_fr' => 'Réparation de fuite', 'slug' => 'leak-repair',
        'is_leaf' => true, 'parent_id' => $category->id,
    ]);

    return ['category' => $category, 'leaf' => $leaf];
}

it('lists every category with its leaves, in the requested language', function () {
    taxonomyPair();

    $this->get('/services?lang=en')
        ->assertOk()
        ->assertSee('Plumbing', escape: false)
        ->assertSee('Leak repair', escape: false);

    $this->get('/services?lang=fr')
        ->assertOk()
        ->assertSee('Plomberie', escape: false)
        ->assertSee('Réparation de fuite', escape: false);
});

it('shows a leaf’s providers WITHOUT ever exposing a display name', function () {
    ['leaf' => $leaf] = taxonomyPair();

    $party = Party::factory()->individual()->create(['display_name' => 'Jean-Paul Mbarga']);
    $profile = ProviderProfile::factory()->create([
        'party_id' => $party->id,
        'headline' => 'Atelier Nkeng · plomberie',
        'verification_tier' => 2,
    ]);
    ProviderSkill::factory()->create([
        'provider_profile_id' => $profile->id, 'skill_id' => $leaf->id,
    ]);

    $response = $this->get('/services/leak-repair?lang=en')->assertOk();

    $response->assertSee('Atelier Nkeng', escape: false)   // the PUBLIC headline is the point
        ->assertSee('Verified', escape: false)             // tier ≥ 2 earns the badge
        ->assertDontSee('Jean-Paul Mbarga', escape: false); // the personal name never leaks
});

it('excludes suspended providers, as search does', function () {
    ['leaf' => $leaf] = taxonomyPair();

    $profile = ProviderProfile::factory()->create([
        'headline' => 'Suspended Shop',
        'suspended_at' => now(),
    ]);
    ProviderSkill::factory()->create([
        'provider_profile_id' => $profile->id, 'skill_id' => $leaf->id,
    ]);

    $this->get('/services/leak-repair')
        ->assertOk()
        ->assertDontSee('Suspended Shop', escape: false);
});

it('carries the SEO head a crawler needs — canonical, reciprocal hreflang, structured data', function () {
    taxonomyPair();

    $response = $this->get('/services/leak-repair?lang=en')->assertOk();

    // Canonical drops ?lang= — a translation is not a separate page.
    $response->assertSee('<link rel="canonical" href="http://localhost/services/leak-repair">', escape: false)
        ->assertSee('hreflang="fr"', escape: false)
        ->assertSee('hreflang="en"', escape: false)
        ->assertSee('hreflang="x-default"', escape: false)
        ->assertSee('application/ld+json', escape: false)
        ->assertSee('"@type":"Service"', escape: false);
});

it('404s an unknown service rather than rendering an empty page', function () {
    $this->get('/services/not-a-real-trade')->assertNotFound();
});

it('publishes a sitemap listing every service in both languages', function () {
    taxonomyPair();

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('/services/leak-repair?lang=en', escape: false)
        ->assertSee('/services/leak-repair?lang=fr', escape: false)
        ->assertSee('hreflang="x-default"', escape: false);
});

it('tells crawlers to stay out of the grant URLs', function () {
    // Signed document links and share tokens are grants, not content — they must never be indexed.
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /verification-documents/', escape: false)
        ->assertSee('Disallow: /s/', escape: false)
        ->assertSee('Sitemap: http://localhost/sitemap.xml', escape: false);
});

it('substitutes translation placeholders instead of printing them raw', function () {
    ['leaf' => $leaf] = taxonomyPair();

    // The shared i18n source uses ngx-translate's {{name}}; the build rewrites it to Laravel's
    // :name for the backend dictionary. Without that, this page printed a literal "{{service}}".
    $this->get('/services/leak-repair?lang=en')
        ->assertOk()
        ->assertSee('Find trusted Leak repair professionals', escape: false)
        ->assertDontSee('{{service}}', escape: false);

    expect($leaf->slug)->toBe('leak-repair');
});
