@extends('layouts.public')

@section('title', __('public.services_title').' · '.__('app.name'))
@section('description', __('public.services_description'))

@push('structured-data')
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    <section class="section-sm">
        <div class="wrap">
            <nav class="crumbs" aria-label="{{ __('public.nav_label') }}">
                <a href="{{ route('home') }}">{{ __('app.name') }}</a>
                <span aria-hidden="true">&rsaquo;</span>
                <span>{{ __('public.services_title') }}</span>
            </nav>

            <div class="measure" style="margin-top: var(--hm-space-md);">
                <h1 class="t-h2">{{ __('public.services_title') }}</h1>
                <p class="t-lede" style="margin-top: var(--hm-space-sm);">{{ __('public.directory_lede') }}</p>
            </div>
        </div>
    </section>

    <section class="section-sm">
        <div class="wrap">
            @forelse ($categories as $category)
                {{-- One card per category with its trades as chips: the whole taxonomy stays
                     scannable on one page, and every leaf is a crawlable link in both languages. --}}
                <section class="card" style="margin-bottom: var(--hm-space-md);">
                    <div style="display:flex; align-items:center; gap: var(--hm-space-md);">
                        <span class="icon-badge" aria-hidden="true">
                            @include('public.partials.icon', ['name' => 'tool'])
                        </span>
                        <div>
                            <h2 class="t-h3">
                                <a href="{{ route('services.show', ['slug' => $category->slug]) }}"
                                   style="color:inherit; text-decoration:none;">{{ $category->name($locale) }}</a>
                            </h2>
                            <span class="t-small muted">{{ trans_choice('public.trades_count', $category->children->count(), ['count' => $category->children->count()]) }}</span>
                        </div>
                    </div>

                    @if ($category->children->isNotEmpty())
                        <ul class="chips" style="margin-top: var(--hm-space-md);">
                            @foreach ($category->children->sortBy(fn ($leaf) => $leaf->name($locale)) as $leaf)
                                <li><a href="{{ route('services.show', ['slug' => $leaf->slug]) }}">{{ $leaf->name($locale) }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @empty
                <p class="muted">{{ __('public.no_services') }}</p>
            @endforelse
        </div>
    </section>

    <section class="section-sm">
        <div class="wrap">
            <div class="card center" style="padding-block: var(--hm-space-2xl);">
                <h2 class="t-h2">{{ __('public.cta_title') }}</h2>
                <p class="t-lede measure-center" style="margin-top: var(--hm-space-sm);">{{ __('public.cta_lede') }}</p>
                <div class="btn-row" style="justify-content:center; margin-top: var(--hm-space-lg);">
                    <a class="btn btn-primary" href="{{ route('home') }}#how">{{ __('public.trade_cta') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
