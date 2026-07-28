@extends('layouts.public')

@section('content')
    <section class="hero">
        <h1>{{ __('app.name') }}</h1>
        <p>{{ __('app.tagline') }}</p>
        {{-- A customer must be able to find a provider and request a quote without loading the
             app bundle (CLAUDE.md) — this Blade surface is the crawlable entry point. --}}
        {{-- The directory is the real entry point: a crawlable page per trade, in both languages. --}}
        <a class="cta" href="{{ route('services.index') }}">{{ __('public.services_title') }}</a>
    </section>

    {{-- Both sides of the marketplace are visible to everyone (doc 10). --}}
    <section class="sections" id="start">
        <div class="card">
            <h2>{{ __('nav.customer') }}</h2>
            <p class="lede">{{ __('public.services_description') }}</p>
            <a href="{{ route('services.index') }}">{{ __('public.all_services') }}</a>
        </div>
        <div class="card">
            <h2>{{ __('nav.provider') }}</h2>
            <p class="lede">{{ __('app.tagline') }}</p>
        </div>
    </section>
@endsection
