@php
    /** @var \App\Models\Party $p */
    $p = $getRecord();
    $p->loadMissing(['user', 'providerProfile']);

    $initials = function (?string $n): string {
        $parts = preg_split('/\s+/', trim((string) $n)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    };

    $m = app(\App\Domain\Metrics\CustomerMetrics::class)->forParty($p->id);

    // The last handful of requests, newest first. Every other number on this page is an aggregate,
    // and an aggregate never answers "what happened last week" — which is the question a customer
    // is usually calling about.
    $recent = \App\Models\Job::query()
        ->where('customer_party_id', $p->id)
        ->where('status', '!=', 'draft')
        ->with('engagement.provider')
        ->orderByDesc('created_at')
        ->limit(6)
        ->get();

    $money = fn (int $minor): string => number_format($minor, 0, ',', ' ');
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    <section class="hm-card">
        <div class="hm-head">
            <span class="hm-pa hm-pa-lg">{{ $initials($p->display_name) }}</span>
            <div style="flex:1">
                <div class="hm-title">
                    {{ $p->display_name }}
                    <small>{{ __('admin.party.kind.'.$p->kind) }}</small>
                </div>
                <div class="hm-sub">
                    {{ $p->user?->phone_e164 ?? '—' }}
                    &nbsp;·&nbsp; {{ __('admin.party.joined') }} {{ $p->created_at?->format('d M Y') }}
                </div>
            </div>
            @if ($p->erased_at)
                <span class="hm-pill hm-neutral">{{ __('admin.party.erased') }}</span>
            @endif
            <span class="hm-pill {{ $p->status === 'active' ? 'hm-completed' : ($p->status === 'suspended' ? 'hm-danger' : 'hm-progress') }}">
                {{ __('admin.party.status.'.$p->status) }}
            </span>
        </div>
    </section>

    @if ($p->erased_at)
        {{-- The row survives so ledger FKs stay intact; the key that could decrypt anything personal
             is gone (P1-10). The counts below are still true — they are about jobs, not about a
             person — but nobody should read this page expecting to learn who they were. --}}
        <div class="hm-card" style="padding:14px 16px">
            <p class="hm-note">{{ __('admin.party.erased_explainer') }}</p>
        </div>
    @endif

    {{-- Posted / hired / spent / in flight. "Hired" sits beside "posted" rather than under it
         because the pair is the whole point: the second number without the first says nothing. --}}
    <div class="hm-mgrid">
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.customers.posted') }}</div>
            <div class="hm-mv">
                {{ $m['jobs_posted'] }}
                <small>{{ __('admin.customers.posted_90d', ['n' => $m['jobs_posted_90d']]) }}</small>
            </div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.customers.hired') }}</div>
            <div class="hm-mv" style="color:var(--hm-brand)">
                {{ $m['jobs_hired'] }}
                <small>{{ __('admin.customers.completed_n', ['n' => $m['completed']]) }}</small>
            </div>
        </div>
        <div class="hm-card hm-metric">
            {{-- Released, not agreed: money that actually reached a provider. What is still held
                 sits underneath, because "spent 400 000" reads very differently when half of it is
                 still in escrow on a job nobody has finished. --}}
            <div class="hm-mk">{{ __('admin.customers.paid_out') }}</div>
            <div class="hm-mv">
                {{ $money($m['released_minor']) }} <small>{{ __('money.currency') }}</small>
                @if ($m['escrow_held_minor'] > 0)
                    <small class="hm-under">{{ __('admin.customers.held_now', ['amount' => $money($m['escrow_held_minor'])]) }}</small>
                @endif
            </div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.provider.in_flight') }}</div>
            <div class="hm-mv" @if ($m['disputes_open'] > 0) style="color:var(--hm-warning)" @endif>
                {{ $m['in_flight'] }}
                @if ($m['disputes_open'] > 0)
                    <small class="hm-under">{{ __('admin.customers.disputes_open_n', ['n' => $m['disputes_open']]) }}</small>
                @endif
            </div>
        </div>
    </div>

    <div class="hm-cols">
        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.customers.as_a_customer') }}</h2></div>
            <div class="hm-kv">
                {{-- Leads cost providers credits (P8-02), so a customer who never hires is spending
                     other people's money. Withheld below the sample floor: "0% of one request" is
                     not a habit, and staff act on what they are shown. --}}
                <div class="r">
                    <span class="l">{{ __('admin.customers.hire_rate') }}</span>
                    <span class="val">
                        @if ($m['hire_rate'] === null)
                            {{ __('admin.provider.too_few', ['n' => $m['hire_sample']]) }}
                        @else
                            {{ round($m['hire_rate'] * 100) }}%
                            <span class="hm-sub">({{ __('admin.provider.of_n', ['n' => $m['hire_sample']]) }})</span>
                        @endif
                    </span>
                </div>
                <div class="r">
                    <span class="l">{{ __('admin.customers.still_looking') }}</span>
                    <span class="val">{{ $m['jobs_awaiting'] }}</span>
                </div>
                <div class="r">
                    <span class="l">{{ __('admin.customers.cancelled') }}</span>
                    <span class="val">{{ $m['jobs_cancelled'] }}</span>
                </div>
                <div class="r">
                    <span class="l">{{ __('admin.customers.providers_used') }}</span>
                    <span class="val">
                        {{ $m['providers_used'] }}
                        @if ($m['repeat_providers'] > 0)
                            <span class="hm-sub">({{ __('admin.customers.repeat_n', ['n' => $m['repeat_providers']]) }})</span>
                        @endif
                    </span>
                </div>
                <div class="r">
                    <span class="l">{{ __('admin.customers.agreed_total') }}</span>
                    <span class="val">{{ $money($m['agreed_minor']) }} {{ __('money.currency') }}</span>
                </div>
            </div>
        </section>

        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.customers.conduct') }}</h2></div>
            <div class="hm-kv">
                <div class="r">
                    <span class="l">{{ __('admin.customers.disputes_raised') }}</span>
                    <span class="val" @if ($m['disputes_open'] > 0) style="color:var(--hm-warning)" @endif>
                        {{ $m['disputes_raised'] }}
                        @if ($m['disputes_open'] > 0)
                            <span class="hm-sub">({{ __('admin.customers.n_open', ['n' => $m['disputes_open']]) }})</span>
                        @endif
                    </span>
                </div>
                <div class="r">
                    <span class="l">{{ __('admin.customers.reports_filed') }}</span>
                    <span class="val">{{ $m['reports_filed'] }}</span>
                </div>
                <div class="r">
                    <span class="l">{{ __('admin.party.open_reports') }}</span>
                    <span class="val" @if ($m['reports_against'] > 0) style="color:var(--hm-warning)" @endif>{{ $m['reports_against'] }}</span>
                </div>
                <div class="r">
                    <span class="l">{{ __('admin.customers.reviews_left') }}</span>
                    <span class="val">{{ __('admin.customers.n_of_m', ['n' => $m['reviews_left'], 'm' => $m['reviews_due']]) }}</span>
                </div>
                {{-- Context for the providers they rated, not a mark against them: one customer who
                     gives every job two stars visibly moves a small provider's average (P6-09), and
                     that is the answer when a provider asks why their rating fell. --}}
                <div class="r">
                    <span class="l">{{ __('admin.customers.rating_given') }}</span>
                    <span class="val">
                        {{ $m['rating_given_avg'] === null ? __('admin.customers.too_few_reviews') : number_format($m['rating_given_avg'], 2) }}
                    </span>
                </div>
            </div>
        </section>
    </div>

    <section class="hm-card">
        <div class="hm-phead"><h2>{{ __('admin.customers.recent') }}</h2></div>
        @if ($recent->isEmpty())
            <p class="hm-note">{{ __('admin.customers.no_jobs') }}</p>
        @else
            <div class="hm-tl">
                @foreach ($recent as $job)
                    {{-- `status` is cast to JobStatus on the model, so it needs unwrapping before it
                         can be compared or made part of a translation key. --}}
                    @php $status = $job->status instanceof BackedEnum ? $job->status->value : $job->status; @endphp
                    <div class="hm-mile">
                        <span class="hm-dot {{ $status === 'completed' ? 'hm-paid' : '' }}">
                            {{ $loop->iteration }}
                        </span>
                        <div class="hm-mt">
                            <b>{{ $job->title }}</b>
                            <div class="hm-sub">
                                {{ $job->created_at?->format('d M Y') }}
                                @if ($job->engagement?->provider)
                                    &nbsp;·&nbsp; {{ $job->engagement->provider->display_name }}
                                @endif
                            </div>
                        </div>
                        @if ($job->engagement?->agreed_amount_minor)
                            <span class="hm-amt">{{ $money((int) $job->engagement->agreed_amount_minor) }}</span>
                        @endif
                        <span class="hm-pill {{ $status === 'completed' ? 'hm-completed' : ($status === 'cancelled' ? 'hm-neutral' : ($status === 'disputed' ? 'hm-danger' : 'hm-progress')) }}">
                            {{ __('status.'.$status) }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- The same account is often both halves of the marketplace (doc 10), and staff who arrived
         from the Customers list would otherwise answer as if this were only one of the two. --}}
    @if ($p->providerProfile)
        <a class="hm-linkcard" href="{{ \App\Filament\Resources\ProviderProfiles\ProviderProfileResource::getUrl('view', ['record' => $p->providerProfile]) }}">
            <span>{{ __('admin.customers.also_provider_link', ['tier' => $p->providerProfile->verification_tier]) }}</span>
            <span>&rarr;</span>
        </a>
    @endif

    <a class="hm-linkcard" href="{{ \App\Filament\Resources\Parties\PartyResource::getUrl('view', ['record' => $p]) }}">
        <span>{{ __('admin.customers.identity_link') }}</span>
        <span>&rarr;</span>
    </a>
</div>
</div>
