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
    $avatarPalette = ['#0a7d54', '#1f6feb', '#b3620a', '#5b6472', '#7c3aed'];
    $initials = function (?string $name): string {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $a = mb_substr($parts[0] ?? '?', 0, 1);
        $b = mb_substr($parts[1] ?? '', 0, 1);
        return mb_strtoupper($a.$b);
    };
    $totalMoney = max(($money['escrow'] ?? 0) + ($money['payable'] ?? 0) + ($money['lead'] ?? 0), 1);
@endphp

<div class="hm-dash">
<style>
    .hm-dash {
        --hm-surface:#ffffff; --hm-base:#f7f8fa; --hm-sunken:#eceef1;
        --hm-text:#16181d; --hm-muted:#5b6472; --hm-border:#e3e6ea; --hm-border-strong:#c4c9d0;
        --hm-brand:#0a7d54; --hm-brand-weak:rgba(10,125,84,.10); --hm-on-brand:#fff;
        --hm-success:#1a7f43; --hm-warning:#b3620a; --hm-danger:#c0392b; --hm-info:#1f6feb;
        --hm-success-w:rgba(26,127,67,.12); --hm-warning-w:rgba(179,98,10,.12);
        --hm-danger-w:rgba(192,57,43,.12); --hm-info-w:rgba(31,111,235,.12);
        --hm-shadow:0 1px 2px rgba(16,24,32,.04), 0 4px 16px rgba(16,24,32,.05);
        color:var(--hm-text);
        font-variant-numeric: tabular-nums;
    }
    .dark .hm-dash {
        --hm-surface:#171b21; --hm-base:#0f1216; --hm-sunken:#0a0c0f;
        --hm-text:#e8eaed; --hm-muted:#9aa4b2; --hm-border:#262b33; --hm-border-strong:#3a414c;
        --hm-brand:#2ea77d; --hm-brand-weak:rgba(46,167,125,.14); --hm-on-brand:#05130d;
        --hm-success:#35b46a; --hm-warning:#e0912f; --hm-danger:#e5675a; --hm-info:#4f9dff;
        --hm-success-w:rgba(53,180,106,.15); --hm-warning-w:rgba(224,145,47,.15);
        --hm-danger-w:rgba(229,103,90,.16); --hm-info-w:rgba(79,157,255,.15);
        --hm-shadow:0 1px 2px rgba(0,0,0,.4), 0 6px 20px rgba(0,0,0,.35);
    }
    .hm-dash * { box-sizing:border-box; }
    .hm-dash .hm-grid { display:flex; flex-direction:column; gap:24px; }
    .hm-dash .hm-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
    .hm-dash .hm-card { background:var(--hm-surface); border:1px solid var(--hm-border); border-radius:16px; box-shadow:var(--hm-shadow); }
    .hm-dash .hm-kpi { padding:16px; display:grid; grid-template-columns:1fr auto; gap:4px 12px; align-items:start; }
    .hm-dash .hm-kpi .hm-label { grid-column:1; font-size:12px; color:var(--hm-muted); font-weight:600; }
    .hm-dash .hm-kpi .hm-value { grid-column:1; font-size:25px; font-weight:700; letter-spacing:-.02em; }
    .hm-dash .hm-kpi .hm-value .hm-unit { font-size:13px; color:var(--hm-muted); font-weight:600; margin-left:3px; }
    .hm-dash .hm-kpi .hm-spark { grid-column:2; grid-row:1 / span 3; align-self:center; }
    .hm-dash .hm-delta { grid-column:1; font-size:12px; font-weight:600; }
    .hm-dash .hm-delta.up { color:var(--hm-success); } .hm-dash .hm-delta.down { color:var(--hm-danger); } .hm-dash .hm-delta.flat { color:var(--hm-muted); }
    .hm-dash .hm-kpi.hm-attention { outline:1px solid var(--hm-danger-w); }
    .hm-dash .hm-cols { display:grid; grid-template-columns:1.6fr 1fr; gap:24px; align-items:start; }
    .hm-dash .hm-phead { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--hm-border); }
    .hm-dash .hm-phead h2 { margin:0; font-size:14px; letter-spacing:-.01em; font-weight:700; }
    .hm-dash .hm-phead a { font-size:12.5px; color:var(--hm-brand); font-weight:600; text-decoration:none; }
    .hm-dash table { width:100%; border-collapse:collapse; font-size:13px; }
    .hm-dash thead th { text-align:left; font-size:10.5px; letter-spacing:.07em; text-transform:uppercase; color:var(--hm-muted); font-weight:700; padding:10px 16px; border-bottom:1px solid var(--hm-border); }
    .hm-dash tbody td { padding:12px 16px; border-bottom:1px solid var(--hm-border); vertical-align:middle; }
    .hm-dash tbody tr:last-child td { border-bottom:0; }
    .hm-dash .hm-num { text-align:right; font-variant-numeric:tabular-nums; }
    .hm-dash .hm-ref { font-weight:600; }
    .hm-dash .hm-sub { color:var(--hm-muted); font-size:12px; }
    .hm-dash .hm-prov { display:flex; align-items:center; gap:9px; }
    .hm-dash .hm-pa { width:26px; height:26px; border-radius:7px; flex:none; display:grid; place-items:center; font-weight:700; font-size:11px; color:#fff; }
    .hm-dash .hm-pill { display:inline-flex; align-items:center; gap:6px; padding:3px 9px; border-radius:9999px; font-size:11.5px; font-weight:600; white-space:nowrap; }
    .hm-dash .hm-pill::before { content:""; width:6px; height:6px; border-radius:50%; background:currentColor; }
    .hm-dash .hm-engaged { color:var(--hm-info); background:var(--hm-info-w); }
    .hm-dash .hm-progress { color:var(--hm-warning); background:var(--hm-warning-w); }
    .hm-dash .hm-completed { color:var(--hm-success); background:var(--hm-success-w); }
    .hm-dash .hm-danger { color:var(--hm-danger); background:var(--hm-danger-w); }
    .hm-dash .hm-mstones { display:flex; gap:3px; }
    .hm-dash .hm-mstones i { width:22px; height:5px; border-radius:3px; background:var(--hm-border-strong); }
    .hm-dash .hm-mstones i.hm-done { background:var(--hm-brand); }
    .hm-dash .hm-stack { display:flex; flex-direction:column; gap:24px; }
    .hm-dash .hm-exc { display:flex; gap:12px; padding:14px 16px; border-bottom:1px solid var(--hm-border); }
    .hm-dash .hm-exc:last-child { border-bottom:0; }
    .hm-dash .hm-exc .hm-sev { width:4px; border-radius:3px; flex:none; }
    .hm-dash .hm-exc.hm-crit .hm-sev { background:var(--hm-danger); } .hm-dash .hm-exc.hm-warn .hm-sev { background:var(--hm-warning); }
    .hm-dash .hm-exc .hm-body { flex:1; min-width:0; }
    .hm-dash .hm-exc .hm-body b { font-size:13px; }
    .hm-dash .hm-exc .hm-body p { margin:2px 0 0; color:var(--hm-muted); font-size:12px; }
    .hm-dash .hm-exc .hm-amt { font-weight:700; font-size:13px; }
    .hm-dash .hm-exc.hm-crit .hm-amt { color:var(--hm-danger); }
    .hm-dash .hm-chip { display:inline-block; margin-top:7px; font-size:10.5px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; padding:2px 7px; border-radius:6px; }
    .hm-dash .hm-chip.hm-crit { color:var(--hm-danger); background:var(--hm-danger-w); }
    .hm-dash .hm-chip.hm-warn { color:var(--hm-warning); background:var(--hm-warning-w); }
    .hm-dash .hm-empty { padding:26px 16px; text-align:center; color:var(--hm-muted); font-size:13px; }
    .hm-dash .hm-ledger { padding:16px; display:flex; flex-direction:column; gap:10px; }
    .hm-dash .hm-lbar { height:8px; border-radius:9999px; overflow:hidden; display:flex; background:var(--hm-sunken); }
    .hm-dash .hm-lbar span { height:100%; }
    .hm-dash .hm-lrow { display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .hm-dash .hm-lrow .hm-k { display:flex; align-items:center; gap:9px; color:var(--hm-muted); font-size:12.5px; font-weight:500; }
    .hm-dash .hm-lrow .hm-k i { width:9px; height:9px; border-radius:3px; }
    .hm-dash .hm-lrow .hm-v { font-weight:700; letter-spacing:-.01em; }
    .hm-dash .hm-tot { border-top:1px dashed var(--hm-border); padding-top:10px; }
    .hm-dash .hm-foot { color:var(--hm-muted); font-size:12px; text-align:center; padding-top:2px; }
    @media (max-width:1100px){ .hm-dash .hm-kpis{ grid-template-columns:repeat(2,1fr);} .hm-dash .hm-cols{ grid-template-columns:1fr;} }
    @media (max-width:640px){ .hm-dash .hm-kpis{ grid-template-columns:1fr;} }
</style>

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
            <div class="hm-phead"><h2>Recent engagements</h2><a href="{{ url('/admin/engagements') }}">View all →</a></div>
            <div style="overflow-x:auto">
                <table>
                    <thead><tr><th>Job</th><th>Provider</th><th>Milestones</th><th class="hm-num">Agreed</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($engagements as $e)
                            @php
                                $total = (int) ($e->milestones_count ?? 0);
                                $done = (int) ($e->milestones_paid ?? 0);
                                $status = $e->job?->status?->value ?? 'engaged';
                                $mode = $e->job?->engagement_mode?->value ?? '';
                                $skill = $e->job?->skill?->name_fr ?? '—';
                                $prov = $e->provider?->display_name ?? 'Provider';
                                $color = $avatarPalette[crc32((string) $e->provider_party_id) % count($avatarPalette)];
                            @endphp
                            <tr>
                                <td><div class="hm-ref">{{ $e->job?->reference ?? '—' }}</div><div class="hm-sub">{{ $skill }} · {{ $mode }}</div></td>
                                <td><div class="hm-prov"><span class="hm-pa" style="background:{{ $color }}">{{ $initials($prov) }}</span><div>{{ \Illuminate\Support\Str::limit($prov, 22) }}</div></div></td>
                                <td>
                                    <div class="hm-mstones">
                                        @for ($i = 0; $i < max($total, 1); $i++)<i class="{{ $i < $done ? 'hm-done' : '' }}"></i>@endfor
                                    </div>
                                </td>
                                <td class="hm-num">{{ number_format((int) $e->agreed_amount_minor, 0, '.', ' ') }}</td>
                                <td><span class="hm-pill {{ $pillClass($status) }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="hm-empty">No engagements yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Attention + money --}}
        <div class="hm-stack">
            <section class="hm-card">
                <div class="hm-phead"><h2>Needs attention</h2><a href="#">Reconciliation →</a></div>
                @forelse ($exceptions as $x)
                    @php $crit = $x->kind === 'settlement_mismatch'; @endphp
                    <div class="hm-exc {{ $crit ? 'hm-crit' : 'hm-warn' }}">
                        <div class="hm-sev"></div>
                        <div class="hm-body">
                            <b>{{ ucfirst(str_replace('_', ' ', $x->kind)) }}</b>
                            <p>{{ \Illuminate\Support\Str::limit($x->detail, 96) }}</p>
                            <span class="hm-chip {{ $crit ? 'hm-crit' : 'hm-warn' }}">{{ $crit ? 'Critical · unresolved' : 'Watching' }}</span>
                        </div>
                        @if ($x->amount_minor !== null)<div class="hm-amt">{{ number_format((int) $x->amount_minor, 0, '.', ' ') }}</div>@endif
                    </div>
                @empty
                    <div class="hm-empty">All clear — the ledger matches.</div>
                @endforelse
            </section>

            <section class="hm-card">
                <div class="hm-phead"><h2>Money held</h2><span class="hm-sub">now</span></div>
                <div class="hm-ledger">
                    <div class="hm-lbar">
                        <span style="width:{{ round(($money['escrow'] / $totalMoney) * 100, 1) }}%;background:var(--hm-info)"></span>
                        <span style="width:{{ round(($money['payable'] / $totalMoney) * 100, 1) }}%;background:var(--hm-brand)"></span>
                        <span style="width:{{ round(($money['lead'] / $totalMoney) * 100, 1) }}%;background:var(--hm-warning)"></span>
                    </div>
                    <div class="hm-lrow"><div class="hm-k"><i style="background:var(--hm-info)"></i> Escrow liability</div><div class="hm-v">{{ number_format($money['escrow'], 0, '.', ' ') }}</div></div>
                    <div class="hm-lrow"><div class="hm-k"><i style="background:var(--hm-brand)"></i> Provider payable</div><div class="hm-v">{{ number_format($money['payable'], 0, '.', ' ') }}</div></div>
                    <div class="hm-lrow"><div class="hm-k"><i style="background:var(--hm-warning)"></i> Lead-credit float</div><div class="hm-v">{{ number_format($money['lead'], 0, '.', ' ') }}</div></div>
                    <div class="hm-lrow hm-tot"><div class="hm-k" style="color:var(--hm-text);font-weight:700">Gateway receivable</div><div class="hm-v">{{ number_format($money['receivable'], 0, '.', ' ') }}</div></div>
                </div>
            </section>
        </div>
    </div>

    <div class="hm-foot">Balances are computed from the append-only ledger · debits = credits</div>
</div>
</div>
