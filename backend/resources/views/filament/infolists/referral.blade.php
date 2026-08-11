@php
    /** @var \App\Models\Referral $r */
    $r = $getRecord();
    $r->loadMissing(['referrer', 'referee']);

    $pill = match ($r->status) {
        'qualified' => 'hm-completed',
        'void' => 'hm-neutral',
        default => 'hm-progress',
    };
    $initials = function (?string $n): string {
        $p = preg_split('/\s+/', trim((string) $n)) ?: [];

        return mb_strtoupper(mb_substr($p[0] ?? '?', 0, 1).mb_substr($p[1] ?? '', 0, 1));
    };

    // The velocity picture the fraud control acts on (P8-02): how many people this referrer has
    // brought in, and how many actually qualified. A referrer with many claims and no completions
    // is the shape the weekly limit is looking for.
    $siblings = \App\Models\Referral::query()->where('referrer_party_id', $r->referrer_party_id);
    $totalReferred = (clone $siblings)->count();
    $qualified = (clone $siblings)->where('status', 'qualified')->count();
    $recent = (clone $siblings)->where('created_at', '>=', now()->subWeek())->count();
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    <section class="hm-card">
        <div class="hm-head">
            <span class="hm-pa hm-pa-lg" style="background:var({{ $r->flagged_for_review ? '--hm-warning' : '--hm-brand' }})">
                {{ $initials($r->referrer?->display_name) }}
            </span>
            <div style="flex:1">
                <div class="hm-title">
                    {{ $r->referrer?->display_name ?? '—' }}
                    <small>{{ __('admin.referral.referred') }} {{ $r->referee?->display_name ?? '—' }}</small>
                </div>
                <div class="hm-sub">{{ $r->created_at?->format('d M Y, H:i') }}</div>
            </div>
            @if ($r->flagged_for_review)
                <span class="hm-pill hm-progress">{{ __('admin.referral.flagged') }}</span>
            @endif
            <span class="hm-pill {{ $pill }}">{{ __('admin.referral.status.'.$r->status) }}</span>
        </div>
    </section>

    <div class="hm-mgrid">
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.referral.total_referred') }}</div>
            <div class="hm-mv">{{ $totalReferred }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.referral.qualified') }}</div>
            <div class="hm-mv" style="color:var(--hm-brand)">{{ $qualified }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.referral.this_week') }}</div>
            <div class="hm-mv" @if ($r->flagged_for_review) style="color:var(--hm-warning)" @endif>{{ $recent }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.referral.reward') }}</div>
            <div class="hm-mv" style="font-size:15px">
                {{ $r->reward_transaction_id ? __('admin.referral.reward_booked') : __('admin.dispute.no_ledger_effect') }}
            </div>
        </div>
    </div>

    <div class="hm-two">
        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.referral.why_flagged') }}</h2></div>
            @if ($r->flagged_for_review)
                <p class="hm-body">{{ $r->flag_reason ?? __('admin.referral.no_reason') }}</p>
                {{-- Flagged is not the same as fraudulent, and clearing is not an accusation
                     reversed: a flagged referral is simply not auto-rewarded until a human looks. --}}
                <p class="hm-sub" style="margin-top:14px">{{ __('admin.referral.flag_meaning') }}</p>
            @else
                <p class="hm-sub" style="margin:0">{{ __('admin.referral.not_flagged') }}</p>
            @endif
        </section>

        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.details') }}</h2></div>
            <div class="hm-kv">
                <div class="r"><span class="l">{{ __('admin.referral.referrer') }}</span><span class="val">{{ $r->referrer?->display_name ?? '—' }}</span></div>
                <div class="r"><span class="l">{{ __('admin.referral.referee') }}</span><span class="val">{{ $r->referee?->display_name ?? '—' }}</span></div>
                <div class="r"><span class="l">{{ __('admin.referral.claimed_at') }}</span><span class="val">{{ $r->created_at?->format('d M Y, H:i') ?? '—' }}</span></div>
                <div class="r"><span class="l">{{ __('admin.referral.qualified_at') }}</span><span class="val">{{ $r->qualified_at?->format('d M Y, H:i') ?? '—' }}</span></div>
                <div class="r"><span class="l">{{ __('admin.referral.reward_transaction') }}</span><span class="val">{{ $r->reward_transaction_id ?? '—' }}</span></div>
            </div>
        </section>
    </div>
</div>
</div>
