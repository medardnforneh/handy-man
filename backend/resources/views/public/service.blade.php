@extends('layouts.public')

@section('title', $skill->name($locale).' · '.__('app.name'))
@section('description', __('public.service_description', ['service' => $skill->name($locale)]))

@push('structured-data')
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    <section class="py-10">
        <div class="w-full max-w-6xl mx-auto px-6">
            <nav class="flex flex-wrap items-center gap-1.5 text-sm text-content-muted" aria-label="{{ __('public.nav_label') }}">
                <a class="text-content-muted no-underline hover:text-content hover:underline" href="{{ route('home') }}">{{ __('app.name') }}</a>
                <span aria-hidden="true">&rsaquo;</span>
                <a class="text-content-muted no-underline hover:text-content hover:underline" href="{{ route('services.index') }}">{{ __('public.services_title') }}</a>
                @if ($parent)
                    <span aria-hidden="true">&rsaquo;</span>
                    <a class="text-content-muted no-underline hover:text-content hover:underline" href="{{ route('services.show', ['slug' => $parent->slug]) }}">{{ $parent->name($locale) }}</a>
                @endif
            </nav>

            <div class="max-w-[44rem] mt-4">
                <h1 class="text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ $skill->name($locale) }}</h1>
                <p class="mt-2 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)] text-content-muted">
                    {{ __('public.service_description', ['service' => $skill->name($locale)]) }}
                </p>
                <div class="flex flex-wrap gap-2 mt-6">
                    <a class="inline-flex items-center justify-center gap-2 min-h-11 rounded-md border border-transparent bg-brand px-[1.15rem] py-3 text-[0.95rem] font-extrabold text-brand-contrast no-underline transition-colors hover:bg-brand-strong"
                       href="{{ route('home') }}#how">{{ __('public.trade_cta') }}</a>
                </div>
            </div>
        </div>
    </section>

    @if ($children->isNotEmpty())
        <section class="pb-10">
            <div class="w-full max-w-6xl mx-auto px-6">
                <h2 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">{{ __('public.in_this_category') }}</h2>
                <div class="grid gap-4 mt-4 grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))]">
                    @foreach ($children as $leaf)
                        <a class="block space-y-2 bg-surface-raised p-6 text-inherit no-underline transition-colors hover:bg-surface-sunken"
                           href="{{ route('services.show', ['slug' => $leaf->slug]) }}">
                            <span class="grid place-items-center shrink-0 size-10 rounded-md bg-brand-tint text-brand [&_svg]:size-5" aria-hidden="true">
                                @include('public.partials.icon', ['name' => 'tool'])
                            </span>
                            <h3 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">{{ $leaf->name($locale) }}</h3>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($skill->is_leaf)
        <section class="pb-10">
            <div class="w-full max-w-6xl mx-auto px-6">
                <h2 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">{{ __('public.providers_heading') }}</h2>
                <p class="max-w-[44rem] mt-1.5 text-sm text-content-muted">{{ __('public.trade_providers_lede') }}</p>

                {{-- PII: every visitor here is anonymous and pre-engagement, so this shows exactly
                     what the API's match list shows — the public headline, verification and rating.
                     Never a display name, never a service area. --}}
                @if ($providers->isNotEmpty())
                    <div class="grid gap-4 mt-6 grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))]">
                        @foreach ($providers as $provider)
                            <div class="space-y-2 bg-surface-raised p-6">
                                <span class="grid place-items-center shrink-0 size-10 rounded-md bg-brand-tint text-brand [&_svg]:size-5" aria-hidden="true">
                                    @include('public.partials.icon', ['name' => 'badge'])
                                </span>
                                <h3 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">{{ $provider->headline ?: $skill->name($locale) }}</h3>
                                <div class="flex flex-wrap gap-1.5">
                                    @if ($provider->verification_tier >= 2)
                                        <span class="inline-flex items-center gap-1 bg-brand-tint px-2.5 py-1 text-xs font-semibold text-brand-strong [&_svg]:size-3.5">
                                            @include('public.partials.icon', ['name' => 'shield'])
                                            {{ __('public.tier_label', ['n' => $provider->verification_tier]) }}
                                        </span>
                                    @endif
                                    @if ($provider->rating_avg !== null && $provider->rating_count > 0)
                                        <span class="inline-flex items-center gap-1 rounded-pill bg-surface-sunken px-2.5 py-1 text-xs font-semibold text-content-muted">
                                            {{ __('public.rating_summary', [
                                                'rating' => number_format((float) $provider->rating_avg, 1),
                                                'count' => $provider->rating_count,
                                            ]) }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-pill bg-surface-sunken px-2.5 py-1 text-xs font-semibold text-content-muted">{{ __('public.no_rating') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- An empty trade is not a dead end: the request is what creates supply, so the
                         page asks for one rather than apologising. The CTA is already at the top of
                         this page, so repeating it a screen later would be the same ask twice. --}}
                    <div class="mt-6 bg-surface-raised p-6">
                        <h3 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">{{ __('public.no_providers_yet') }}</h3>
                        <p class="mt-2 text-sm text-content-muted">{{ __('public.no_providers_body') }}</p>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($parent && $parent->children->isNotEmpty())
        <section class="pb-10">
            <div class="w-full max-w-6xl mx-auto px-6">
                <h2 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">{{ __('public.trade_in_category') }}</h2>
                <ul class="flex flex-wrap gap-2 mt-4 list-none p-0 m-0">
                    @foreach ($parent->children->where('id', '!=', $skill->id) as $sibling)
                        <li>
                            <a class="inline-block bg-surface-raised px-3.5 py-2 text-sm text-content no-underline transition-colors hover:bg-surface-sunken"
                               href="{{ route('services.show', ['slug' => $sibling->slug]) }}">{{ $sibling->name($locale) }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
