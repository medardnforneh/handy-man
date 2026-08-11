<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Contracts\View\View;

/**
 * The landing page. It was a closure returning a static view; it needs real data now, because the
 * one thing this page must prove is that there is a marketplace behind it — and the taxonomy (P1-07)
 * is the only inventory that exists before a single provider signs up.
 *
 * Deliberately shows category and trade NAMES, never provider counts: "3 plumbers" is a liquidity
 * disclosure, and an empty marketplace should not advertise how empty it is. The counts here are the
 * size of the taxonomy, which is a fact about the product rather than about supply.
 */
final class PublicHomeController extends Controller
{
    public function __invoke(): View
    {
        $locale = app()->getLocale();

        $categories = Skill::query()
            ->where('is_leaf', false)
            ->withCount(['children' => fn ($q) => $q->where('is_leaf', true)])
            ->get()
            ->sortBy(fn (Skill $s): string => $s->name($locale))
            ->values();

        // A handful of leaves for the hero's quick links. Sorted by name rather than by popularity
        // we do not have yet — an invented "most requested" ordering would be a lie the page tells
        // on its first screen.
        $popularTrades = Skill::query()
            ->where('is_leaf', true)
            ->get()
            ->sortBy(fn (Skill $s): string => $s->name($locale))
            ->take(8)
            ->values();

        return view('public.home', [
            'locale' => $locale,
            'categories' => $categories,
            'popularTrades' => $popularTrades,
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => __('app.name'),
                'url' => route('home'),
                'inLanguage' => [$locale],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => __('app.name'),
                    'areaServed' => ['@type' => 'Country', 'name' => 'Cameroon'],
                ],
            ],
        ]);
    }
}
