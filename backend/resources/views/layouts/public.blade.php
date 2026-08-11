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

    {{-- Design tokens — GENERATED from tokens/tokens.json (npm run tokens:build). Same semantic
         --hm-* variables as the app, so Blade and the Ionic app share one theme (doc 08). --}}
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <style>
        /* ── Foundations ───────────────────────────────────────────────────────────────────────
           Every colour here resolves to a --hm-* token: the no-literal-colour lint scans this file,
           which is deliberate — the marketing surface is exactly where a "just this once" hex would
           creep in and then fail in dark mode. */
        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            background: var(--hm-color-surface-base);
            color: var(--hm-color-text-primary);
            line-height: 1.6;
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
        }
        img, svg { max-width: 100%; }
        a { color: var(--hm-color-brand-primary); text-underline-offset: 3px; }

        /* Fluid type scale. clamp() rather than breakpoints so headlines are proportionate on a
           360px Tecno and on a desktop without a cascade of media queries. */
        h1, h2, h3 { margin: 0; letter-spacing: -0.025em; line-height: 1.15; font-weight: 800; }
        .t-display { font-size: clamp(2.1rem, 1.35rem + 3.2vw, 3.9rem); }
        .t-h2 { font-size: clamp(1.55rem, 1.15rem + 1.7vw, 2.4rem); }
        .t-h3 { font-size: clamp(1.05rem, 0.95rem + 0.4vw, 1.2rem); line-height: 1.3; }
        .t-lede { font-size: clamp(1.02rem, 0.96rem + 0.35vw, 1.2rem); color: var(--hm-color-text-muted); }
        .t-small { font-size: 0.875rem; }
        .t-eyebrow {
            display: inline-block; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.12em;
            text-transform: uppercase; color: var(--hm-color-brand-primary);
        }
        .muted { color: var(--hm-color-text-muted); }

        /* ── Layout ─────────────────────────────────────────────────────────────────────────── */
        .wrap { width: 100%; max-width: 72rem; margin: 0 auto; padding-inline: var(--hm-space-lg); }
        .section { padding-block: var(--hm-space-3xl); }
        .section-sm { padding-block: var(--hm-space-xl); }
        /* Two adjacent sections would otherwise stack their padding into a visible void; collapse
           the seam so the inner pages read as one document rather than a stack of slabs. */
        .section-sm + .section-sm { padding-block-start: 0; }
        .stack-sm > * + * { margin-top: var(--hm-space-sm); }
        .stack > * + * { margin-top: var(--hm-space-md); }
        .grid { display: grid; gap: var(--hm-space-md); }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(min(100%, 20rem), 1fr)); }
        .grid-3 { grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); }
        .grid-4 { grid-template-columns: repeat(auto-fit, minmax(min(100%, 13rem), 1fr)); }
        .center { text-align: center; }
        .measure { max-width: 44rem; }
        .measure-center { max-width: 44rem; margin-inline: auto; }

        /* ── Header ─────────────────────────────────────────────────────────────────────────── */
        .site-header {
            position: sticky; top: 0; z-index: 20;
            background: color-mix(in srgb, var(--hm-color-surface-base) 88%, transparent);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--hm-color-border-subtle);
        }
        .header-inner { display: flex; align-items: center; gap: var(--hm-space-md); min-height: 4rem; }
        .brand {
            display: inline-flex; align-items: center; gap: 0.55rem;
            font-weight: 800; font-size: 1.06rem; letter-spacing: -0.02em;
            color: var(--hm-color-text-primary); text-decoration: none;
        }
        .brand .mark {
            width: 1.85rem; height: 1.85rem; border-radius: var(--hm-radius-md);
            background: var(--hm-color-brand-primary); color: var(--hm-color-brand-onPrimary);
            display: grid; place-items: center; flex: none;
        }
        .brand .mark svg { width: 1.05rem; height: 1.05rem; }
        .site-nav { display: flex; align-items: center; gap: var(--hm-space-md); margin-inline-start: auto; }
        .site-nav a {
            color: var(--hm-color-text-muted); text-decoration: none; font-size: 0.925rem; font-weight: 500;
        }
        .site-nav a:hover { color: var(--hm-color-text-primary); }
        .nav-links { display: none; gap: var(--hm-space-md); }
        @media (min-width: 52rem) { .nav-links { display: flex; } }
        .menu { position: relative; }
        .menu > summary {
            list-style: none; cursor: pointer; display: grid; place-items: center;
            width: 2.5rem; height: 2.5rem; border-radius: var(--hm-radius-md);
            border: 1px solid var(--hm-color-border-subtle); color: var(--hm-color-text-primary);
        }
        .menu > summary::-webkit-details-marker { display: none; }
        .menu > summary svg { width: 1.15rem; height: 1.15rem; }
        .menu-panel {
            position: absolute; inset-inline-end: 0; top: calc(100% + 0.5rem);
            min-width: 13rem; display: grid; gap: 0.15rem; padding: var(--hm-space-sm);
            background: var(--hm-color-surface-raised);
            border: 1px solid var(--hm-color-border-subtle);
            border-radius: var(--hm-radius-lg); box-shadow: var(--hm-shadow-lg);
        }
        .menu-panel a { padding: 0.6rem 0.7rem; border-radius: var(--hm-radius-sm); }
        .menu-panel a:hover { background: var(--hm-color-surface-sunken); }
        @media (min-width: 52rem) { .menu { display: none; } }

        .lang-switch { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; }
        .lang-switch a { padding: 0.15rem 0.4rem; border-radius: var(--hm-radius-sm); }
        .lang-switch a[aria-current="true"] {
            color: var(--hm-color-text-primary); font-weight: 700; background: var(--hm-color-surface-sunken);
        }
        .lang-sep { color: var(--hm-color-border-strong); }

        /* ── Buttons ────────────────────────────────────────────────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 0.72rem 1.15rem; border-radius: var(--hm-radius-md);
            font-weight: 650; font-size: 0.95rem; text-decoration: none; cursor: pointer;
            border: 1px solid transparent; transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
            /* 44px minimum target: this is a phone-first market. */
            min-height: 2.75rem;
        }
        .btn:focus-visible { outline: 3px solid var(--hm-color-brand-ring); outline-offset: 2px; }
        .btn-primary { background: var(--hm-color-brand-primary); color: var(--hm-color-brand-onPrimary); box-shadow: var(--hm-shadow-sm); }
        .btn-primary:hover { background: var(--hm-color-brand-strong); transform: translateY(-1px); box-shadow: var(--hm-shadow-md); }
        .btn-ghost { background: var(--hm-color-surface-raised); color: var(--hm-color-text-primary); border-color: var(--hm-color-border-subtle); }
        .btn-ghost:hover { border-color: var(--hm-color-border-strong); transform: translateY(-1px); }
        .btn-onInverse { background: var(--hm-color-surface-raised); color: var(--hm-color-text-primary); }
        .btn-onInverse:hover { transform: translateY(-1px); box-shadow: var(--hm-shadow-md); }
        .btn-row { display: flex; flex-wrap: wrap; gap: var(--hm-space-sm); }

        /* ── Cards ──────────────────────────────────────────────────────────────────────────── */
        .card {
            background: var(--hm-color-surface-raised);
            border: 1px solid var(--hm-color-border-subtle);
            border-radius: var(--hm-radius-lg);
            padding: var(--hm-space-lg);
        }
        a.card { display: block; text-decoration: none; color: inherit; transition: transform .14s ease, box-shadow .14s ease, border-color .14s ease; }
        a.card:hover { transform: translateY(-2px); border-color: var(--hm-color-brand-primary); box-shadow: var(--hm-shadow-md); }
        .icon-badge {
            width: 2.5rem; height: 2.5rem; border-radius: var(--hm-radius-md); flex: none;
            display: grid; place-items: center;
            background: var(--hm-color-brand-tint); color: var(--hm-color-brand-primary);
        }
        .icon-badge svg { width: 1.25rem; height: 1.25rem; }
        .pill {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.2rem 0.6rem; border-radius: var(--hm-radius-pill);
            font-size: 0.75rem; font-weight: 650;
            background: var(--hm-color-brand-tint); color: var(--hm-color-brand-primary);
        }
        .pill-quiet { background: var(--hm-color-surface-sunken); color: var(--hm-color-text-muted); }

        /* ── Inverted band ──────────────────────────────────────────────────────────────────── */
        .band-inverse {
            background: var(--hm-color-surface-inverse);
            color: var(--hm-color-text-onInverse);
        }
        .band-inverse h2, .band-inverse h3 { color: var(--hm-color-text-onInverse); }
        .band-inverse .muted { color: var(--hm-color-text-onInverse); opacity: 0.72; }
        .band-inverse .card {
            background: transparent; border-color: var(--hm-color-border-onInverse);
        }
        .band-tint { background: var(--hm-color-brand-tint); }

        /* ── Footer ─────────────────────────────────────────────────────────────────────────── */
        .site-footer {
            border-top: 1px solid var(--hm-color-border-subtle);
            background: var(--hm-color-surface-raised);
            padding-block: var(--hm-space-2xl) var(--hm-space-lg);
        }
        .footer-grid { display: grid; gap: var(--hm-space-lg); grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr)); }
        .footer-grid h3 { font-size: 0.8rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--hm-color-text-muted); font-weight: 700; }
        .footer-links { list-style: none; margin: var(--hm-space-sm) 0 0; padding: 0; display: grid; gap: 0.45rem; }
        .footer-links a { color: var(--hm-color-text-muted); text-decoration: none; font-size: 0.9rem; }
        .footer-links a:hover { color: var(--hm-color-text-primary); }
        .footer-bottom {
            margin-top: var(--hm-space-xl); padding-top: var(--hm-space-md);
            border-top: 1px solid var(--hm-color-border-subtle);
            display: flex; flex-wrap: wrap; gap: var(--hm-space-sm); justify-content: space-between;
            font-size: 0.85rem; color: var(--hm-color-text-muted);
        }

        /* ── Misc shared ────────────────────────────────────────────────────────────────────── */
        .crumbs { font-size: 0.85rem; color: var(--hm-color-text-muted); }
        .crumbs a { color: var(--hm-color-text-muted); text-decoration: none; }
        .crumbs a:hover { color: var(--hm-color-text-primary); text-decoration: underline; }
        .chips { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: var(--hm-space-sm); }
        .chips a {
            display: inline-block; padding: 0.45rem 0.85rem;
            background: var(--hm-color-surface-raised); border: 1px solid var(--hm-color-border-subtle);
            border-radius: var(--hm-radius-pill); text-decoration: none; font-size: 0.9rem;
            color: var(--hm-color-text-primary); transition: border-color .12s ease, color .12s ease;
        }
        .chips a:hover { border-color: var(--hm-color-brand-primary); color: var(--hm-color-brand-primary); }
        /* ── Hero ──────────────────────────────────────────────────────────────────────────── */
        .hero-grid { display: grid; gap: var(--hm-space-2xl); align-items: center; }
        .hero-visual { display: none; }
        @media (min-width: 62rem) {
            .hero-grid { grid-template-columns: 1.05fr 0.95fr; }
            .hero-visual { display: block; }
        }
        .mock {
            background: var(--hm-color-surface-raised);
            border: 1px solid var(--hm-color-border-subtle);
            border-radius: var(--hm-radius-lg);
            box-shadow: var(--hm-shadow-lg);
            padding: var(--hm-space-lg);
            max-width: 24rem; margin-inline-start: auto;
        }
        .mock-row { display: flex; align-items: center; gap: var(--hm-space-sm); }
        .mock-avatar {
            width: 2.4rem; height: 2.4rem; border-radius: var(--hm-radius-md); flex: none;
            display: grid; place-items: center; font-weight: 800; font-size: 0.8rem;
            background: var(--hm-color-brand-primary); color: var(--hm-color-brand-onPrimary);
        }
        .mock-escrow {
            display: flex; align-items: center; gap: var(--hm-space-sm);
            margin-top: var(--hm-space-md); padding: var(--hm-space-md);
            border-radius: var(--hm-radius-md); background: var(--hm-color-brand-tint);
        }
        .mock-steps { margin-top: var(--hm-space-md); display: grid; gap: 0.55rem; }
        .mock-step { display: flex; align-items: center; gap: 0.6rem; color: var(--hm-color-text-muted); }
        .mock-step .dot {
            width: 1.15rem; height: 1.15rem; border-radius: var(--hm-radius-pill); flex: none;
            display: grid; place-items: center; font-size: 0.7rem;
            border: 1.5px solid var(--hm-color-border-strong); color: transparent;
        }
        .mock-step.done { color: var(--hm-color-text-primary); }
        .mock-step.done .dot {
            background: var(--hm-color-brand-primary); border-color: var(--hm-color-brand-primary);
            color: var(--hm-color-brand-onPrimary);
        }
        .mock-cta {
            margin-top: var(--hm-space-md); text-align: center;
            padding: 0.7rem; border-radius: var(--hm-radius-md);
            background: var(--hm-color-brand-primary); color: var(--hm-color-brand-onPrimary);
            font-weight: 650; font-size: 0.92rem;
        }

        .skip-link {
            position: absolute; left: -9999px; top: 0;
            background: var(--hm-color-brand-primary); color: var(--hm-color-brand-onPrimary);
            padding: var(--hm-space-sm) var(--hm-space-md); border-radius: var(--hm-radius-md); z-index: 30;
        }
        .skip-link:focus { left: var(--hm-space-md); top: var(--hm-space-md); }
    </style>
