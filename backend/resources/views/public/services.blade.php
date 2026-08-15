@extends('layouts.public')

@section('title', __('public.services_title').' · '.__('app.name'))
@section('description', __('public.services_description'))

@push('structured-data')
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    <section class="py-10">
        <div class="w-full max-w-6xl mx-auto px-6">
            <nav class="flex flex-wrap items-center gap-1.5 text-sm text-content-muted" aria-label="{{ __('public.nav_label') }}">
                <a class="text-content-muted no-underline hover:text-content hover:underline" href="{{ route('home') }}">{{ __('app.name') }}</a>
                <span aria-hidden="true">&rsaquo;</span>
                <span>{{ __('public.services_title') }}</span>
            </nav>

            <div class="max-w-[44rem] mt-4">
                <h1 class="text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ __('public.services_title') }}</h1>
                <p class="mt-2 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)] text-content-muted">{{ __('public.directory_lede') }}</p>
            </div>
        </div>
    </section>

    <section class="pb-10">
        <div class="w-full max-w-6xl mx-auto px-6">
            @forelse ($categories as $category)
                {{-- One card per category with its trades as chips: the whole taxonomy stays
                     scannable on one page, and every leaf is a crawlable link in both languages. --}}
                <section class="mb-4 rounded-lg border border-edge bg-surface-raised p-6">
                    <div class="flex items-center gap-4">
                        <span class="grid place-items-center shrink-0 size-10 rounded-md bg-brand-tint text-brand [&_svg]:size-5" aria-hidden="true">
                            @include('public.partials.icon', ['name' => 'tool'])
                        </span>
                        <div>
                            <h2 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">
                                <a class="text-inherit no-underline hover:text-brand" href="{{ route('services.show', ['slug' => $category->slug]) }}">{{ $category->name($locale) }}</a>
                            </h2>
                            <span class="text-sm text-content-muted">{{ trans_choice('public.trades_count', $category->children->count(), ['count' => $category->children->count()]) }}</span>
                        </div>
                    </div>

                    @if ($category->children->isNotEmpty())
                        <ul class="flex flex-wrap gap-2 mt-4 list-none p-0 m-0">
                            @foreach ($category->children->sortBy(fn ($leaf) => $leaf->name($locale)) as $leaf)
                                <li>
                                    <a class="inline-block rounded-pill border border-edge bg-surface-raised px-3.5 py-2 text-sm text-content no-underline transition-colors hover:border-brand hover:text-brand"
                                       href="{{ route('services.show', ['slug' => $leaf->slug]) }}">{{ $leaf->name($locale) }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @empty
                <p class="text-content-muted">{{ __('public.no_services') }}</p>
            @endforelse
        </div>
    </section>

    <section class="py-10">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="rounded-lg border border-edge bg-surface-raised px-6 py-16 text-center">
                <h2 class="text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ __('public.cta_title') }}</h2>
                <p class="max-w-[44rem] mx-auto mt-2 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)] text-content-muted">{{ __('public.cta_lede') }}</p>
                <div class="flex flex-wrap justify-center gap-2 mt-6">
                    <a class="inline-flex items-center justify-center gap-2 min-h-11 rounded-md border border-transparent bg-brand px-[1.15rem] py-3 text-[0.95rem] font-semibold text-brand-contrast no-underline shadow-sm transition hover:bg-brand-strong hover:-translate-y-px hover:shadow-md"
                       href="{{ route('home') }}#how">{{ __('public.trade_cta') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
