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
        <div class="hm-card hm-metric"><div class="hm-mk">Budget</div><div class="hm-mv">{{ $money($j->budget_minor) }} <small>{{ $j->currency }}</small></div></div>
        <div class="hm-card hm-metric"><div class="hm-mk">Mode</div><div class="hm-mv" style="font-size:18px">{{ ucfirst($j->engagement_mode->value) }}</div></div>
        <div class="hm-card hm-metric"><div class="hm-mk">Price model</div><div class="hm-mv" style="font-size:18px">{{ str_replace('_', ' ', $j->price_model) }}</div></div>
        <div class="hm-card hm-metric"><div class="hm-mk">Urgency</div><div class="hm-mv">{{ $j->urgency }} <small>/ 5</small></div></div>
    </div>

    <div class="hm-two">
        <section class="hm-card">
            <div class="hm-phead"><h2>Details</h2></div>
            <div class="hm-kv">
                <div class="r"><span class="l">Customer</span><span class="val">{{ $j->customer?->display_name ?? '—' }}</span></div>
                <div class="r"><span class="l">Skill</span><span class="val">{{ $j->skill?->name_fr }} · {{ $j->skill?->name_en }}</span></div>
                <div class="r"><span class="l">Assignment</span><span class="val">{{ ucfirst($j->assignment_mode->value) }}</span></div>
                <div class="r"><span class="l">Verified provider</span><span class="val">{{ $j->requires_verified_provider ? 'Required' : 'Not required' }}</span></div>
                <div class="r"><span class="l">Published</span><span class="val">{{ $j->published_at?->format('d M Y, H:i') ?? '—' }}</span></div>
                @if ($j->cancelled_at)
                    <div class="r"><span class="l">Cancelled</span><span class="val">{{ $j->cancelled_at->format('d M Y, H:i') }}</span></div>
                @endif
            </div>
        </section>

        <section class="hm-card">
            <div class="hm-phead"><h2>{{ $j->engagement_mode->value === 'remote' ? 'Remote work' : 'Location' }}</h2><span class="hm-sub">{{ $j->photos->count() }} photo(s)</span></div>
            <div class="hm-kv">
                @if ($addr)
                    <div class="r"><span class="l">Address</span><span class="val">{{ $addr->line1 ?? '—' }}</span></div>
                    <div class="r"><span class="l">Quarter</span><span class="val">{{ $addr->quarter ?? '—' }}</span></div>
                    <div class="r"><span class="l">City</span><span class="val">{{ $addr->city ?? '—' }} · {{ $addr->region ?? '' }}</span></div>
                    @if ($addr->landmark_note)
                        <div class="r"><span class="l">Landmark</span><span class="val">{{ \Illuminate\Support\Str::limit($addr->landmark_note, 40) }}</span></div>
                    @endif
                @else
                    <div class="hm-empty" style="padding:8px 0">No address — this job is done remotely.</div>
                @endif
            </div>
        </section>
    </div>

    @if ($j->description)
        <section class="hm-card">
            <div class="hm-phead"><h2>Description</h2></div>
            <div style="padding:14px 16px; color:var(--hm-text); font-size:13.5px; line-height:1.6">{{ $j->description }}</div>
        </section>
    @endif
</div>
</div>
