@extends('layouts.public')

@section('title', $skill->name($locale).' · '.__('app.name'))
@section('description', __('public.service_description', ['service' => $skill->name($locale)]))

@push('structured-data')
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    <section class="section-sm">
        <div class="wrap">
            <nav class="crumbs" aria-label="{{ __('public.nav_label') }}">
                <a href="{{ route('home') }}">{{ __('app.name') }}</a>
                <span aria-hidden="true">&rsaquo;</span>
                <a href="{{ route('services.index') }}">{{ __('public.services_title') }}</a>
                @if ($parent)
                    <span aria-hidden="true">&rsaquo;</span>
                    <a href="{{ route('services.show', ['slug' => $parent->slug]) }}">{{ $parent->name($locale) }}</a>
                @endif
            </nav>

            <div class="measure" style="margin-top: var(--hm-space-md);">
                <h1 class="t-h2">{{ $skill->name($locale) }}</h1>
                <p class="t-lede" style="margin-top: var(--hm-space-sm);">
                    {{ __('public.service_description', ['service' => $skill->name($locale)]) }}
                </p>
                <div class="btn-row" style="margin-top: var(--hm-space-lg);">
                    <a class="btn btn-primary" href="{{ route('home') }}#how">{{ __('public.trade_cta') }}</a>
                </div>
            </div>
        </div>
    </section>

    @if ($children->isNotEmpty())
        <section class="section-sm">
            <div class="wrap">
                <h2 class="t-h3">{{ __('public.in_this_category') }}</h2>
                <div class="grid grid-4" style="margin-top: var(--hm-space-md);">
                    @foreach ($children as $leaf)
                        <a class="card stack-sm" href="{{ route('services.show', ['slug' => $leaf->slug]) }}">
                            <span class="icon-badge" aria-hidden="true">
                                @include('public.partials.icon', ['name' => 'tool'])
                            </span>
                            <h3 class="t-h3">{{ $leaf->name($locale) }}</h3>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($skill->is_leaf)
        <section class="section-sm">
            <div class="wrap">
                <h2 class="t-h3">{{ __('public.providers_heading') }}</h2>
                <p class="t-small muted measure" style="margin-top: 0.35rem;">{{ __('public.trade_providers_lede') }}</p>

                {{-- PII: every visitor here is anonymous and pre-engagement, so this shows exactly
                     what the API's match list shows — the public headline, verification and rating.
                     Never a display name, never a service area. --}}
                @if ($providers->isNotEmpty())
                    <div class="grid grid-3" style="margin-top: var(--hm-space-lg);">
                        @foreach ($providers as $provider)
                            <div class="card stack-sm">
                                <span class="icon-badge" aria-hidden="true">
                                    @include('public.partials.icon', ['name' => 'badge'])
                                </span>
                                <h3 class="t-h3">{{ $provider->headline ?: $skill->name($locale) }}</h3>
                                <div class="chips" style="gap: 0.4rem;">
                                    @if ($provider->verification_tier >= 2)
                                        <span class="pill">
                                            @include('public.partials.icon', ['name' => 'shield'])
                                            {{ __('public.tier_label', ['n' => $provider->verification_tier]) }}
                                        </span>
                                    @endif
                                    @if ($provider->rating_avg !== null && $provider->rating_count > 0)
                                        <span class="pill pill-quiet">
                                            {{ __('public.rating_summary', [
                                                'rating' => number_format((float) $provider->rating_avg, 1),
                                                'count' => $provider->rating_count,
                                            ]) }}
                                        </span>
                                    @else
                                        <span class="pill pill-quiet">{{ __('public.no_rating') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- An empty trade is not a dead end: the request is what creates supply, so the
                         page asks for one rather than apologising. --}}
                    {{-- The CTA is already at the top of this page; repeating it a screen later
                         would be the same ask twice, so the empty state explains instead. --}}
                    <div class="card" style="margin-top: var(--hm-space-lg);">
                        <h3 class="t-h3">{{ __('public.no_providers_yet') }}</h3>
                        <p class="t-small muted" style="margin: var(--hm-space-sm) 0 0;">{{ __('public.no_providers_body') }}</p>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($parent && $parent->children->isNotEmpty())
        <section class="section-sm">
            <div class="wrap">
                <h2 class="t-h3">{{ __('public.trade_in_category') }}</h2>
                <ul class="chips" style="margin-top: var(--hm-space-md);">
                    @foreach ($parent->children->where('id', '!=', $skill->id) as $sibling)
                        <li><a href="{{ route('services.show', ['slug' => $sibling->slug]) }}">{{ $sibling->name($locale) }}</a></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
