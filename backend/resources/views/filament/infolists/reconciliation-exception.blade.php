@php
    /** @var \App\Models\ReconciliationException $x */
    $x = $getRecord();
    $x->loadMissing('resolvedBy');

    $open = $x->status === \App\Models\ReconciliationException::STATUS_OPEN;
    $money = fn (?int $m): string => $m === null ? '—' : number_format($m, 0, '.', ' ');
    $age = (int) $x->detected_at->diffInDays();
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    <section class="hm-card">
        <div class="hm-head">
            <span class="hm-pa hm-pa-lg" style="background:var({{ $open ? '--hm-danger' : '--hm-success' }})" aria-hidden="true">!</span>
            <div style="flex:1">
                <div class="hm-title">{{ __('admin.recon.kind.'.$x->kind) }}</div>
                <div class="hm-sub">{{ __('admin.recon.detected') }} {{ $x->detected_at->format('d M Y, H:i') }}</div>
            </div>
            <span class="hm-pill {{ $open ? 'hm-danger' : 'hm-completed' }}">
                {{ $open ? __('admin.recon.status.open') : __('admin.recon.status.resolved') }}
            </span>
        </div>
    </section>

    <div class="hm-mgrid">
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.amount') }}</div>
            <div class="hm-mv hm-num" style="text-align:left">{{ $money($x->amount_minor) }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.dispute.age') }}</div>
            <div class="hm-mv">{{ $age }} <small>{{ __('admin.dispute.days') }}</small></div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.recon.reference') }}</div>
            <div class="hm-mv" style="font-size:15px">{{ $x->reference_type ? str_replace('_', ' ', $x->reference_type) : '—' }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.dispute.ledger_effect') }}</div>
            <div class="hm-mv" style="font-size:15px">
                {{ $x->resolution_transaction_id ? __('admin.dispute.adjustment_posted') : __('admin.dispute.no_ledger_effect') }}
            </div>
        </div>
    </div>

    <div class="hm-two">
        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.recon.what_the_check_found') }}</h2></div>
            <p class="hm-body">{{ $x->detail }}</p>
            {{-- Stated on the page, not just in a code comment: the nightly job never corrects a
                 discrepancy by itself, so nothing here has been silently adjusted. --}}
            <p class="hm-sub" style="margin-top:14px">{{ __('admin.recon.never_auto_corrected') }}</p>
        </section>

        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.details') }}</h2></div>
            <div class="hm-kv">
                <div class="r"><span class="l">{{ __('admin.recon.reference') }}</span><span class="val">{{ $x->reference_id ?? '—' }}</span></div>
                <div class="r"><span class="l">{{ __('admin.recon.detected') }}</span><span class="val">{{ $x->detected_at->format('d M Y, H:i') }}</span></div>
                <div class="r"><span class="l">{{ __('admin.recon.resolved_at') }}</span><span class="val">{{ $x->resolved_at?->format('d M Y, H:i') ?? '—' }}</span></div>
                <div class="r"><span class="l">{{ __('admin.dispute.decided_by') }}</span><span class="val">{{ $x->resolvedBy?->party?->display_name ?? '—' }}</span></div>
                <div class="r"><span class="l">{{ __('admin.recon.transaction') }}</span><span class="val">{{ $x->resolution_transaction_id ?? '—' }}</span></div>
            </div>
        </section>
    </div>
</div>
</div>
