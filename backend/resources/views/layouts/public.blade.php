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

    {{-- Applied BEFORE the stylesheet paints, and inline for the same reason: a saved dark choice
         restored after first paint is a white flash on every navigation, which is worst for exactly
         the person who chose dark. No attribute at all means "follow the device", which the tokens
         already handle through prefers-color-scheme — so the untouched default costs nothing. --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('hm-theme');
                if (saved === 'dark' || saved === 'light') {
                    document.documentElement.setAttribute('data-theme', saved);
                }
            } catch (e) {
                // Private mode with storage denied. The device preference still applies.
            }
        })();
    </script>
</head>
<body>
    {{-- Off-screen until focused, then the first thing a keyboard reaches. --}}
    <a class="absolute -left-[9999px] top-0 z-30 rounded-md bg-brand px-4 py-2 text-brand-contrast focus:left-4 focus:top-4"
       href="#main">{{ __('public.skip_to_content') }}</a>

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

                    {{-- Theme. Three states, like the app's own setting: follow the device, or
                         override it either way. One button that cycles rather than three controls —
                         the header is already carrying a language switch and a call to action, and
                         at 390px a third segmented control would not fit beside them.

                         Rendered with BOTH glyphs, one hidden per theme by CSS, so the icon is right
                         on the very first paint. Deciding it in JS would show the wrong one until the
                         script ran, which is the flash this whole arrangement exists to avoid. --}}
                    <button type="button"
                            data-theme-toggle
                            class="grid size-9 flex-none place-items-center rounded-pill border border-edge bg-surface-raised text-content-muted cursor-pointer transition-colors hover:text-content"
                            title="{{ __('public.theme_toggle') }}"
                            aria-label="{{ __('public.theme_toggle') }}">
                        <svg class="size-4 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                        </svg>
                        <svg class="size-4 hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
                        </svg>
                    </button>

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

    <footer class="border-t border-edge bg-surface-raised pt-16 pb-6">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="grid gap-6 grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))]">
                <div>
                    <a class="inline-flex items-center gap-2 font-extrabold text-lg tracking-tight text-content no-underline" href="{{ route('home') }}">
                        <span class="grid place-items-center shrink-0 size-7 rounded-md bg-brand text-brand-contrast" aria-hidden="true">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.7 6.3a4 4 0 0 1-5 5L4 17v3h3l5.7-5.7a4 4 0 0 1 5-5l2.6-2.6-2.6-2.6z"/>
                            </svg>
                        </span>
                        {{ __('app.name') }}
                    </a>
                    <p class="mt-2 max-w-[22rem] text-sm text-content-muted">
                        {{ __('public.footer_blurb') }}
                    </p>
                </div>

                @foreach ([
                    ['public.footer_customers', [
                        ['public.nav_trades', route('services.index')],
                        ['public.nav_how', route('home').'#how'],
                        ['public.nav_trust', route('home').'#trust'],
                        ['public.nav_faq', route('home').'#faq'],
                    ]],
                    ['public.footer_providers', [
                        ['public.footer_join', route('home').'#providers'],
                        ['public.footer_pricing', route('home').'#providers'],
                        ['public.footer_safety', route('home').'#trust'],
                    ]],
                ] as [$heading, $links])
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-[0.1em] text-content-muted">{{ __($heading) }}</h3>
                        <ul class="grid gap-1.5 mt-2 list-none p-0">
                            @foreach ($links as [$key, $href])
                                <li><a class="text-sm text-content-muted no-underline hover:text-content" href="{{ $href }}">{{ __($key) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-[0.1em] text-content-muted">{{ __('public.footer_language') }}</h3>
                    <ul class="grid gap-1.5 mt-2 list-none p-0">
                        @foreach (['fr' => 'language.french', 'en' => 'language.english'] as $code => $label)
                            <li><a class="text-sm text-content-muted no-underline hover:text-content" href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}">{{ __($label) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="flex flex-wrap justify-between gap-2 mt-10 pt-4 border-t border-edge text-sm text-content-muted">
                <span>{{ __('public.footer_rights', ['year' => now()->year, 'name' => __('app.name')]) }}</span>
                <span>{{ __('public.footer_country') }}</span>
            </div>
        </div>
    </footer>

    {{-- The toggle's behaviour. Inline rather than a bundle: this is the only script the marketing
         site has, and a whole JS request to cycle one attribute is a poor trade on a slow
         connection.

         It cycles through three states in the order someone actually wants them — from wherever you
         are, one tap gives you the other appearance, and a third returns you to following the
         device. `system` is stored as the ABSENCE of the attribute, so it can never drift from what
         the tokens' media query already decides. --}}
    <script>
        (function () {
            var button = document.querySelector('[data-theme-toggle]');
            if (!button) {
                return;
            }

            var root = document.documentElement;

            button.addEventListener('click', function () {
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var current = root.getAttribute('data-theme');
                // Starting from "follow the device", the useful first tap is the opposite of what
                // they are looking at — not a fixed direction that would appear to do nothing.
                var next = current === null
                    ? (prefersDark ? 'light' : 'dark')
                    : (current === (prefersDark ? 'light' : 'dark') ? (prefersDark ? 'dark' : 'light') : null);

                if (next === null) {
                    root.removeAttribute('data-theme');
                } else {
                    root.setAttribute('data-theme', next);
                }

                try {
                    if (next === null) {
                        localStorage.removeItem('hm-theme');
                    } else {
                        localStorage.setItem('hm-theme', next);
                    }
                } catch (e) {
                    // Storage denied — the choice still applies to this page, it just won't persist.
                }
            });
        })();
    </script>
</body>
</html>
