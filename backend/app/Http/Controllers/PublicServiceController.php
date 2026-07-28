<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProviderProfile;
use App\Models\Skill;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The crawlable services directory (doc 08). A customer must be able to find a trade and see who
 * offers it WITHOUT loading the app bundle, so these are server-rendered Blade pages built on the
 * bilingual taxonomy (P1-07) — which is the site's natural SEO surface: 13 categories and 41 leaves,
 * each a real search term, in both languages.
 *
 * PII: a provider listed here is pre-engagement to every visitor, and an anonymous one at that, so
 * these pages obey the same minimisation as the API's match list — the public HEADLINE, reputation
 * and verification tier only. Never a display name, never a service area or coordinate. Suspended
 * providers are excluded, as they are from search.
 */
final class PublicServiceController extends Controller
{
    /** Every category with its leaves — the directory's index and the sitemap's backbone. */
    public function index(): View
    {
        // The web group's SetLocale has already resolved fr/en (from ?lang=, the user, or
        // Accept-Language), so read it rather than re-deriving — RequestLocale is the API's
        // equivalent and keys off ?locale=, which this surface does not use.
        $locale = app()->getLocale();

        $categories = Skill::query()
            ->where('is_leaf', false)
            ->with(['children' => fn ($q) => $q->where('is_leaf', true)])
            ->get()
            ->sortBy(fn (Skill $s): string => $s->name($locale))
            ->values();

        return view('public.services', [
            'locale' => $locale,
            'categories' => $categories,
            // Built here rather than in the template: Blade's @json can't parse a multi-line array
            // literal, and structured data is data, not markup.
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => __('public.services_title'),
                'itemListElement' => $categories->values()->map(fn (Skill $c, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $c->name($locale),
                    'url' => route('services.show', ['slug' => $c->slug]),
                ])->all(),
            ],
        ]);
    }

    /** One trade: what it is, and who offers it. */
    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        $skill = Skill::query()->where('slug', $slug)->first();
        if ($skill === null) {
            throw new NotFoundHttpException('Unknown service.');
        }

        // A category page lists its leaves; a leaf page lists providers.
        $children = $skill->is_leaf
            ? collect()
            : $skill->children()->where('is_leaf', true)->get()->sortBy(fn (Skill $s): string => $s->name($locale))->values();

        $providers = $skill->is_leaf
            ? ProviderProfile::query()
                ->whereNull('suspended_at')
                ->whereHas('skills', fn ($q) => $q->where('skill_id', $skill->id))
                ->orderByDesc('verification_tier')
                ->orderByDesc('rating_avg')
                ->limit(50)
                ->get()
            : collect();

        return view('public.service', [
            'locale' => $locale,
            'skill' => $skill,
            'parent' => $skill->parent,
            'children' => $children,
            'providers' => $providers,
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'Service',
                'name' => $skill->name($locale),
                'serviceType' => $skill->name($locale),
                'areaServed' => ['@type' => 'Country', 'name' => 'Cameroon'],
                'provider' => ['@type' => 'Organization', 'name' => __('app.name')],
            ],
        ]);
    }
}
