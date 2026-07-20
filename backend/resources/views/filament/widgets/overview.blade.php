@php
    /** @var array<int,array<string,mixed>> $stats */
    /** @var \Illuminate\Support\Collection $engagements */
    /** @var \Illuminate\Support\Collection $exceptions */
    /** @var array<string,int> $money */

    $pillClass = fn (string $s): string => match ($s) {
        'completed', 'closed' => 'hm-completed',
        'in_progress', 'work_submitted' => 'hm-progress',
        'engaged', 'scheduled', 'en_route' => 'hm-engaged',
        'disputed', 'cancelled' => 'hm-danger',
        default => 'hm-engaged',
    };
    // Accent classes resolve to semantic tokens — never a literal colour (doc 08).
    $accents = ['', 'accent-info', 'accent-warning', 'accent-muted'];
    $initials = function (?string $name): string {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $a = mb_substr($parts[0] ?? '?', 0, 1);
        $b = mb_substr($parts[1] ?? '', 0, 1);
        return mb_strtoupper($a.$b);
    };
    $totalMoney = max(($money['escrow'] ?? 0) + ($money['payable'] ?? 0) + ($money['lead'] ?? 0), 1);
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    {{-- KPIs --}}
    <div class="hm-kpis">
        @foreach ($stats as $s)
            <div class="hm-card hm-kpi {{ ($s['flag'] ?? null) === 'attention' ? 'hm-attention' : '' }}">
                <div class="hm-label">{{ $s['label'] }}</div>
                <div class="hm-value">{{ $s['value'] }}@if($s['unit'])<span class="hm-unit">{{ $s['unit'] }}</span>@endif</div>
                <div class="hm-delta {{ $s['dir'] }}">{{ $s['desc'] }}</div>
                @if ($s['spark'])
                    @php $sc = ($s['flag'] ?? null) === 'attention' ? 'var(--hm-danger)' : 'var(--hm-brand)'; @endphp
                    <svg class="hm-spark" width="72" height="40" viewBox="0 0 72 40" fill="none" aria-hidden="true">
                        <polygon points="{{ $s['spark']['area'] }}" fill="var(--hm-brand-weak)"></polygon>
                        <polyline points="{{ $s['spark']['line'] }}" stroke="{{ $sc }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        <circle cx="{{ $s['spark']['cx'] }}" cy="{{ $s['spark']['cy'] }}" r="3" fill="{{ $sc }}"></circle>
                    </svg>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Two columns --}}
    <div class="hm-cols">
        {{-- Recent engagements --}}
        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.recent_engagements') }}</h2><a href="{{ url('/admin/engagements') }}">{{ __('admin.view_all') }}</a></div>
            <div style="overflow-x:auto">
                <table>
                    <thead><tr><th>{{ __('admin.col.job') }}</th><th>{{ __('admin.col.provider') }}</th><th>{{ __('admin.col.milestones') }}</th><th class="hm-num">{{ __('admin.col.agreed') }}</th><th>{{ __('admin.col.status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($engagements as $e)
                            @php
                                $total = (int) ($e->milestones_count ?? 0);
                                $done = (int) ($e->milestones_paid ?? 0);
                                $status = $e->job?->status?->value ?? 'engaged';
                                $mode = $e->job?->engagement_mode?->value ?? '';
                                $skill = $e->job?->skill?->name_fr ?? '—';
                                $prov = $e->provider?->display_name ?? 'Provider';
                                $accent = $accents[crc32((string) $e->provider_party_id) % count($accents)];
                            @endphp
                            <tr>
                                <td><div class="hm-ref">{{ $e->job?->reference ?? '—' }}</div><div class="hm-sub">{{ $skill }} · {{ $mode }}</div></td>
                                <td><div class="hm-prov"><span class="hm-pa {{ $accent }}">{{ $initials($prov) }}</span><div>{{ \Illuminate\Support\Str::limit($prov, 22) }}</div></div></td>
                                <td>
                                    <div class="hm-mstones">
                                        @for ($i = 0; $i < max($total, 1); $i++)<i class="{{ $i < $done ? 'hm-done' : '' }}"></i>@endfor
                                    </div>
                                </td>
                                <td class="hm-num">{{ number_format((int) $e->agreed_amount_minor, 0, '.', ' ') }}</td>
                                <td><span class="hm-pill {{ $pillClass($status) }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="hm-empty">{{ __('admin.no_engagements') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Attention + money --}}
        <div class="hm-stack">
            <section class="hm-card">
                <div class="hm-phead"><h2>{{ __('admin.needs_attention') }}</h2><a href="#">{{ __('admin.reconciliation') }}</a></div>
                @forelse ($exceptions as $x)
                    @php $crit = $x->kind === 'settlement_mismatch'; @endphp
                    <div class="hm-exc {{ $crit ? 'hm-crit' : 'hm-warn' }}">
                        <div class="hm-sev"></div>
                        <div class="hm-body">
                            <b>{{ ucfirst(str_replace('_', ' ', $x->kind)) }}</b>
                            <p>{{ \Illuminate\Support\Str::limit($x->detail, 96) }}</p>
                            <span class="hm-chip {{ $crit ? 'hm-crit' : 'hm-warn' }}">{{ $crit ? __('admin.critical') : __('admin.watching') }}</span>
                        </div>
                        @if ($x->amount_minor !== null)<div class="hm-amt">{{ number_format((int) $x->amount_minor, 0, '.', ' ') }}</div>@endif
                    </div>
                @empty
                    <div class="hm-empty">{{ __('admin.all_clear') }}</div>
                @endforelse
            </section>

            <section class="hm-card">
                <div class="hm-phead"><h2>{{ __('admin.money_held') }}</h2><span class="hm-sub">{{ __('admin.now') }}</span></div>
                <div class="hm-ledger">
                    <div class="hm-lbar">
                        <span style="width:{{ round(($money['escrow'] / $totalMoney) * 100, 1) }}%;background:var(--hm-info)"></span>
                        <span style="width:{{ round(($money['payable'] / $totalMoney) * 100, 1) }}%;background:var(--hm-brand)"></span>
                        <span style="width:{{ round(($money['lead'] / $totalMoney) * 100, 1) }}%;background:var(--hm-warning)"></span>
                    </div>
                    <div class="hm-lrow"><div class="hm-k"><i style="background:var(--hm-info)"></i> {{ __('admin.escrow_liability') }}</div><div class="hm-v">{{ number_format($money['escrow'], 0, '.', ' ') }}</div></div>
                    <div class="hm-lrow"><div class="hm-k"><i style="background:var(--hm-brand)"></i> {{ __('admin.provider_payable') }}</div><div class="hm-v">{{ number_format($money['payable'], 0, '.', ' ') }}</div></div>
                    <div class="hm-lrow"><div class="hm-k"><i style="background:var(--hm-warning)"></i> {{ __('admin.lead_float') }}</div><div class="hm-v">{{ number_format($money['lead'], 0, '.', ' ') }}</div></div>
                    <div class="hm-lrow hm-tot"><div class="hm-k" style="color:var(--hm-text);font-weight:700">{{ __('admin.gateway_receivable') }}</div><div class="hm-v">{{ number_format($money['receivable'], 0, '.', ' ') }}</div></div>
                </div>
            </section>
        </div>
    </div>

    <div class="hm-foot">{{ __('admin.ledger_note') }}</div>
</div>
</div>
