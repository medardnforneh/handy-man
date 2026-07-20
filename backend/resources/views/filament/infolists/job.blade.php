@php
    /** @var \App\Models\Job $j */
    $j = $getRecord();
    $j->loadMissing(['skill', 'customer', 'address', 'photos']);

    $money = fn (?int $m): string => $m === null ? '—' : number_format($m, 0, '.', ' ');
    $status = $j->status->value;
    $pill = match ($status) {
        'completed', 'closed' => 'hm-completed',
        'in_progress', 'work_submitted' => 'hm-progress',
        'disputed', 'cancelled' => 'hm-danger',
        'draft' => 'hm-neutral',
        default => 'hm-engaged',
    };
    $addr = $j->address;
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    <section class="hm-card">
        <div class="hm-head">
            <div style="flex:1; min-width:0">
                <div class="hm-title">{{ $j->reference }} <small>{{ $j->skill?->name_fr }}</small></div>
                <div class="hm-sub">{{ $j->title }}</div>
            </div>
            <span class="hm-pill {{ $pill }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
        </div>
    </section>

    <div class="hm-mgrid">
        <div class="hm-card hm-metric"><div class="hm-mk">{{ __('admin.budget') }}</div><div class="hm-mv">{{ $money($j->budget_minor) }} <small>{{ $j->currency }}</small></div></div>
        <div class="hm-card hm-metric"><div class="hm-mk">{{ __('admin.mode') }}</div><div class="hm-mv" style="font-size:18px">{{ ucfirst($j->engagement_mode->value) }}</div></div>
        <div class="hm-card hm-metric"><div class="hm-mk">{{ __('admin.price_model') }}</div><div class="hm-mv" style="font-size:18px">{{ str_replace('_', ' ', $j->price_model) }}</div></div>
        <div class="hm-card hm-metric"><div class="hm-mk">{{ __('admin.urgency') }}</div><div class="hm-mv">{{ $j->urgency }}</div></div>
    </div>

    <div class="hm-two">
        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.details') }}</h2></div>
            <div class="hm-kv">
                <div class="r"><span class="l">{{ __('admin.customer') }}</span><span class="val">{{ $j->customer?->display_name ?? '—' }}</span></div>
                <div class="r"><span class="l">{{ __('admin.skill') }}</span><span class="val">{{ $j->skill?->name_fr }} · {{ $j->skill?->name_en }}</span></div>
                <div class="r"><span class="l">{{ __('admin.assignment') }}</span><span class="val">{{ ucfirst($j->assignment_mode->value) }}</span></div>
                <div class="r"><span class="l">{{ __('admin.verified_provider') }}</span><span class="val">{{ $j->requires_verified_provider ? __('admin.required') : __('admin.not_required') }}</span></div>
                <div class="r"><span class="l">{{ __('admin.published') }}</span><span class="val">{{ $j->published_at?->format('d M Y, H:i') ?? '—' }}</span></div>
                @if ($j->cancelled_at)
                    <div class="r"><span class="l">{{ __('admin.cancelled') }}</span><span class="val">{{ $j->cancelled_at->format('d M Y, H:i') }}</span></div>
                @endif
            </div>
        </section>

        <section class="hm-card">
            <div class="hm-phead"><h2>{{ $j->engagement_mode->value === 'remote' ? __('admin.remote_work') : __('admin.location') }}</h2><span class="hm-sub">{{ $j->photos->count() }} {{ __('admin.photos') }}</span></div>
            <div class="hm-kv">
                @if ($addr)
                    <div class="r"><span class="l">{{ __('admin.address') }}</span><span class="val">{{ $addr->line1 ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.quarter') }}</span><span class="val">{{ $addr->quarter ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.city') }}</span><span class="val">{{ $addr->city ?? '—' }} · {{ $addr->region ?? '' }}</span></div>
                    @if ($addr->landmark_note)
                        <div class="r"><span class="l">{{ __('admin.landmark') }}</span><span class="val">{{ \Illuminate\Support\Str::limit($addr->landmark_note, 40) }}</span></div>
                    @endif
                @else
                    <div class="hm-empty" style="padding:8px 0">{{ __('admin.no_address') }}</div>
                @endif
            </div>
        </section>
    </div>

    @if ($j->description)
        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.description') }}</h2></div>
            <div style="padding:14px 16px; color:var(--hm-text); font-size:13.5px; line-height:1.6">{{ $j->description }}</div>
        </section>
    @endif
</div>
</div>
