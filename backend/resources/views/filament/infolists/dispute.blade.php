@php
    /** @var \App\Models\Dispute $d */
    $d = $getRecord();
    $d->loadMissing(['engagement.job.skill', 'engagement.job.customer', 'engagement.provider', 'raisedBy', 'resolvedBy']);

    $e = $d->engagement;
    $ledger = app(\App\Domain\Money\Ledger::class);
    $held = $e ? $ledger->escrowHeldMinor($e->id, $e->currency) : 0;

    $money = fn (int $m): string => number_format($m, 0, '.', ' ');
    $pill = match ($d->status) {
        'resolved' => 'hm-completed',
        'reviewing' => 'hm-progress',
        'rejected' => 'hm-engaged',
        default => 'hm-danger',
    };
    $initials = function (?string $n): string {
        $p = preg_split('/\s+/', trim((string) $n)) ?: [];

        return mb_strtoupper(mb_substr($p[0] ?? '?', 0, 1).mb_substr($p[1] ?? '', 0, 1));
    };

    // Which side raised it. A dispute reads very differently depending on who is complaining, and
    // that is the first thing an adjudicator needs to know.
    $raiserSide = match ($d->raised_by_party_id) {
        $e?->job?->customer_party_id => __('admin.customer'),
        $e?->provider_party_id => __('admin.col.provider'),
        default => null,
    };
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    {{-- Header --}}
    <section class="hm-card">
        <div class="hm-head">
            <span class="hm-pa hm-pa-lg" style="background:var(--hm-danger)">{{ $initials($d->raisedBy?->display_name) }}</span>
            <div style="flex:1">
                <div class="hm-title">
                    {{ __('admin.dispute.category.'.$d->category) }}
                    <small>{{ $e?->job?->reference }}</small>
                </div>
                <div class="hm-sub">
                    {{ __('admin.dispute.raised_by') }}: {{ $d->raisedBy?->display_name ?? '—' }}@if ($raiserSide) ({{ $raiserSide }})@endif
                    &nbsp;·&nbsp; {{ $d->created_at?->format('d M Y, H:i') }}
                </div>
            </div>
            <span class="hm-pill {{ $pill }}">{{ __('admin.dispute.status.'.$d->status) }}</span>
        </div>
    </section>

    {{-- Money at stake: what an adjudicator is actually deciding over. --}}
    <div class="hm-mgrid">
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.agreed_amount') }}</div>
            <div class="hm-mv">{{ $money((int) ($e?->agreed_amount_minor ?? 0)) }} <small>{{ $e?->currency }}</small></div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.kpi.escrow_held') }}</div>
            <div class="hm-mv" style="color:var(--hm-info)">{{ $money($held) }} <small>{{ $e?->currency }}</small></div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.dispute.age') }}</div>
            <div class="hm-mv">{{ (int) ($d->created_at?->diffInDays() ?? 0) }} <small>{{ __('admin.dispute.days') }}</small></div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.dispute.ledger_effect') }}</div>
            <div class="hm-mv">{{ $d->resolution_transaction_id ? __('admin.dispute.adjustment_posted') : __('admin.dispute.no_ledger_effect') }}</div>
        </div>
    </div>

    <div class="hm-two">
        {{-- The complaint, in full. The table truncates it to 60 characters; this is the page where
             it is actually read. --}}
        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.dispute.complaint') }}</h2></div>
            <p class="hm-body">{{ $d->body }}</p>

            @if ($d->resolution_note)
                <div class="hm-phead" style="margin-top:18px"><h2>{{ __('admin.dispute.resolution') }}</h2></div>
                <p class="hm-body">{{ $d->resolution_note }}</p>
                <div class="hm-sub" style="margin-top:8px">
                    {{ __('admin.dispute.decided_by') }}: {{ $d->resolvedBy?->party?->display_name ?? '—' }}
                    @if ($d->resolved_at) · {{ $d->resolved_at->format('d M Y, H:i') }} @endif
                </div>
            @endif
        </section>

        <div class="hm-stack">
            <section class="hm-card">
                <div class="hm-phead"><h2>{{ __('admin.dispute.engagement') }}</h2></div>
                <div class="hm-kv">
                    <div class="r"><span class="l">{{ __('admin.col.job') }}</span><span class="val">{{ $e?->job?->reference ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.skill') }}</span><span class="val">{{ $e?->job?->skill?->name_fr ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.mode') }}</span><span class="val">{{ $e?->job?->engagement_mode?->value ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.customer') }}</span><span class="val">{{ $e?->job?->customer?->display_name ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.col.provider') }}</span><span class="val">{{ $e?->provider?->display_name ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.accepted') }}</span><span class="val">{{ $e?->accepted_at?->format('d M Y') ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.completed') }}</span><span class="val">{{ $e?->completed_at?->format('d M Y') ?? '—' }}</span></div>
                </div>
            </section>

            @if ($e)
                <a class="hm-linkcard" href="{{ \App\Filament\Resources\Engagements\EngagementResource::getUrl('view', ['record' => $e]) }}">
                    <span>{{ __('admin.dispute.open_engagement') }}</span>
                    <span aria-hidden="true">→</span>
                </a>
            @endif
        </div>
    </div>
</div>
</div>
