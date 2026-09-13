@extends('layouts.public')

@section('title', $skill->name($locale).' · '.__('app.name'))
@section('description', __('public.service_description', ['service' => $skill->name($locale)]))

@push('structured-data')
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    <section class="pt-12 pb-10 lg:pt-16">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <nav class="flex flex-wrap items-center gap-2 text-sm text-content-muted" aria-label="{{ __('public.nav_label') }}">
                <a class="text-content-muted no-underline hover:text-content" href="{{ route('home') }}">{{ __('app.name') }}</a>
                <span class="text-content-faint" aria-hidden="true">&rsaquo;</span>
                <a class="text-content-muted no-underline hover:text-content" href="{{ route('services.index') }}">{{ __('public.services_title') }}</a>
                @if ($parent)
                    <span class="text-content-faint" aria-hidden="true">&rsaquo;</span>
                    <a class="text-content-muted no-underline hover:text-content" href="{{ route('services.show', ['slug' => $parent->slug]) }}">{{ $parent->name($locale) }}</a>
                @endif
                <span class="text-content-faint" aria-hidden="true">&rsaquo;</span>
                <span class="text-content-tertiary">{{ $skill->name($locale) }}</span>
            </nav>

            <div class="mt-6 flex flex-wrap items-end justify-between gap-6">
                <div class="max-w-[44rem]">
                    <h1 class="title text-[clamp(2.1rem,1.4rem+2.2vw,2.875rem)] tracking-[-0.038em]">{{ $skill->name($locale) }}</h1>
                    <p class="lede mt-4">{{ __('public.service_description', ['service' => $skill->name($locale)]) }}</p>
                </div>
                {{-- A leaf is a thing one can be quoted for, so its button opens the request form. A
                     category's button explains how it works instead — the request has to name a trade.
                     The page's one filled button. --}}
                <a class="btn btn-primary" href="{{ $skill->is_leaf ? route('services.request', ['slug' => $skill->slug]) : route('home').'#how' }}">
                    {{ __('public.trade_cta') }}
                    <span class="[&_svg]:size-4" aria-hidden="true">@include('public.partials.icon', ['name' => 'arrow-right'])</span>
                </a>
            </div>
        </div>
    </section>

    @if ($children->isNotEmpty())
        <section class="pb-12">
            <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
                <h2 class="text-[1.1875rem] font-bold leading-tight tracking-[-0.02em]">{{ __('public.in_this_category') }}</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($children as $leaf)
                        <a class="card card-interactive block p-6 text-inherit no-underline hover:text-inherit"
                           href="{{ route('services.show', ['slug' => $leaf->slug]) }}">
                            <span class="icon-tile icon-tile-neutral" aria-hidden="true">@include('public.partials.icon', ['name' => $parent?->slug ?? $skill->slug])</span>
                            <h3 class="mt-6 text-[1.1rem] font-bold leading-tight tracking-[-0.015em]">{{ $leaf->name($locale) }}</h3>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($skill->is_leaf)
        <section class="pb-12">
            <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
                <h2 class="text-[1.1875rem] font-bold leading-tight tracking-[-0.02em]">{{ __('public.providers_heading') }}</h2>
                <p class="mt-1.5 max-w-[44rem] text-[0.9rem] text-content-muted">{{ __('public.trade_providers_lede') }}</p>

                {{-- PII: every visitor here is anonymous and pre-engagement, so this shows exactly
                     what the API's match list shows — the public headline, verification and rating.
                     Never a display name, never a service area. --}}
                @if ($providers->isNotEmpty())
                    <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($providers as $provider)
                            <div class="card p-6">
                                <span class="icon-tile icon-tile-neutral" aria-hidden="true">@include('public.partials.icon', ['name' => 'badge'])</span>
                                <h3 class="mt-6 text-[1.1rem] font-bold leading-tight tracking-[-0.015em]">{{ $provider->headline ?: $skill->name($locale) }}</h3>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @if ($provider->verification_tier >= 2)
                                        <span class="pill pill-accent [&_svg]:size-3.5">
                                            @include('public.partials.icon', ['name' => 'shield'])
                                            {{ __('public.tier_label', ['n' => $provider->verification_tier]) }}
                                        </span>
                                    @endif
                                    @if ($provider->rating_avg !== null && $provider->rating_count > 0)
                                        <span class="pill pill-neutral [&_svg]:size-3.5 [&_svg]:text-warning">
                                            @include('public.partials.icon', ['name' => 'star'])
                                            {{ __('public.rating_summary', [
                                                'rating' => number_format((float) $provider->rating_avg, 1),
                                                'count' => $provider->rating_count,
                                            ]) }}
                                        </span>
                                    @else
                                        <span class="pill pill-neutral">{{ __('public.no_rating') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- An empty trade is not a dead end: the request is what creates supply, so the
                         page asks for one rather than apologising. The CTA is already at the top of
                         this page, so repeating it a screen later would be the same ask twice. --}}
                    <div class="card mt-6 rounded-[24px] p-8">
                        <span class="icon-tile size-12 rounded-[15px]" aria-hidden="true">@include('public.partials.icon', ['name' => 'user-plus'])</span>
                        <h3 class="mt-5 text-[1.1875rem] font-bold leading-tight tracking-[-0.02em]">{{ __('public.no_providers_yet') }}</h3>
                        <p class="mt-2 max-w-[66ch] text-[0.95rem] leading-relaxed text-content-muted">{{ __('public.no_providers_body') }}</p>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($parent && $parent->children->where('id', '!=', $skill->id)->isNotEmpty())
        <section class="pb-20">
            <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
                <h2 class="text-[1.1875rem] font-bold leading-tight tracking-[-0.02em]">{{ __('public.trade_in_category') }}</h2>
                <ul class="mt-4 flex flex-wrap gap-2 list-none p-0 m-0">
                    @foreach ($parent->children->where('id', '!=', $skill->id) as $sibling)
                        <li>
                            <a class="chip" href="{{ route('services.show', ['slug' => $sibling->slug]) }}">{{ $sibling->name($locale) }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