</head>
<body>
    <a class="skip-link" href="#main">{{ __('public.skip_to_content') }}</a>

    <header class="site-header">
        <div class="wrap header-inner">
            <a class="brand" href="{{ route('home') }}">
                <span class="mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.7 6.3a4 4 0 0 1-5 5L4 17v3h3l5.7-5.7a4 4 0 0 1 5-5l2.6-2.6-2.6-2.6z"/>
                    </svg>
                </span>
                {{ __('app.name') }}
            </a>

            <nav class="site-nav" aria-label="{{ __('public.nav_label') }}">
                <span class="nav-links">
                    <a href="{{ route('services.index') }}">{{ __('public.nav_trades') }}</a>
                    <a href="{{ route('home') }}#how">{{ __('public.nav_how') }}</a>
                    <a href="{{ route('home') }}#trust">{{ __('public.nav_trust') }}</a>
                    <a href="{{ route('home') }}#providers">{{ __('public.nav_providers') }}</a>
                </span>

                {{-- Small screens got only the language switch, which left a phone visitor unable to
                     reach anything from the header — on a phone-first market that is the majority.
                     A <details> disclosure is a real menu with no JavaScript, so it still works on
                     the first paint and on a dead connection. --}}
                <details class="menu">
                    <summary aria-label="{{ __('public.nav_menu') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16"/>
                        </svg>
                    </summary>
                    <div class="menu-panel">
                        <a href="{{ route('services.index') }}">{{ __('public.nav_trades') }}</a>
                        <a href="{{ route('home') }}#how">{{ __('public.nav_how') }}</a>
                        <a href="{{ route('home') }}#trust">{{ __('public.nav_trust') }}</a>
                        <a href="{{ route('home') }}#providers">{{ __('public.nav_providers') }}</a>
                        <a href="{{ route('home') }}#faq">{{ __('public.nav_faq') }}</a>
                    </div>
                </details>
                <span class="lang-switch">
                    <a href="{{ request()->fullUrlWithQuery(['lang' => 'fr']) }}"
                       aria-current="{{ app()->getLocale() === 'fr' ? 'true' : 'false' }}">{{ __('language.french_short') }}</a>
                    <span class="lang-sep" aria-hidden="true">/</span>
                    <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}"
                       aria-current="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">{{ __('language.english_short') }}</a>
                </span>
            </nav>
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
