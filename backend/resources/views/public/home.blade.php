@extends('layouts.public')

@section('title', __('public.home_title'))
@section('description', __('public.home_description'))

@push('structured-data')
    <script type="application/ld+json">@json($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
@endpush

@section('content')
    {{-- ── Hero ──────────────────────────────────────────────────────────────────────────────
         The promise, then the two doors. Both sides of the marketplace are visible to everyone
         (doc 10) — there is no "sign up as a provider" fork, because the same person is routinely
         both, so the second CTA leads into the same product rather than a separate one.

         Layout from the handoff: 1fr / 460px, gap 72, padding 92/84. The accent is spent on the
         one filled button and the escrow card's marks; the ground stays the ground. --}}
    <section class="py-16 lg:py-[5.75rem] lg:pb-[5.25rem]">
        <div class="mx-auto grid w-full max-w-[1440px] items-center gap-12 px-5 sm:px-8 lg:grid-cols-[1fr_460px] lg:gap-[72px] lg:px-16">
            <div>
                <span class="pill pill-accent">
                    <span class="size-1.5 rounded-pill bg-brand" aria-hidden="true"></span>
                    {{ __('public.hero_eyebrow') }} · {{ __('public.hero_country') }}
                </span>
                <h1 class="display mt-5">{{ __('public.hero_title') }}</h1>
                <p class="lede mt-5 max-w-[54ch] text-[1.125rem]">{{ __('public.hero_lede') }}</p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a class="btn btn-primary" href="{{ route('services.index') }}">
                        {{ __('public.hero_cta_primary') }}
                        <span class="[&_svg]:size-4" aria-hidden="true">@include('public.partials.icon', ['name' => 'arrow-right'])</span>
                    </a>
                    <a class="btn btn-secondary" href="#providers">{{ __('public.hero_cta_secondary') }}</a>
                </div>

                @if ($popularTrades->isNotEmpty())
                    <p class="micro mt-9">{{ __('public.hero_popular') }}</p>
                    <ul class="mt-3 flex flex-wrap gap-2 list-none p-0 m-0">
                        @foreach ($popularTrades as $trade)
                            <li>
                                <a class="chip" href="{{ route('services.show', ['slug' => $trade->slug]) }}">{{ $trade->name($locale) }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- The hero visual is the product itself rather than a stock photograph: an escrow-
                 backed engagement, mid-flight — "the whole value proposition in one object". It is
                 decorative markup (the same facts are stated in words above and below), so it is
                 hidden from assistive technology instead of being read out as a fake job. Hidden
                 on small screens, where the copy and the CTA are what matter. --}}
            <div class="hidden lg:block" aria-hidden="true">
                <div class="rounded-xl border border-edge-strong bg-surface-raised p-7 shadow-lg">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[0.95rem] font-bold">{{ __('public.hero_card_ref') }}</span>
                        <span class="pill pill-warn">
                            <span class="size-1.5 rounded-pill bg-warning" aria-hidden="true"></span>
                            {{ __('public.hero_card_status') }}
                        </span>
                    </div>

                    <div class="mt-5 flex items-center gap-3">
                        <span class="monogram size-[46px] rounded-[15px] text-[0.8rem]">{{ __('public.hero_card_initials') }}</span>
                        <div>
                            <div class="text-[0.95rem] font-bold">{{ __('public.hero_card_provider') }}</div>
                            <div class="text-[0.85rem] text-content-muted">{{ __('public.hero_card_trade') }}</div>
                        </div>
                    </div>

                    <div class="mt-5 flex items-center gap-3.5 rounded-md border border-brand/25 bg-brand/10 p-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-[13px] bg-brand text-brand-contrast [&_svg]:size-5">
                            @include('public.partials.icon', ['name' => 'shield'])
                        </span>
                        <div>
                            <div class="text-[0.8rem] text-content-muted">{{ __('public.hero_card_escrow_label') }}</div>
                            <div class="text-2xl font-extrabold tracking-[-0.035em]">{{ __('public.hero_card_amount') }}</div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-2.5">
                        @foreach ([
                            ['public.hero_card_step_deposit', true],
                            ['public.hero_card_step_work', true],
                            ['public.hero_card_step_approve', false],
                        ] as [$stepKey, $done])
                            <div @class([
                                'flex items-center gap-3 text-[0.9rem] font-semibold',
                                'text-content' => $done,
                                'text-content-muted' => ! $done,
                            ])>
                                <span @class([
                                    'grid size-6 shrink-0 place-items-center rounded-pill [&_svg]:size-3.5',
                                    'bg-brand text-brand-contrast' => $done,
                                    'border-2 border-edge-strong' => ! $done,
                                ])>@if ($done)@include('public.partials.icon', ['name' => 'check'])@endif</span>
                                <span>{{ __($stepKey) }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 rounded-md bg-brand py-3.5 text-center text-[0.95rem] font-bold text-brand-contrast">{{ __('public.hero_card_button') }}</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Trust strip ───────────────────────────────────────────────────────────────────────
         Four claims, each of which the platform actually implements: escrow is the ledger, the
         tier is an approved ID document, the warranty spawns a real remedy job, and MoMo/OM are
         the only rails we take money on. A rail-coloured band between two lines. --}}
    <section class="border-y border-edge bg-surface-rail py-13" id="trust">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="grid gap-9 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['shield', 'public.trust_escrow_title', 'public.trust_escrow_body'],
                    ['badge', 'public.trust_verified_title', 'public.trust_verified_body'],
                    ['refresh', 'public.trust_warranty_title', 'public.trust_warranty_body'],
                    ['phone', 'public.trust_momo_title', 'public.trust_momo_body'],
                ] as [$icon, $title, $body])
                    <div>
                        <span class="icon-tile" aria-hidden="true">@include('public.partials.icon', ['name' => $icon])</span>
                        {{-- h2, not h3: these sit directly under the page's h1. --}}
                        <h2 class="mt-5 text-[1.0625rem] font-bold leading-tight tracking-[-0.015em]">{{ __($title) }}</h2>
                        <p class="mt-2 text-sm leading-relaxed text-content-muted">{{ __($body) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── How it works ─────────────────────────────────────────────────────────────────────── --}}
    <section class="py-20 lg:py-24" id="how">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="max-w-[40rem]">
                <span class="eyebrow">{{ __('public.how_eyebrow') }}</span>
                <h2 class="title mt-4">{{ __('public.how_title') }}</h2>
                <p class="lede mt-4">{{ __('public.how_lede') }}</p>
            </div>

            <ol class="mt-10 grid list-none gap-4 p-0 md:grid-cols-3">
                @foreach (['describe', 'compare', 'pay'] as $i => $step)
                    <li class="card rounded-[24px] p-8">
                        <span class="pill pill-accent">{{ __('public.how_step', ['n' => $i + 1]) }}</span>
                        <h3 class="mt-5 text-[1.375rem] font-bold leading-tight tracking-[-0.025em]">{{ __('public.how_'.$step.'_title') }}</h3>
                        <p class="mt-3 text-[0.95rem] leading-relaxed text-content-muted">{{ __('public.how_'.$step.'_body') }}</p>
                    </li>
                @endforeach
            </ol>

            <p class="mt-8 text-[0.9rem] text-content-muted">{{ __('public.how_remote_note') }}</p>
        </div>
    </section>

    {{-- ── Trades directory ──────────────────────────────────────────────────────────────────
         The categories are the SEO surface (P1-07): real trades, in both languages, each a
         crawlable page. Counting the leaves is honest — it is the size of the taxonomy, not a
         claim about how many providers are signed up. --}}
    @if ($categories->isNotEmpty())
        <section class="pb-20 lg:pb-24" id="trades">
            <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-[40rem]">
                        <span class="eyebrow">{{ __('public.trades_eyebrow') }}</span>
                        <h2 class="title mt-4">{{ __('public.trades_title') }}</h2>
                    </div>
                    <a class="btn btn-secondary" href="{{ route('services.index') }}">
                        {{ __('public.all_services') }}
                        <span class="[&_svg]:size-4" aria-hidden="true">@include('public.partials.icon', ['name' => 'arrow-right'])</span>
                    </a>
                </div>

                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($categories as $category)
                        <a class="card card-interactive block p-6 text-inherit no-underline hover:text-inherit"
                           href="{{ route('services.show', ['slug' => $category->slug]) }}">
                            {{-- The slug doubles as the icon name; an unknown trade falls back to
                                 the generic tool rather than rendering nothing. --}}
                            <span class="icon-tile" aria-hidden="true">@include('public.partials.icon', ['name' => $category->slug])</span>
                            <h3 class="mt-7 text-[1.1rem] font-bold leading-tight tracking-[-0.015em]">{{ $category->name($locale) }}</h3>
                            <span class="mt-1.5 block text-[0.85rem] text-content-muted">{{ trans_choice('public.trades_count', $category->children_count, ['count' => $category->children_count]) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ── Providers ─────────────────────────────────────────────────────────────────────────
         A surface-1 band addresses the other side of the marketplace. The claims are the ones
         the product can defend — a client book that stays yours, cash recorded rather than
         punished, and payouts to the same MoMo number. --}}
    <section class="border-y border-edge bg-surface-raised py-20 lg:py-24" id="providers">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <div>
                    <span class="eyebrow">{{ __('public.pro_eyebrow') }}</span>
                    <h2 class="title mt-4">{{ __('public.pro_title') }}</h2>
                    <p class="lede mt-5 max-w-[46ch]">{{ __('public.pro_lede') }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a class="btn btn-primary" href="{{ route('services.index') }}">
                            {{ __('public.pro_cta') }}
                            <span class="[&_svg]:size-4" aria-hidden="true">@include('public.partials.icon', ['name' => 'arrow-right'])</span>
                        </a>
                    </div>
                </div>

                <div class="grid gap-3">
                    @foreach (['leads', 'paid', 'cash', 'tools'] as $benefit)
                        <div class="flex items-start gap-4 rounded-[20px] border border-edge bg-surface-rail p-5">
                            <span class="grid size-10 shrink-0 place-items-center rounded-[13px] bg-brand-tint text-brand [&_svg]:size-5" aria-hidden="true">
                                @include('public.partials.icon', ['name' => 'check'])
                            </span>
                            <div>
                                <h3 class="text-[1.03rem] font-bold leading-tight tracking-[-0.015em]">{{ __('public.pro_'.$benefit.'_title') }}</h3>
                                <p class="mt-1.5 text-sm leading-relaxed text-content-muted">{{ __('public.pro_'.$benefit.'_body') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ── Safety ────────────────────────────────────────────────────────────────────────────
         Every item is built: panic alerts reach staff and emergency contacts server-side, check-in
         is recorded with a timestamp and a point, the share link expires, and a dispute is decided
         by a person whose name is on the adjustment. The siren is the page's one non-accent tile. --}}
    <section class="py-20 lg:py-24">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="max-w-[41rem]">
                <span class="eyebrow">{{ __('public.safety_eyebrow') }}</span>
                <h2 class="title mt-4">{{ __('public.safety_title') }}</h2>
                <p class="lede mt-4">{{ __('public.safety_lede') }}</p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['siren', 'panic', true],
                    ['pin', 'checkin', false],
                    ['share', 'share', false],
                    ['scale', 'dispute', false],
                ] as [$icon, $item, $danger])
                    <div class="card p-6">
                        <span @class(['icon-tile', 'icon-tile-danger' => $danger]) aria-hidden="true">@include('public.partials.icon', ['name' => $icon])</span>
                        <h3 class="mt-6 text-[1.1rem] font-bold leading-tight tracking-[-0.015em]">{{ __('public.safety_'.$item.'_title') }}</h3>
                        <p class="mt-2.5 text-sm leading-relaxed text-content-muted">{{ __('public.safety_'.$item.'_body') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── FAQ ───────────────────────────────────────────────────────────────────────────────
         Real answers to the questions that decide whether someone tries this. Plain <details>
         rows divided by lines, the first open, so it works without JS. --}}
    <section class="pb-20 lg:pb-24" id="faq">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="grid gap-10 lg:grid-cols-[360px_1fr] lg:gap-[72px]">
                <div>
                    <span class="eyebrow">{{ __('public.faq_eyebrow') }}</span>
                    <h2 class="title mt-4">{{ __('public.faq_title') }}</h2>
                </div>

                <div class="border-t border-edge">
                    @foreach (['cost', 'money', 'unhappy', 'cash', 'remote'] as $q)
                        <details class="group border-b border-edge py-6" @if ($loop->first) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-6 text-[1.125rem] font-bold tracking-[-0.02em] [&::-webkit-details-marker]:hidden">
                                {{ __('public.faq_'.$q.'_q') }}
                                <span class="shrink-0 text-content-muted group-open:hidden [&_svg]:size-[19px]" aria-hidden="true">@include('public.partials.icon', ['name' => 'plus'])</span>
                                <span class="hidden shrink-0 text-brand group-open:block [&_svg]:size-[19px]" aria-hidden="true">@include('public.partials.icon', ['name' => 'minus'])</span>
                            </summary>
                            <p class="mt-3 max-w-[74ch] text-[0.95rem] leading-[1.65] text-content-muted">{{ __('public.faq_'.$q.'_a') }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ── Closing CTA ───────────────────────────────────────────────────────────────────────
         The one place the accent runs as a field on this site: a 32px-radius panel inside the
         column, ink on it always the page ground. --}}
    <section class="pb-20 lg:pb-24">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="flex flex-wrap items-center justify-between gap-8 rounded-[32px] bg-brand px-8 py-14 text-brand-contrast lg:px-16 lg:py-16">
                <div class="max-w-[50ch]">
                    <h2 class="text-[clamp(2rem,1.4rem+2.4vw,2.875rem)] font-extrabold leading-[1.05] tracking-[-0.038em] text-brand-contrast">{{ __('public.cta_title') }}</h2>
                    <p class="mt-4 text-[1.0625rem] leading-relaxed text-brand-contrast/80">{{ __('public.cta_lede') }}</p>
                </div>
                <a class="btn btn-on-accent" href="{{ route('services.index') }}">
                    {{ __('public.cta_button') }}
                    <span class="[&_svg]:size-4" aria-hidden="true">@include('public.partials.icon', ['name' => 'arrow-right'])</span>
                </a>
            </div>
        </div>
    </section>
@endsection
