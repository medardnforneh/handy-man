@extends('layouts.public')

@section('title', __('public.services_title').' · '.__('app.name'))
@section('description', __('public.services_description'))

@push('structured-data')
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    <section class="pt-12 pb-10 lg:pt-16">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <nav class="flex flex-wrap items-center gap-2 text-sm text-content-muted" aria-label="{{ __('public.nav_label') }}">
                <a class="text-content-muted no-underline hover:text-content" href="{{ route('home') }}">{{ __('app.name') }}</a>
                <span class="text-content-faint" aria-hidden="true">&rsaquo;</span>
                <span class="text-content-tertiary">{{ __('public.services_title') }}</span>
            </nav>

            <div class="mt-6 max-w-[44rem]">
                <h1 class="title text-[clamp(2.1rem,1.4rem+2.2vw,2.875rem)] tracking-[-0.038em]">{{ __('public.services_title') }}</h1>
                <p class="lede mt-4">{{ __('public.directory_lede') }}</p>
            </div>
        </div>
    </section>

    <section class="pb-20">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            @if ($categories->isNotEmpty())
                {{-- One card per category with its trades as chips: the whole taxonomy stays
                     scannable on one page, and every leaf is a crawlable link in both languages. --}}
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($categories as $category)
                        <section class="card p-6">
                            <div class="flex items-center gap-4">
                                <span class="icon-tile icon-tile-neutral" aria-hidden="true">@include('public.partials.icon', ['name' => $category->slug])</span>
                                <div class="min-w-0 flex-1">
                                    <h2 class="text-[1.1875rem] font-bold leading-tight tracking-[-0.02em]">
                                        <a class="text-inherit no-underline hover:text-brand" href="{{ route('services.show', ['slug' => $category->slug]) }}">{{ $category->name($locale) }}</a>
                                    </h2>
                                    <span class="text-[0.8125rem] text-content-muted">{{ trans_choice('public.trades_count', $category->children->count(), ['count' => $category->children->count()]) }}</span>
                                </div>
                                <a class="grid size-9 shrink-0 place-items-center rounded-[11px] text-content-muted no-underline hover:bg-surface-sunken hover:text-content [&_svg]:size-[18px]"
                                   href="{{ route('services.show', ['slug' => $category->slug]) }}" aria-label="{{ $category->name($locale) }}">
                                    @include('public.partials.icon', ['name' => 'arrow-right'])
                                </a>
                            </div>

                            @if ($category->children->isNotEmpty())
                                <ul class="mt-5 flex flex-wrap gap-2 list-none p-0 m-0">
                                    @foreach ($category->children->sortBy(fn ($leaf) => $leaf->name($locale)) as $leaf)
                                        <li>
                                            <a class="chip" href="{{ route('services.show', ['slug' => $leaf->slug]) }}">{{ $leaf->name($locale) }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @endforeach
                </div>
            @else
                <p class="text-content-muted">{{ __('public.no_services') }}</p>
            @endif
        </div>
    </section>

    {{-- The closing panel — the one place the accent runs as a field (see home.blade.php). --}}
    <section class="pb-20 lg:pb-24">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="flex flex-wrap items-center justify-between gap-8 rounded-[32px] bg-brand px-8 py-14 text-brand-contrast lg:px-16 lg:py-16">
                <div class="max-w-[50ch]">
                    <h2 class="text-[clamp(2rem,1.4rem+2.4vw,2.875rem)] font-extrabold leading-[1.05] tracking-[-0.038em] text-brand-contrast">{{ __('public.cta_title') }}</h2>
                    <p class="mt-4 text-[1.0625rem] leading-relaxed text-brand-contrast/80">{{ __('public.cta_lede') }}</p>
                </div>
                <a class="btn btn-on-accent" href="{{ route('home') }}#how">
                    {{ __('public.trade_cta') }}
                    <span class="[&_svg]:size-4" aria-hidden="true">@include('public.partials.icon', ['name' => 'arrow-right'])</span>
                </a>
            </div>
        </div>
    </section>
@endsection
