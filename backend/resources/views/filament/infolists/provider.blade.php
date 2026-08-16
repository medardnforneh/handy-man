@php
    /** @var \App\Models\ProviderProfile $pp */
    $pp = $getRecord();
    $pp->loadMissing(['party.user', 'skills']);
    $party = $pp->party;

    $initials = function (?string $n): string {
        $parts = preg_split('/\s+/', trim((string) $n)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    };

    // The SAME numbers the app shows a customer (GET /providers/{party}/metrics), from the same
    // service — not a second calculation that could disagree with it. Staff answering "why does my
    // profile say X" must be looking at exactly what the caller is looking at.
    $m = app(\App\Domain\Metrics\ProviderMetrics::class)->forParty($pp->party_id);

    $engagements = \App\Models\Engagement::query()->where('provider_party_id', $pp->party_id);
    $inFlight = (clone $engagements)->whereNull('completed_at')->count();

    // Earned = what has actually been RELEASED from escrow, not what was agreed. An agreed amount on
    // a job still in flight is a promise; this column is the money that reached them.
    $released = (int) \App\Models\Milestone::query()
        ->whereIn('engagement_id', (clone $engagements)->select('id'))
        ->where('status', 'approved')
        ->sum('amount_minor');

    $openDisputes = \App\Models\Dispute::query()
        ->whereIn('engagement_id', (clone $engagements)->select('id'))
        ->whereIn('status', ['open', 'under_review'])
        ->count();

    $openReports = \App\Models\Report::query()
        ->where('subject_party_id', $pp->party_id)
        ->whereIn('status', ['open', 'reviewing'])
        ->count();

    $docs = \App\Models\VerificationDocument::query()
        ->where('party_id', $pp->party_id)
        ->orderByDesc('created_at')
        ->get();

    $money = fn (int $minor): string => number_format($minor, 0, ',', ' ');
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    <section class="hm-card">
        <div class="hm-head">
            <span class="hm-pa hm-pa-lg">{{ $initials($party?->display_name) }}</span>
            <div style="flex:1">
                <div class="hm-title">
                    {{ $party?->display_name ?? '—' }}
                    <small>{{ $pp->headline ?? '—' }}</small>
                </div>
                <div class="hm-sub">
                    {{ $party?->user?->phone_e164 ?? '—' }}
                    &nbsp;·&nbsp; {{ __('admin.provider.joined') }} {{ $pp->created_at?->format('d M Y') }}
                </div>
            </div>
            <span class="hm-pill {{ $pp->verification_tier >= 2 ? 'hm-completed' : 'hm-progress' }}">
                {{ __('admin.party.tier_n', ['n' => $pp->verification_tier]) }}
            </span>
        </div>
    </section>

    {{-- The four numbers a provider is judged on. `completed_total` is lifetime; the 90-day figure
         sits under it because a provider who was busy two years ago and idle since is a different
         proposition from one who is working now, and one number cannot say both. --}}
    <div class="hm-mgrid">
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.provider.completed_total') }}</div>
            <div class="hm-mv" style="color:var(--hm-brand)">
                {{ $m['completed_total'] }}
                <small>{{ __('admin.provider.completed_90d', ['n' => $m['jobs_completed_90d']]) }}</small>
            </div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.provider.in_flight') }}</div>
            <div class="hm-mv">{{ $inFlight }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.provider.rating') }}</div>
            <div class="hm-mv">
                @if ($m['rating_avg'] === null)
                    <span class="hm-sub">{{ __('admin.provider.unrated') }}</span>
                @else
                    {{ number_format($m['rating_avg'], 2) }}
                    <small>{{ __('admin.provider.reviews_n', ['n' => $m['rating_count']]) }}</small>
                @endif
            </div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.provider.earned') }}</div>
            <div class="hm-mv">{{ $money($released) }} <small>{{ __('money.currency') }}</small></div>
        </div>
    </div>

    <div class="hm-cols">
        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.provider.reliability') }}</h2></div>
            <div class="hm-kv">
                {{-- Withheld below the sample floor rather than shown thin (P6-12). "100% of one job"
                     is not a reliability record, and staff should not be shown one as if it were. --}}
                <div class="r">
                    <span class="l">{{ __('admin.provider.on_time') }}</span>
                    <span class="val">
                        @if ($m['on_time_rate'] === null)
                            {{ __('admin.provider.too_few', ['n' => $m['on_time_sample']]) }}
                        @else
                            {{ round($m['on_time_rate'] * 100) }}%
                            <span class="hm-sub">({{ __('admin.provider.of_n', ['n' => $m['on_time_sample']]) }})</span>
                        @endif
                    </span>
                </div>
                <div class="r">
                    <span class="l">{{ __('admin.provider.repeat_rate') }}</span>
                    <span class="val">
                        {{ $m['repeat_customer_rate'] === null ? '—' : round($m['repeat_customer_rate'] * 100).'%' }}
                    </span>
                </div>
                {{-- A high repeat rate on very few jobs is the shape of work moving off-platform
                     (P8-04) — the same signal the leakage watch widget raises. --}}
                <div class="r">
                    <span class="l">{{ __('admin.provider.leakage') }}</span>
                    <span class="val">
                        @if ($m['leakage_flag'])
                            <span class="hm-pill hm-danger">{{ __('admin.provider.leakage_flagged') }}</span>
                        @else
                            <span class="hm-sub">{{ __('admin.provider.leakage_clear') }}</span>
                        @endif
                    </span>
                </div>
                <div class="r">
                    <span class="l">{{ __('admin.provider.open_disputes') }}</span>
                    <span class="val">{{ $openDisputes }}</span>
                </div>
                <div class="r">
                    <span class="l">{{ __('admin.party.open_reports') }}</span>
                    <span class="val">{{ $openReports }}</span>
                </div>
            </div>
        </section>

        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.provider.verification') }}</h2></div>
            @if ($docs->isEmpty())
                <p class="hm-note">{{ __('admin.provider.no_documents') }}</p>
            @else
                <div class="hm-kv">
                    {{-- `kind` and `status` are cast to enums on the model, so they need unwrapping
                         before they can be part of a translation key. --}}
                    @foreach ($docs as $doc)
                        @php
                            $kind = $doc->kind instanceof BackedEnum ? $doc->kind->value : $doc->kind;
                            $status = $doc->status instanceof BackedEnum ? $doc->status->value : $doc->status;
                        @endphp
                        <div class="r">
                            <span class="l">{{ __('vdoc.kind.'.$kind) }}</span>
                            <span class="val">
                                <span class="hm-pill {{ $status === 'approved' ? 'hm-completed' : ($status === 'rejected' ? 'hm-danger' : 'hm-progress') }}">
                                    {{ __('vdoc.status.'.$status) }}
                                </span>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <section class="hm-card">
        <div class="hm-phead"><h2>{{ __('admin.provider.skills') }}</h2></div>
        @if ($pp->skills->isEmpty())
            {{-- Not cosmetic: a provider with no listed trade cannot be matched to any request at
                 all (P2-04), so this is the reason their feed is empty. --}}
            <p class="hm-note">{{ __('admin.provider.no_skills') }}</p>
        @else
            <div class="hm-kv">
                <div class="r">
                    <span class="l">{{ __('admin.provider.listed_trades') }}</span>
                    <span class="val">{{ $pp->skills->pluck('name')->implode(', ') }}</span>
                </div>
            </div>
        @endif
    </section>
</div>
</div>
