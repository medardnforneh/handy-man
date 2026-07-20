@php
    /** @var \App\Models\JobOffer $o */
    $o = $getRecord();
    $o->loadMissing(['job', 'provider']);

    $money = fn (?int $m): string => $m === null ? '—' : number_format($m, 0, '.', ' ');
    $status = $o->status->value;
    $pill = match ($status) {
        'accepted' => 'hm-completed',
        'pending' => 'hm-engaged',
        'declined', 'withdrawn', 'expired', 'superseded' => 'hm-neutral',
        default => 'hm-neutral',
    };
    $initials = function (?string $n): string {
        $p = preg_split('/\s+/', trim((string) $n)) ?: [];
        return mb_strtoupper(mb_substr($p[0] ?? '?', 0, 1).mb_substr($p[1] ?? '', 0, 1));
    };
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    <section class="hm-card">
        <div class="hm-head">
            <span class="hm-pa" style="width:38px;height:38px;border-radius:10px;font-size:14px;background:var(--hm-brand)">{{ $initials($o->provider?->display_name) }}</span>
            <div style="flex:1; min-width:0">
                <div class="hm-title">{{ $o->provider?->display_name ?? __('admin.col.provider') }} <small>{{ __('admin.on_job') }} {{ $o->job?->reference }}</small></div>
                <div class="hm-sub">{{ str_replace('_', ' ', $o->origin->value) }} {{ __('admin.offer_suffix') }}</div>
            </div>
            <span class="hm-pill {{ $pill }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
        </div>
    </section>

    <div class="hm-mgrid" style="grid-template-columns:repeat(3,1fr)">
        <div class="hm-card hm-metric"><div class="hm-mk">{{ __('admin.amount') }}</div><div class="hm-mv">{{ $money($o->amount_minor) }} <small>{{ $o->currency }}</small></div></div>
        <div class="hm-card hm-metric"><div class="hm-mk">{{ __('admin.expires') }}</div><div class="hm-mv" style="font-size:16px">{{ $o->expires_at->format('d M Y, H:i') }}</div></div>
        <div class="hm-card hm-metric"><div class="hm-mk">{{ __('admin.responded') }}</div><div class="hm-mv" style="font-size:16px">{{ $o->responded_at?->format('d M Y, H:i') ?? __('admin.awaiting') }}</div></div>
    </div>

    <section class="hm-card">
        <div class="hm-phead"><h2>{{ __('admin.message') }}</h2></div>
        <div style="padding:14px 16px; color:var(--hm-text); font-size:13.5px; line-height:1.6">{{ $o->message ?: __('admin.no_message') }}</div>
    </section>
</div>
</div>
