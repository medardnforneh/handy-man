<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('app.name'))</title>
    <meta name="description" content="@yield('description', __('app.tagline'))">

    {{-- Canonical without the `lang` parameter: ?lang= selects a translation, it does not create a
         separate page, so leaving it in would split ranking signals across near-identical URLs. --}}
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Reciprocal alternates for a genuinely bilingual site (P0-15): fr and en are translations of
         one another, not duplicates, and x-default points at the unparameterised URL. --}}
    @foreach (['fr', 'en'] as $alt)
        <link rel="alternate" hreflang="{{ $alt }}" href="{{ request()->fullUrlWithQuery(['lang' => $alt]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ __('app.name') }}">
    <meta property="og:title" content="@yield('title', __('app.name'))">
    <meta property="og:description" content="@yield('description', __('app.tagline'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="{{ app()->getLocale() }}">
    <meta name="twitter:card" content="summary">

    @stack('structured-data')

    {{-- Tailwind, with the design tokens compiled into it (resources/css/app.css imports
         tokens.css and the generated @theme block). This replaces the hand-written stylesheet that
         used to live in this file: the palette still comes from tokens/tokens.json, but the styling
         is utilities in the markup. --}}
    @vite(['resources/css/app.css'])
</head>
<body>
    <a class="skip-link" href="#main">{{ __('public.skip_to_content') }}</a>

    {{-- Sticky, but floating: the chrome is a contained pill rather than a bar welded to the top
         edge, so the page reads as content on a surface. The short fade behind it stops the page
         showing through the gaps either side of the pill's rounded ends. --}}
    <header class="sticky top-0 z-20 py-3 bg-linear-to-b from-surface from-55% to-transparent">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="flex items-center gap-4 min-h-14 px-4 rounded-pill border border-edge/85 bg-surface-raised/85 shadow-md backdrop-blur-xl backdrop-saturate-150">
                <a class="inline-flex items-center gap-2 font-extrabold text-lg tracking-tight text-content no-underline" href="{{ route('home') }}">
                    <span class="grid place-items-center shrink-0 size-7 rounded-md bg-brand text-brand-contrast" aria-hidden="true">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.7 6.3a4 4 0 0 1-5 5L4 17v3h3l5.7-5.7a4 4 0 0 1 5-5l2.6-2.6-2.6-2.6z"/>
                        </svg>
                    </span>
                    {{ __('app.name') }}
                </a>

                <nav class="flex items-center gap-1.5 ms-auto" aria-label="{{ __('public.nav_label') }}">
                    {{-- The hover target is the pill, not the word. --}}
                    <span class="hidden lg:flex gap-0.5">
                        @foreach ([
                            ['public.nav_trades', route('services.index')],
                            ['public.nav_how', route('home').'#how'],
                            ['public.nav_trust', route('home').'#trust'],
                            ['public.nav_providers', route('home').'#providers'],
                        ] as [$key, $href])
                            <a class="px-3 py-2 rounded-pill text-sm font-medium text-content-muted no-underline transition-colors hover:text-content hover:bg-brand/10"
                               href="{{ $href }}">{{ __($key) }}</a>
                        @endforeach
                    </span>

                    {{-- Small screens got only the language switch, which left a phone visitor unable
                         to reach anything from the header. A <details> disclosure is a real menu with
                         no JavaScript, so it works on the first paint and on a dead connection. --}}
                    <details class="relative lg:hidden group">
                        <summary class="grid place-items-center size-10 rounded-md border border-edge text-content cursor-pointer list-none [&::-webkit-details-marker]:hidden"
                                 aria-label="{{ __('public.nav_menu') }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <path d="M4 7h16M4 12h16M4 17h16"/>
                            </svg>
                        </summary>
                        <div class="absolute end-0 top-[calc(100%+0.5rem)] grid gap-0.5 min-w-52 p-2 rounded-lg border border-edge bg-surface-raised shadow-lg">
                            @foreach ([
                                ['public.nav_trades', route('services.index')],
                                ['public.nav_how', route('home').'#how'],
                                ['public.nav_trust', route('home').'#trust'],
                                ['public.nav_providers', route('home').'#providers'],
                                ['public.nav_faq', route('home').'#faq'],
                            ] as [$key, $href])
                                <a class="px-3 py-2.5 rounded-sm text-sm font-medium text-content-muted no-underline hover:bg-surface-sunken hover:text-content"
                                   href="{{ $href }}">{{ __($key) }}</a>
                            @endforeach
                        </div>
                    </details>

                    {{-- A segmented control rather than "FR / EN": two links with a slash between
                         them read as breadcrumbs, and the slash was the third-loudest glyph here. --}}
                    <span class="inline-flex items-center p-[3px] rounded-pill bg-surface-sunken text-xs">
                        @foreach (['fr' => 'language.french_short', 'en' => 'language.english_short'] as $code => $label)
                            <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                               aria-current="{{ app()->getLocale() === $code ? 'true' : 'false' }}"
                               @class([
                                   'px-2.5 py-1 rounded-pill font-semibold no-underline transition-colors',
                                   'text-content bg-surface-raised shadow-sm' => app()->getLocale() === $code,
                                   'text-content-muted' => app()->getLocale() !== $code,
                               ])>{{ __($label) }}</a>
                        @endforeach
                    </span>

                    {{-- The header's own call to action. Without one, a reader convinced by what they
                         just read has to scroll back up to the hero to act on it. --}}
                    <a class="hidden lg:inline-flex items-center gap-1.5 px-4 py-2.5 rounded-pill bg-brand text-brand-contrast text-sm font-bold no-underline shadow-sm transition-colors hover:bg-brand-strong active:translate-y-px"
                       href="{{ route('services.index') }}">
                        {{ __('public.hero_cta_primary') }}
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main id="main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="wrap">
            <div class="footer-grid">
                <div>
                    <a class="brand" href="{{ route('home') }}">
                        <span class="mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.7 6.3a4 4 0 0 1-5 5L4 17v3h3l5.7-5.7a4 4 0 0 1 5-5l2.6-2.6-2.6-2.6z"/>
                            </svg>
                        </span>
                        {{ __('app.name') }}
                    </a>
                    <p class="t-small muted" style="margin-top: var(--hm-space-sm); max-width: 22rem;">
                        {{ __('public.footer_blurb') }}
                    </p>
                </div>

                <div>
                    <h3>{{ __('public.footer_customers') }}</h3>
                    <ul class="footer-links">
                        <li><a href="{{ route('services.index') }}">{{ __('public.nav_trades') }}</a></li>
                        <li><a href="{{ route('home') }}#how">{{ __('public.nav_how') }}</a></li>
                        <li><a href="{{ route('home') }}#trust">{{ __('public.nav_trust') }}</a></li>
                        <li><a href="{{ route('home') }}#faq">{{ __('public.nav_faq') }}</a></li>
                    </ul>
                </div>

                <div>
                    <h3>{{ __('public.footer_providers') }}</h3>
                    <ul class="footer-links">
                        <li><a href="{{ route('home') }}#providers">{{ __('public.footer_join') }}</a></li>
                        <li><a href="{{ route('home') }}#providers">{{ __('public.footer_pricing') }}</a></li>
                        <li><a href="{{ route('home') }}#trust">{{ __('public.footer_safety') }}</a></li>
                    </ul>
                </div>

                <div>
                    <h3>{{ __('public.footer_language') }}</h3>
                    <ul class="footer-links">
                        <li><a href="{{ request()->fullUrlWithQuery(['lang' => 'fr']) }}">{{ __('language.french') }}</a></li>
                        <li><a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}">{{ __('language.english') }}</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <span>{{ __('public.footer_rights', ['year' => now()->year, 'name' => __('app.name')]) }}</span>
                <span>{{ __('public.footer_country') }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
