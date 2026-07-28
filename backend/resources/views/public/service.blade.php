@extends('layouts.public')

@section('title', $skill->name($locale).' · '.__('app.name'))
@section('description', __('public.service_description', ['service' => $skill->name($locale)]))

@push('structured-data')
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    <nav class="crumbs">
        <a href="{{ route('services.index') }}">{{ __('public.services_title') }}</a>
        @if ($parent)
            &rsaquo; <a href="{{ route('services.show', ['slug' => $parent->slug]) }}">{{ $parent->name($locale) }}</a>
        @endif
    </nav>

    <h1>{{ $skill->name($locale) }}</h1>
    <p class="lede">{{ __('public.service_description', ['service' => $skill->name($locale)]) }}</p>

    @if ($children->isNotEmpty())
        <section class="cat">
            <h2>{{ __('public.in_this_category') }}</h2>
            <ul class="links">
                @foreach ($children as $leaf)
                    <li><a href="{{ route('services.show', ['slug' => $leaf->slug]) }}">{{ $leaf->name($locale) }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($skill->is_leaf)
        <section class="cat">
            <h2>{{ __('public.providers_heading') }}</h2>

            {{-- PII: every visitor here is anonymous and pre-engagement, so this shows exactly what
                 the API's match list shows — the public headline, verification and rating. Never a
                 display name, never a service area. --}}
            @if ($providers->isNotEmpty())
                <ul class="providers">
                    @foreach ($providers as $provider)
                        <li class="provider">
                            <span class="name">{{ $provider->headline ?: $skill->name($locale) }}</span>
                            @if ($provider->verification_tier >= 2)
                                <span class="badge">{{ __('discover.verified') }}</span>
                            @endif
                            @if ($provider->rating_avg !== null && $provider->rating_count > 0)
                                <span class="badge">★ {{ number_format((float) $provider->rating_avg, 1) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="empty">{{ __('public.no_providers_yet') }}</p>
            @endif
        </section>
    @endif
@endsection
