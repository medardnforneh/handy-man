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
         both, so the second CTA leads into the same product rather than a separate one. --}}
    <section class="section">
        <div class="wrap hero-grid">
            <div class="measure">
                <span class="t-eyebrow">{{ __('public.hero_eyebrow') }}</span>
                <h1 class="t-display" style="margin-top: var(--hm-space-sm);">{{ __('public.hero_title') }}</h1>
                <p class="t-lede" style="margin-top: var(--hm-space-md);">{{ __('public.hero_lede') }}</p>

                <div class="btn-row" style="margin-top: var(--hm-space-lg);">
                    <a class="btn btn-primary" href="{{ route('services.index') }}">
                        {{ __('public.hero_cta_primary') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a class="btn btn-ghost" href="#providers">{{ __('public.hero_cta_secondary') }}</a>
                </div>

                @if ($popularTrades->isNotEmpty())
                    <p class="t-small muted" style="margin-top: var(--hm-space-lg);">{{ __('public.hero_popular') }}</p>
                    <ul class="chips" style="margin-top: var(--hm-space-sm);">
                        @foreach ($popularTrades as $trade)
                            <li><a href="{{ route('services.show', ['slug' => $trade->slug]) }}">{{ $trade->name($locale) }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- The hero visual is the product itself rather than a stock photograph: an escrow-
                 backed engagement, mid-flight. It is decorative markup (the same facts are stated in
                 words above and below), so it is hidden from assistive technology instead of being
                 read out as a fake job. Hidden entirely on small screens — on a phone the copy and
                 the CTA are what matter, and a decorative panel would just push them down. --}}
            <div class="hero-visual" aria-hidden="true">
                <div class="mock">
                    <div class="mock-row" style="justify-content:space-between;">
                        <span class="t-small" style="font-weight:700;">{{ __('public.hero_card_ref') }}</span>
                        <span class="pill">{{ __('public.hero_card_status') }}</span>
                    </div>

                    <div class="mock-row" style="margin-top: var(--hm-space-md);">
                        <span class="mock-avatar">{{ __('public.hero_card_initials') }}</span>
                        <div>
                            <div class="t-small" style="font-weight:650;">{{ __('public.hero_card_provider') }}</div>
                            <div class="t-small muted">{{ __('public.hero_card_trade') }}</div>
                        </div>
                    </div>

                    <div class="mock-escrow">
                        <span class="icon-badge" style="width:2rem;height:2rem;">
                            @include('public.partials.icon', ['name' => 'shield'])
                        </span>
                        <div>
                            <div class="t-small muted">{{ __('public.hero_card_escrow_label') }}</div>
                            <div style="font-weight:800; letter-spacing:-0.02em;">{{ __('public.hero_card_amount') }}</div>
                        </div>
                    </div>

                    <div class="mock-steps">
                        <div class="mock-step done">
                            <span class="dot">✓</span>
                            <span class="t-small">{{ __('public.hero_card_step_deposit') }}</span>
                        </div>
                        <div class="mock-step done">
                            <span class="dot">✓</span>
                            <span class="t-small">{{ __('public.hero_card_step_work') }}</span>
                        </div>
                        <div class="mock-step">
                            <span class="dot"></span>
                            <span class="t-small">{{ __('public.hero_card_step_approve') }}</span>
                        </div>
                    </div>

                    <div class="mock-cta">{{ __('public.hero_card_button') }}</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Trust strip ───────────────────────────────────────────────────────────────────────
         Four claims, each of which the platform actually implements. Nothing here is aspirational:
         escrow is the ledger, the tier is an approved ID document, the warranty spawns a real
         remedy job, and MoMo is the only rail we take money on. --}}
    <section class="section-sm band-tint" id="trust">
        <div class="wrap">
            <div class="grid grid-4">
                @foreach ([
                    ['shield', 'public.trust_escrow_title', 'public.trust_escrow_body'],
                    ['badge', 'public.trust_verified_title', 'public.trust_verified_body'],
                    ['refresh', 'public.trust_warranty_title', 'public.trust_warranty_body'],
                    ['phone', 'public.trust_momo_title', 'public.trust_momo_body'],
                ] as [$icon, $title, $body])
                    <div class="stack-sm">
                        <span class="icon-badge" aria-hidden="true">
                            @include('public.partials.icon', ['name' => $icon])
                        </span>
                        <h3 class="t-h3">{{ __($title) }}</h3>
                        <p class="t-small muted" style="margin:0">{{ __($body) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── How it works ─────────────────────────────────────────────────────────────────────── --}}
    <section class="section" id="how">
        <div class="wrap">
            <div class="measure-center center">
                <span class="t-eyebrow">{{ __('public.how_eyebrow') }}</span>
                <h2 class="t-h2" style="margin-top: var(--hm-space-sm);">{{ __('public.how_title') }}</h2>
                <p class="t-lede" style="margin-top: var(--hm-space-sm);">{{ __('public.how_lede') }}</p>
            </div>

            <ol class="grid grid-3" style="list-style:none; margin: var(--hm-space-xl) 0 0; padding:0; counter-reset: step;">
                @foreach (['describe', 'compare', 'pay'] as $i => $step)
                    <li class="card stack-sm">
                        <span class="pill">{{ __('public.how_step', ['n' => $i + 1]) }}</span>
                        <h3 class="t-h3">{{ __('public.how_'.$step.'_title') }}</h3>
                        <p class="t-small muted" style="margin:0">{{ __('public.how_'.$step.'_body') }}</p>
                    </li>
                @endforeach
            </ol>

            <p class="t-small muted center" style="margin-top: var(--hm-space-lg);">{{ __('public.how_remote_note') }}</p>
        </div>
    </section>

    {{-- ── Trades directory ──────────────────────────────────────────────────────────────────
         The categories are the SEO surface (P1-07): real Cameroon trades, in both languages, each
         a crawlable page. Counting the leaves is honest — it is the size of the taxonomy, not a
         claim about how many providers are signed up. --}}
    @if ($categories->isNotEmpty())
        <section class="section-sm" id="trades">
            <div class="wrap">
                <div style="display:flex; flex-wrap:wrap; gap: var(--hm-space-md); align-items:flex-end; justify-content:space-between;">
                    <div class="measure">
                        <span class="t-eyebrow">{{ __('public.trades_eyebrow') }}</span>
                        <h2 class="t-h2" style="margin-top: var(--hm-space-sm);">{{ __('public.trades_title') }}</h2>
                    </div>
                    <a class="btn btn-ghost" href="{{ route('services.index') }}">{{ __('public.all_services') }}</a>
                </div>

                <div class="grid grid-4" style="margin-top: var(--hm-space-lg);">
                    @foreach ($categories as $category)
                        <a class="card stack-sm" href="{{ route('services.show', ['slug' => $category->slug]) }}">
                            {{-- The slug doubles as the icon name; an unknown trade falls back to
                                 the generic tool rather than rendering nothing. --}}
                            <span class="icon-badge" aria-hidden="true">
                                @include('public.partials.icon', ['name' => $category->slug])
                            </span>
                            <h3 class="t-h3">{{ $category->name($locale) }}</h3>
                            <span class="t-small muted">{{ trans_choice('public.trades_count', $category->children_count, ['count' => $category->children_count]) }}</span>
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
    <section class="section band-inverse" id="providers">
        <div class="wrap">
            <div class="grid grid-2" style="gap: var(--hm-space-2xl); align-items:center;">
                <div>
                    <span class="t-eyebrow">{{ __('public.pro_eyebrow') }}</span>
                    <h2 class="t-h2" style="margin-top: var(--hm-space-sm);">{{ __('public.pro_title') }}</h2>
                    <p class="t-lede muted" style="margin-top: var(--hm-space-md);">{{ __('public.pro_lede') }}</p>
                    <div class="btn-row" style="margin-top: var(--hm-space-lg);">
                        <a class="btn btn-onInverse" href="{{ route('services.index') }}">{{ __('public.pro_cta') }}</a>
                    </div>
                </div>

                <div class="grid" style="gap: var(--hm-space-md);">
                    @foreach (['leads', 'paid', 'cash', 'tools'] as $benefit)
                        <div class="card" style="display:flex; gap: var(--hm-space-md); align-items:flex-start;">
                            <span class="icon-badge" aria-hidden="true">
                                @include('public.partials.icon', ['name' => 'check'])
                            </span>
                            <div>
                                <h3 class="t-h3">{{ __('public.pro_'.$benefit.'_title') }}</h3>
                                <p class="t-small muted" style="margin: 0.25rem 0 0;">{{ __('public.pro_'.$benefit.'_body') }}</p>
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
    <section class="section">
        <div class="wrap">
            <div class="measure-center center">
                <span class="t-eyebrow">{{ __('public.safety_eyebrow') }}</span>
                <h2 class="t-h2" style="margin-top: var(--hm-space-sm);">{{ __('public.safety_title') }}</h2>
                <p class="t-lede" style="margin-top: var(--hm-space-sm);">{{ __('public.safety_lede') }}</p>
            </div>

            <div class="grid grid-4" style="margin-top: var(--hm-space-xl);">
                @foreach ([
                    ['alert', 'panic'],
                    ['pin', 'checkin'],
                    ['share', 'share'],
                    ['scale', 'dispute'],
                ] as [$icon, $item])
                    <div class="card stack-sm">
                        <span class="icon-badge" aria-hidden="true">
                            @include('public.partials.icon', ['name' => $icon])
                        </span>
                        <h3 class="t-h3">{{ __('public.safety_'.$item.'_title') }}</h3>
                        <p class="t-small muted" style="margin:0">{{ __('public.safety_'.$item.'_body') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── FAQ ───────────────────────────────────────────────────────────────────────────────
         Real answers to the questions that decide whether someone tries this: what it costs, what
         happens to the money, and what recourse exists. Plain <details> so it works without JS. --}}
    <section class="section-sm" id="faq">
        <div class="wrap">
            <div class="measure-center center">
                <span class="t-eyebrow">{{ __('public.faq_eyebrow') }}</span>
                <h2 class="t-h2" style="margin-top: var(--hm-space-sm);">{{ __('public.faq_title') }}</h2>
            </div>

            <div class="measure-center stack" style="margin-top: var(--hm-space-xl);">
                @foreach (['cost', 'money', 'unhappy', 'cash', 'remote'] as $q)
                    <details class="card">
                        <summary style="cursor:pointer; font-weight:650;">{{ __('public.faq_'.$q.'_q') }}</summary>
                        <p class="t-small muted" style="margin: var(--hm-space-sm) 0 0;">{{ __('public.faq_'.$q.'_a') }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Closing CTA ───────────────────────────────────────────────────────────────────────── --}}
    <section class="section-sm">
        <div class="wrap">
            <div class="card center" style="padding-block: var(--hm-space-2xl);">
                <h2 class="t-h2">{{ __('public.cta_title') }}</h2>
                <p class="t-lede measure-center" style="margin-top: var(--hm-space-sm);">{{ __('public.cta_lede') }}</p>
                <div class="btn-row" style="justify-content:center; margin-top: var(--hm-space-lg);">
                    <a class="btn btn-primary" href="{{ route('services.index') }}">{{ __('public.cta_button') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
