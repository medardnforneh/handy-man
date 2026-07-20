@php
    /** @var \App\Models\Engagement $e */
    $e = $getRecord();
    $e->loadMissing(['job.skill', 'job.customer', 'provider', 'milestones', 'assignments.worker.party']);

    $ledger = app(\App\Domain\Money\Ledger::class);
    $held = $ledger->escrowHeldMinor($e->id, $e->currency);
    $released = (int) $e->milestones->whereIn('status', [\App\Domain\Engagements\MilestoneStatus::Approved, \App\Domain\Engagements\MilestoneStatus::Paid])->sum('amount_minor');

    $money = fn (int $m): string => number_format($m, 0, '.', ' ');
    $status = $e->job?->status?->value ?? 'engaged';
    $pill = match ($status) {
        'completed', 'closed' => 'hm-completed',
        'in_progress', 'work_submitted' => 'hm-progress',
        'disputed', 'cancelled' => 'hm-danger',
        default => 'hm-engaged',
    };
    $initials = function (?string $n): string {
        $p = preg_split('/\s+/', trim((string) $n)) ?: [];
        return mb_strtoupper(mb_substr($p[0] ?? '?', 0, 1).mb_substr($p[1] ?? '', 0, 1));
    };
    $origin = $e->quotation_id ? __('admin.origin_quote') : ($e->offer_id ? __('admin.origin_offer') : '—');
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    {{-- Header --}}
    <section class="hm-card">
        <div class="hm-head">
            <span class="hm-pa" style="width:42px;height:42px;border-radius:11px;font-size:15px;background:var(--hm-brand)">{{ $initials($e->provider?->display_name) }}</span>
            <div style="flex:1">
                <div class="hm-title">{{ $e->job?->reference ?? 'Engagement' }} <small>{{ $e->job?->skill?->name_fr }} · {{ $e->job?->engagement_mode?->value }}</small></div>
                <div class="hm-sub">{{ $e->provider?->display_name }} &nbsp;·&nbsp; {{ __('admin.customer') }}: {{ $e->job?->customer?->display_name }}</div>
            </div>
            <span class="hm-pill {{ $pill }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
        </div>
    </section>

    {{-- Money metrics --}}
    <div class="hm-mgrid">
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.agreed_amount') }}</div>
            <div class="hm-mv">{{ $money($e->agreed_amount_minor) }} <small>{{ $e->currency }}</small></div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.kpi.escrow_held') }}</div>
            <div class="hm-mv" style="color:var(--hm-info)">{{ $money($held) }} <small>{{ $e->currency }}</small></div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.released') }}</div>
            <div class="hm-mv" style="color:var(--hm-brand)">{{ $money($released) }} <small>{{ $e->currency }}</small></div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.visit_credit') }}</div>
            <div class="hm-mv">{{ $money($e->visit_credit_minor) }} <small>{{ $e->currency }}</small></div>
        </div>
    </div>

    <div class="hm-two">
        {{-- Milestone timeline --}}
        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.col.milestones') }}</h2><span class="hm-sub">{{ $e->milestones->count() }} · {{ __('admin.escrow_releases') }}</span></div>
            <div class="hm-tl">
                @forelse ($e->milestones->sortBy('position') as $m)
                    @php $paid = in_array($m->status->value, ['approved', 'paid'], true); @endphp
                    <div class="hm-mile">
                        <span class="hm-dot {{ $paid ? 'hm-paid' : '' }}">{{ $paid ? '✓' : $m->position }}</span>
                        <div class="hm-mt">
                            <b>{{ $m->title }}</b>
                            <div class="hm-sub">{{ ucfirst(str_replace('_', ' ', $m->status->value)) }}</div>
                        </div>
                        <div class="hm-num" style="font-weight:700">{{ $money($m->amount_minor) }}</div>
                    </div>
                @empty
                    <div class="hm-empty">{{ __('admin.no_milestones') }}</div>
                @endforelse
            </div>
        </section>

        {{-- Assignments + facts --}}
        <div class="hm-stack">
            <section class="hm-card">
                <div class="hm-phead"><h2>{{ __('admin.workforce') }}</h2><span class="hm-sub">{{ $e->assignments->where('status', '!=', 'removed')->count() }} {{ __('admin.active') }}</span></div>
                <div class="hm-tl">
                    @forelse ($e->assignments->where('status', '!=', 'removed') as $a)
                        <div class="hm-mile">
                            <span class="hm-pa" style="background:var(--hm-brand)">{{ $initials($a->worker?->party?->display_name) }}</span>
                            <div class="hm-mt">
                                <b>{{ $a->worker?->party?->display_name ?? 'Worker' }}</b>
                                <div class="hm-sub">{{ ucfirst($a->role->value) }} · {{ str_replace('_', ' ', $a->status->value) }}</div>
                            </div>
                            @if ($a->scheduled_from)
                                <div class="hm-sub">{{ $a->scheduled_from->format('d M H:i') }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="hm-empty">{{ __('admin.no_workers') }}</div>
                    @endforelse
                </div>
            </section>

            <section class="hm-card">
                <div class="hm-phead"><h2>{{ __('admin.details') }}</h2></div>
                <div class="hm-kv">
                    <div class="r"><span class="l">{{ __('admin.origin') }}</span><span class="val">{{ $origin }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.escrowed') }}</span><span class="val">{{ $e->is_escrowed ? __('admin.escrowed_yes') : __('admin.escrowed_on_collection') }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.accepted') }}</span><span class="val">{{ $e->accepted_at?->format('d M Y, H:i') }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.completed') }}</span><span class="val">{{ $e->completed_at?->format('d M Y, H:i') ?? '—' }}</span></div>
                </div>
            </section>
        </div>
    </div>
</div>
</div>
