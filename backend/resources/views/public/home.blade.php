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

         The ground: a brand wash fading downward so the fold has a horizon rather than an edge,
         the header's own tool mark held at four percent, and a hairline where the wash ends. The
         mark sits top-right, on the same side as the radial, so the headline column stays clean —
         behind the text it read as a smudge across the first line. Hidden below 48rem, where it
         would fight the headline for the same pixels. --}}
    <section class="relative isolate overflow-hidden border-b border-edge py-24
                    bg-[radial-gradient(60rem_32rem_at_78%_-12%,color-mix(in_srgb,var(--hm-color-brand-primary)_16%,transparent),transparent_65%),linear-gradient(to_bottom,var(--hm-color-brand-tint),color-mix(in_srgb,var(--hm-color-brand-tint)_22%,transparent))]
                    before:content-[''] before:absolute before:-z-10 before:-top-[20%] before:-right-[8%]
                    before:size-[40rem] before:bg-current before:text-brand before:opacity-[0.045]
                    before:[mask:var(--hm-mark)_no-repeat_center/contain] before:pointer-events-none
                    max-md:before:hidden">
        <div class="w-full max-w-6xl mx-auto px-6 grid gap-16 items-center lg:grid-cols-[1.05fr_0.95fr]">
            <div class="max-w-[44rem]">
                <span class="inline-block text-xs font-bold uppercase tracking-[0.12em] text-brand">{{ __('public.hero_eyebrow') }}</span>
                <h1 class="mt-2 text-[clamp(2.1rem,1.35rem+3.2vw,3.9rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ __('public.hero_title') }}</h1>
                <p class="mt-4 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)] text-content-muted">{{ __('public.hero_lede') }}</p>

                <div class="flex flex-wrap gap-2 mt-6">
                    <a class="inline-flex items-center justify-center gap-2 min-h-11 rounded-md border border-transparent bg-brand px-[1.15rem] py-3 text-[0.95rem] font-semibold text-brand-contrast no-underline shadow-sm transition hover:bg-brand-strong hover:-translate-y-px hover:shadow-md"
                       href="{{ route('services.index') }}">
                        {{ __('public.hero_cta_primary') }}
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a class="inline-flex items-center justify-center gap-2 min-h-11 rounded-md border border-edge bg-surface-raised px-[1.15rem] py-3 text-[0.95rem] font-semibold text-content no-underline transition hover:border-edge-strong hover:-translate-y-px"
                       href="#providers">{{ __('public.hero_cta_secondary') }}</a>
                </div>

                @if ($popularTrades->isNotEmpty())
                    <p class="mt-6 text-sm text-content-muted">{{ __('public.hero_popular') }}</p>
                    <ul class="flex flex-wrap gap-2 mt-2 list-none p-0 m-0">
                        @foreach ($popularTrades as $trade)
                            <li>
                                <a class="inline-block rounded-pill border border-edge bg-surface-raised px-3.5 py-2 text-sm text-content no-underline transition-colors hover:border-brand hover:text-brand"
                                   href="{{ route('services.show', ['slug' => $trade->slug]) }}">{{ $trade->name($locale) }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- The hero visual is the product itself rather than a stock photograph: an escrow-
                 backed engagement, mid-flight. It is decorative markup (the same facts are stated in
                 words above and below), so it is hidden from assistive technology instead of being
                 read out as a fake job. Hidden entirely on small screens — on a phone the copy and
                 the CTA are what matter, and a decorative panel would just push them down. --}}
            <div class="hidden lg:block" aria-hidden="true">
                <div class="max-w-96 ms-auto rounded-lg border border-edge bg-surface-raised p-6 shadow-lg">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-bold">{{ __('public.hero_card_ref') }}</span>
                        <span class="inline-flex items-center gap-1 rounded-pill bg-brand-tint px-2.5 py-1 text-xs font-semibold text-brand">{{ __('public.hero_card_status') }}</span>
                    </div>

                    <div class="flex items-center gap-2 mt-4">
                        <span class="grid place-items-center shrink-0 size-10 rounded-md bg-brand text-brand-contrast text-xs font-extrabold">{{ __('public.hero_card_initials') }}</span>
                        <div>
                            <div class="text-sm font-semibold">{{ __('public.hero_card_provider') }}</div>
                            <div class="text-sm text-content-muted">{{ __('public.hero_card_trade') }}</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mt-4 p-4 rounded-md bg-brand-tint">
                        <span class="grid place-items-center shrink-0 size-8 rounded-md bg-brand-tint text-brand [&_svg]:size-5">
                            @include('public.partials.icon', ['name' => 'shield'])
                        </span>
                        <div>
                            <div class="text-sm text-content-muted">{{ __('public.hero_card_escrow_label') }}</div>
                            <div class="font-extrabold tracking-[-0.02em]">{{ __('public.hero_card_amount') }}</div>
                        </div>
                    </div>

                    <div class="grid gap-2 mt-4">
                        @foreach ([
                            ['public.hero_card_step_deposit', true],
                            ['public.hero_card_step_work', true],
                            ['public.hero_card_step_approve', false],
                        ] as [$stepKey, $done])
                            <div @class([
                                'flex items-center gap-2.5',
                                'text-content' => $done,
                                'text-content-muted' => ! $done,
                            ])>
                                <span @class([
                                    'grid place-items-center shrink-0 size-[1.15rem] rounded-pill text-[0.7rem] border-[1.5px]',
                                    'bg-brand border-brand text-brand-contrast' => $done,
                                    'border-edge-strong text-transparent' => ! $done,
                                ])>{{ $done ? '✓' : '' }}</span>
                                <span class="text-sm">{{ __($stepKey) }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 rounded-md bg-brand px-3 py-2.5 text-center text-[0.92rem] font-semibold text-brand-contrast">{{ __('public.hero_card_button') }}</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Trust strip ───────────────────────────────────────────────────────────────────────
         Four claims, each of which the platform actually implements. Nothing here is aspirational:
         escrow is the ledger, the tier is an approved ID document, the warranty spawns a real
         remedy job, and MoMo is the only rail we take money on. --}}
    <section class="py-10 bg-brand-tint" id="trust">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))]">
                @foreach ([
                    ['shield', 'public.trust_escrow_title', 'public.trust_escrow_body'],
                    ['badge', 'public.trust_verified_title', 'public.trust_verified_body'],
                    ['refresh', 'public.trust_warranty_title', 'public.trust_warranty_body'],
                    ['phone', 'public.trust_momo_title', 'public.trust_momo_body'],
                ] as [$icon, $title, $body])
                    <div class="space-y-2">
                        <span class="grid place-items-center shrink-0 size-10 rounded-md bg-brand-tint text-brand [&_svg]:size-5" aria-hidden="true">
                            @include('public.partials.icon', ['name' => $icon])
                        </span>
                        <h3 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">{{ __($title) }}</h3>
                        <p class="m-0 text-sm text-content-muted">{{ __($body) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── How it works ─────────────────────────────────────────────────────────────────────── --}}
    <section class="py-24" id="how">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="max-w-[44rem] mx-auto text-center">
                <span class="inline-block text-xs font-bold uppercase tracking-[0.12em] text-brand">{{ __('public.how_eyebrow') }}</span>
                <h2 class="mt-2 text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ __('public.how_title') }}</h2>
                <p class="mt-2 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)] text-content-muted">{{ __('public.how_lede') }}</p>
            </div>

            <ol class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))] list-none mt-10 p-0">
                @foreach (['describe', 'compare', 'pay'] as $i => $step)
                    <li class="space-y-2 rounded-lg border border-edge bg-surface-raised p-6">
                        <span class="inline-flex items-center gap-1 rounded-pill bg-brand-tint px-2.5 py-1 text-xs font-semibold text-brand">{{ __('public.how_step', ['n' => $i + 1]) }}</span>
                        <h3 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">{{ __('public.how_'.$step.'_title') }}</h3>
                        <p class="m-0 text-sm text-content-muted">{{ __('public.how_'.$step.'_body') }}</p>
                    </li>
                @endforeach
            </ol>

            <p class="mt-6 text-center text-sm text-content-muted">{{ __('public.how_remote_note') }}</p>
        </div>
    </section>

    {{-- ── Trades directory ──────────────────────────────────────────────────────────────────
         The categories are the SEO surface (P1-07): real trades, in both languages, each a
         crawlable page. Counting the leaves is honest — it is the size of the taxonomy, not a
         claim about how many providers are signed up. --}}
    @if ($categories->isNotEmpty())
        <section class="py-10" id="trades">
            <div class="w-full max-w-6xl mx-auto px-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-[44rem]">
                        <span class="inline-block text-xs font-bold uppercase tracking-[0.12em] text-brand">{{ __('public.trades_eyebrow') }}</span>
                        <h2 class="mt-2 text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ __('public.trades_title') }}</h2>
                    </div>
                    <a class="inline-flex items-center justify-center gap-2 min-h-11 rounded-md border border-edge bg-surface-raised px-[1.15rem] py-3 text-[0.95rem] font-semibold text-content no-underline transition hover:border-edge-strong hover:-translate-y-px"
                       href="{{ route('services.index') }}">{{ __('public.all_services') }}</a>
                </div>

                <div class="grid gap-4 mt-6 grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))]">
                    @foreach ($categories as $category)
                        <a class="block space-y-2 rounded-lg border border-edge bg-surface-raised p-6 text-inherit no-underline transition hover:-translate-y-0.5 hover:border-brand hover:shadow-md"
                           href="{{ route('services.show', ['slug' => $category->slug]) }}">
                            {{-- The slug doubles as the icon name; an unknown trade falls back to
                                 the generic tool rather than rendering nothing. --}}
                            <span class="grid place-items-center shrink-0 size-10 rounded-md bg-brand-tint text-brand [&_svg]:size-5" aria-hidden="true">
                                @include('public.partials.icon', ['name' => $category->slug])
                            </span>
                            <h3 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">{{ $category->name($locale) }}</h3>
                            <span class="text-sm text-content-muted">{{ trans_choice('public.trades_count', $category->children_count, ['count' => $category->children_count]) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ── Providers ─────────────────────────────────────────────────────────────────────────
         The inverted band is the register change: this section addresses the other side of the
         marketplace. The claims are the ones the product can defend — a client book that stays
         yours, cash recorded rather than punished, and payouts to the same MoMo number. --}}
    <section class="py-24 bg-surface-inverse text-content-inverse" id="providers">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="grid gap-16 items-center grid-cols-[repeat(auto-fit,minmax(min(100%,20rem),1fr))]">
                <div>
                    <span class="inline-block text-xs font-bold uppercase tracking-[0.12em] text-brand">{{ __('public.pro_eyebrow') }}</span>
                    <h2 class="mt-2 text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em] text-content-inverse">{{ __('public.pro_title') }}</h2>
                    <p class="mt-4 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)] text-content-inverse/70">{{ __('public.pro_lede') }}</p>
                    <div class="flex flex-wrap gap-2 mt-6">
                        <a class="inline-flex items-center justify-center gap-2 min-h-11 rounded-md border border-transparent bg-surface-raised px-[1.15rem] py-3 text-[0.95rem] font-semibold text-content no-underline transition hover:-translate-y-px hover:shadow-md"
                           href="{{ route('services.index') }}">{{ __('public.pro_cta') }}</a>
                    </div>
                </div>

                <div class="grid gap-4">
                    @foreach (['leads', 'paid', 'cash', 'tools'] as $benefit)
                        {{-- Transparent on the inverted band: a raised white card here would read as
                             four holes punched in the section. --}}
                        <div class="flex items-start gap-4 rounded-lg border border-white/15 p-6">
                            <span class="grid place-items-center shrink-0 size-10 rounded-md bg-brand-tint text-brand [&_svg]:size-5" aria-hidden="true">
                                @include('public.partials.icon', ['name' => 'check'])
                            </span>
                            <div>
                                <h3 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em] text-content-inverse">{{ __('public.pro_'.$benefit.'_title') }}</h3>
                                <p class="mt-1 text-sm text-content-inverse/70">{{ __('public.pro_'.$benefit.'_body') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ── Safety ────────────────────────────────────────────────────────────────────────────
         Stated plainly because it is a differentiator in this market, and because every item is
         built: panic alerts reach staff and emergency contacts server-side, check-in is recorded
         with a timestamp and a point, the share link expires, and a dispute is decided by a person
         whose name is on the adjustment. --}}
    <section class="py-24">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="max-w-[44rem] mx-auto text-center">
                <span class="inline-block text-xs font-bold uppercase tracking-[0.12em] text-brand">{{ __('public.safety_eyebrow') }}</span>
                <h2 class="mt-2 text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ __('public.safety_title') }}</h2>
                <p class="mt-2 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)] text-content-muted">{{ __('public.safety_lede') }}</p>
            </div>

            <div class="grid gap-4 mt-10 grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))]">
                @foreach ([
                    ['alert', 'panic'],
                    ['pin', 'checkin'],
                    ['share', 'share'],
                    ['scale', 'dispute'],
                ] as [$icon, $item])
                    <div class="space-y-2 rounded-lg border border-edge bg-surface-raised p-6">
                        <span class="grid place-items-center shrink-0 size-10 rounded-md bg-brand-tint text-brand [&_svg]:size-5" aria-hidden="true">
                            @include('public.partials.icon', ['name' => $icon])
                        </span>
                        <h3 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">{{ __('public.safety_'.$item.'_title') }}</h3>
                        <p class="m-0 text-sm text-content-muted">{{ __('public.safety_'.$item.'_body') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── FAQ ───────────────────────────────────────────────────────────────────────────────
         Real answers to the questions that decide whether someone tries this: what it costs, what
         happens to the money, and what recourse exists. Plain <details> so it works without JS. --}}
    <section class="py-10" id="faq">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="max-w-[44rem] mx-auto text-center">
                <span class="inline-block text-xs font-bold uppercase tracking-[0.12em] text-brand">{{ __('public.faq_eyebrow') }}</span>
                <h2 class="mt-2 text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ __('public.faq_title') }}</h2>
            </div>

            <div class="max-w-[44rem] mx-auto mt-10 space-y-4">
                @foreach (['cost', 'money', 'unhappy', 'cash', 'remote'] as $q)
                    <details class="rounded-lg border border-edge bg-surface-raised p-6">
                        <summary class="cursor-pointer font-semibold">{{ __('public.faq_'.$q.'_q') }}</summary>
                        <p class="mt-2 text-sm text-content-muted">{{ __('public.faq_'.$q.'_a') }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Closing CTA ───────────────────────────────────────────────────────────────────────── --}}
    <section class="py-10">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="rounded-lg border border-edge bg-surface-raised px-6 py-16 text-center">
                <h2 class="text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ __('public.cta_title') }}</h2>
                <p class="max-w-[44rem] mx-auto mt-2 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)] text-content-muted">{{ __('public.cta_lede') }}</p>
                <div class="flex flex-wrap justify-center gap-2 mt-6">
                    <a class="inline-flex items-center justify-center gap-2 min-h-11 rounded-md border border-transparent bg-brand px-[1.15rem] py-3 text-[0.95rem] font-semibold text-brand-contrast no-underline shadow-sm transition hover:bg-brand-strong hover:-translate-y-px hover:shadow-md"
                       href="{{ route('services.index') }}">{{ __('public.cta_button') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
