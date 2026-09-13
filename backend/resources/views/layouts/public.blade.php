<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('app.name'))</title>
    <meta name="description" content="@yield('description', __('app.tagline'))">
    <meta name="color-scheme" content="dark">

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
         tokens.css and the generated @theme block). The palette comes from tokens/tokens.json;
         the styling is utilities and a few component classes. --}}
    @vite(['resources/css/app.css'])
</head>
<body>
    {{-- Off-screen until focused, then the first thing a keyboard reaches. --}}
    <a class="absolute -left-[9999px] top-0 z-30 rounded-md bg-brand px-4 py-2 text-brand-contrast focus:left-4 focus:top-4"
       href="#main">{{ __('public.skip_to_content') }}</a>

    {{-- Sticky, on a 90% ground so the page shows through as it scrolls under, ending in the 1px
         line that every region in this design is drawn with (handoff: "Shared chrome"). --}}
    <header class="sticky top-0 z-20 border-b border-edge bg-surface/90 backdrop-blur-md">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="flex min-h-[4.75rem] items-center gap-6">
                <a class="inline-flex items-center gap-2.5 text-lg font-bold tracking-[-0.02em] text-content no-underline hover:text-content" href="{{ route('home') }}">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[11px] bg-brand text-brand-contrast" aria-hidden="true">
                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.7 6.3a4 4 0 0 1-5 5L4 17v3h3l5.7-5.7a4 4 0 0 1 5-5l2.6-2.6-2.6-2.6z"/>
                        </svg>
                    </span>
                    {{ __('app.name') }}
                </a>

                <nav class="ms-auto flex items-center gap-2 sm:gap-3" aria-label="{{ __('public.nav_label') }}">
                    <span class="hidden items-center gap-7 me-4 lg:flex">
                        @foreach ([
                            ['public.nav_trades', route('services.index')],
                            ['public.nav_how', route('home').'#how'],
                            ['public.nav_trust', route('home').'#trust'],
                            ['public.nav_providers', route('home').'#providers'],
                            ['public.nav_faq', route('home').'#faq'],
                        ] as [$key, $href])
                            <a class="text-[0.9rem] font-medium text-content-muted no-underline transition-colors hover:text-content"
                               href="{{ $href }}">{{ __($key) }}</a>
                        @endforeach
                    </span>

                    {{-- Small screens: a <details> disclosure is a real menu with no JavaScript, so it
                         works on the first paint and on a dead connection. --}}
                    <details class="group relative lg:hidden">
                        <summary class="grid size-10 cursor-pointer list-none place-items-center rounded-[13px] border border-edge bg-surface-raised text-content [&::-webkit-details-marker]:hidden"
                                 aria-label="{{ __('public.nav_menu') }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <path d="M4 7h16M4 12h16M4 17h16"/>
                            </svg>
                        </summary>
                        <div class="absolute end-0 top-[calc(100%+0.5rem)] grid min-w-56 gap-0.5 rounded-lg border border-edge bg-surface-raised p-2">
                            @foreach ([
                                ['public.nav_trades', route('services.index')],
                                ['public.nav_how', route('home').'#how'],
                                ['public.nav_trust', route('home').'#trust'],
                                ['public.nav_providers', route('home').'#providers'],
                                ['public.nav_faq', route('home').'#faq'],
                            ] as [$key, $href])
                                <a class="rounded-sm px-3 py-2.5 text-sm font-medium text-content no-underline hover:bg-surface-sunken hover:text-content"
                                   href="{{ $href }}">{{ __($key) }}</a>
                            @endforeach
                        </div>
                    </details>

                    {{-- FR/EN: a surface-1 shell, the active language on surface-2. --}}
                    <span class="inline-flex items-center rounded-[12px] border border-edge bg-surface-raised p-1 text-xs">
                        @foreach (['fr' => 'language.french_short', 'en' => 'language.english_short'] as $code => $label)
                            <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                               aria-current="{{ app()->getLocale() === $code ? 'true' : 'false' }}"
                               @class([
                                   'rounded-[9px] px-2.5 py-1.5 font-bold no-underline transition-colors',
                                   'bg-surface-sunken text-content' => app()->getLocale() === $code,
                                   'text-content-muted hover:text-content' => app()->getLocale() !== $code,
                               ])>{{ __($label) }}</a>
                        @endforeach
                    </span>

                    {{-- The header's own call to action: the one filled button on the bar. --}}
                    <a class="btn btn-primary hidden min-h-11 py-2.5 lg:inline-flex" href="{{ route('services.index') }}">
                        {{ __('public.hero_cta_primary') }}
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main id="main">
        @yield('content')
    </main>

    <footer class="border-t border-edge bg-surface-rail pt-14 pb-7">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="grid gap-10 md:grid-cols-[1.4fr_repeat(3,minmax(0,1fr))] md:gap-16">
                <div>
                    <a class="inline-flex items-center gap-2.5 text-lg font-bold tracking-[-0.02em] text-content no-underline hover:text-content" href="{{ route('home') }}">
                        <span class="grid size-8 shrink-0 place-items-center rounded-[11px] bg-brand text-brand-contrast" aria-hidden="true">
                            <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.7 6.3a4 4 0 0 1-5 5L4 17v3h3l5.7-5.7a4 4 0 0 1 5-5l2.6-2.6-2.6-2.6z"/>
                            </svg>
                        </span>
                        {{ __('app.name') }}
                    </a>
                    <p class="mt-3 max-w-[34ch] text-sm leading-relaxed text-content-muted">
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
                        <h3 class="micro">{{ __($heading) }}</h3>
                        <ul class="mt-3 grid list-none gap-2 p-0">
                            @foreach ($links as [$key, $href])
                                <li><a class="text-sm text-content-tertiary no-underline hover:text-content" href="{{ $href }}">{{ __($key) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <div>
                    <h3 class="micro">{{ __('public.footer_language') }}</h3>
                    <ul class="mt-3 grid list-none gap-2 p-0">
                        @foreach (['fr' => 'language.french', 'en' => 'language.english'] as $code => $label)
                            <li><a class="text-sm text-content-tertiary no-underline hover:text-content" href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}">{{ __($label) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-wrap justify-between gap-2 border-t border-edge pt-6 text-[0.8rem] text-content-muted">
                <span>{{ __('public.footer_rights', ['year' => now()->year, 'name' => __('app.name')]) }}</span>
                <span>{{ __('public.footer_country') }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
