<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('app.name'))</title>
    <meta name="description" content="{{ __('app.tagline') }}">

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
    </div>
</body>
</html>
