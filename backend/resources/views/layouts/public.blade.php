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
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--hm-color-surface-base);
            color: var(--hm-color-text-primary);
            line-height: 1.5;
        }
        .container { max-width: 60rem; margin: 0 auto; padding: var(--hm-space-lg); }
        .site-header { display: flex; justify-content: space-between; align-items: center; }
        .brand { font-weight: 700; color: var(--hm-color-brand-primary); }
        .lang-switch a { color: var(--hm-color-text-muted); text-decoration: none; margin-left: var(--hm-space-sm); }
        .lang-switch a[aria-current="true"] { color: var(--hm-color-text-primary); font-weight: 600; }
        .hero { padding: var(--hm-space-xl) 0; }
        .hero h1 { font-size: 2rem; margin: 0 0 var(--hm-space-sm); }
        .hero p { color: var(--hm-color-text-muted); font-size: 1.125rem; }
        .cta {
            display: inline-block; margin-top: var(--hm-space-md);
            background: var(--hm-color-brand-primary); color: var(--hm-color-brand-onPrimary);
            padding: var(--hm-space-sm) var(--hm-space-lg); border-radius: var(--hm-radius-md);
            text-decoration: none; font-weight: 600;
        }
        .sections { display: grid; gap: var(--hm-space-md); margin-top: var(--hm-space-xl); }
        @media (min-width: 40rem) { .sections { grid-template-columns: 1fr 1fr; } }
        .card {
            background: var(--hm-color-surface-raised); border: 1px solid var(--hm-color-border-subtle);
            border-radius: var(--hm-radius-lg); padding: var(--hm-space-lg);
        }
        a { color: var(--hm-color-brand-primary); }
        .crumbs { font-size: 0.875rem; color: var(--hm-color-text-muted); margin: var(--hm-space-md) 0 0; }
        .crumbs a { color: var(--hm-color-text-muted); }
        h1 { font-size: 1.75rem; margin: var(--hm-space-sm) 0; }
        h2 { font-size: 1.15rem; margin: 0 0 var(--hm-space-sm); }
        .lede { color: var(--hm-color-text-muted); }
        .cat { margin-top: var(--hm-space-lg); }
        .links { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: var(--hm-space-sm); }
        .links a {
            display: inline-block; padding: 0.35rem 0.75rem;
            background: var(--hm-color-surface-raised); border: 1px solid var(--hm-color-border-subtle);
            border-radius: var(--hm-radius-pill); text-decoration: none; font-size: 0.9rem;
        }
        .providers { list-style: none; margin: var(--hm-space-md) 0 0; padding: 0; display: grid; gap: var(--hm-space-sm); }
        .provider {
            display: flex; align-items: baseline; gap: var(--hm-space-sm); flex-wrap: wrap;
            background: var(--hm-color-surface-raised); border: 1px solid var(--hm-color-border-subtle);
            border-radius: var(--hm-radius-md); padding: var(--hm-space-md);
        }
        .provider .name { font-weight: 600; }
        .badge {
            font-size: 0.75rem; font-weight: 600; padding: 0.1rem 0.5rem;
            border-radius: var(--hm-radius-pill);
            background: var(--hm-color-surface-sunken); color: var(--hm-color-text-muted);
        }
        .empty { color: var(--hm-color-text-muted); }
        .site-footer { margin: var(--hm-space-xl) 0 0; padding-top: var(--hm-space-md);
            border-top: 1px solid var(--hm-color-border-subtle); font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="container">
        <header class="site-header">
            <span class="brand">{{ __('app.name') }}</span>
            <nav class="lang-switch">
                <a href="{{ request()->fullUrlWithQuery(['lang' => 'fr']) }}"
                   aria-current="{{ app()->getLocale() === 'fr' ? 'true' : 'false' }}">{{ __('language.french') }}</a>
                <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}"
                   aria-current="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">{{ __('language.english') }}</a>
            </nav>
        </header>

        @yield('content')

        <footer class="site-footer">
            <a href="{{ route('services.index') }}">{{ __('public.all_services') }}</a>
        </footer>
    </div>
</body>
</html>
