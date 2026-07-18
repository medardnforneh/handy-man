@extends('layouts.public')

@section('content')
    <section class="hero">
        <h1>{{ __('app.name') }}</h1>
        <p>{{ __('app.tagline') }}</p>
        {{-- A customer must be able to find a provider and request a quote without loading the
             app bundle (CLAUDE.md) — this Blade surface is the crawlable entry point. --}}
        <a class="cta" href="{{ request()->fullUrlWithQuery(['lang' => app()->getLocale()]) }}#start">{{ __('common.continue') }}</a>
    </section>

    {{-- Both sides of the marketplace are visible to everyone (doc 10). --}}
    <section class="sections" id="start">
        <div class="card">
            <h2>{{ __('nav.customer') }}</h2>
        </div>
        <div class="card">
            <h2>{{ __('nav.provider') }}</h2>
        </div>
    </section>
@endsection
