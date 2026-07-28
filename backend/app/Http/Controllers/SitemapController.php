<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Support\RequestLocale;
use Illuminate\Http\Response;

/**
 * sitemap.xml and robots.txt — how a crawler discovers the services directory (doc 08).
 *
 * Every public URL is listed once per supported language with reciprocal `xhtml:link` alternates,
 * because the site is genuinely bilingual (P0-15) and the fr/en pages are translations of one
 * another, not duplicates. Only public pages appear: signed document URLs and share links are
 * deliberately absent — they are grants, not content, and must never be crawled.
 */
final class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $pages = [route('home'), route('services.index')];
        foreach (Skill::query()->orderBy('slug')->pluck('slug') as $slug) {
            $pages[] = route('services.show', ['slug' => $slug]);
        }

        // URLs are assembled here, not in the template: a sitemap entry is data, and building it
        // in Blade means concatenating query strings in markup.
        $entries = [];
        foreach ($pages as $page) {
            $alternates = [];
            foreach (RequestLocale::SUPPORTED as $locale) {
                $alternates[$locale] = $page.'?lang='.$locale;
            }
            foreach (RequestLocale::SUPPORTED as $locale) {
                $entries[] = [
                    'loc' => $alternates[$locale],
                    'alternates' => $alternates,
                    'default' => $page,
                ];
            }
        }

        $xml = view('public.sitemap', ['entries' => $entries])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            // Grants, not content: a signed verification-document URL or a share token must never
            // be indexed, even though both 404/403 once expired.
            'Disallow: /verification-documents/',
            'Disallow: /s/',
            'Disallow: /admin',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }
}
