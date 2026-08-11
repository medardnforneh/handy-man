@php
    /** @var \App\Models\Report $r */
    $r = $getRecord();
    $r->loadMissing(['subject', 'reporter', 'job.skill']);

    $closed = in_array($r->status, ['resolved', 'dismissed'], true);
    $pill = match ($r->status) {
        'resolved' => 'hm-completed',
        'dismissed' => 'hm-neutral',
        'reviewing' => 'hm-progress',
        default => 'hm-danger',
    };
    $initials = function (?string $n): string {
        $p = preg_split('/\s+/', trim((string) $n)) ?: [];

        return mb_strtoupper(mb_substr($p[0] ?? '?', 0, 1).mb_substr($p[1] ?? '', 0, 1));
    };

    // How many other reports name this same subject: one complaint is an incident, a pattern is a
    // different decision. Open ones only — a dismissed report should not count against anyone.
    $priors = \App\Models\Report::query()
        ->where('subject_party_id', $r->subject_party_id)
        ->where('id', '!=', $r->id)
        ->whereIn('status', ['open', 'reviewing', 'resolved'])
        ->count();

    $decision = \App\Models\ActivityLog::query()
        ->where('subject_type', $r->getMorphClass())
        ->where('subject_id', $r->id)
        ->where('action', 'report.reviewed')
        ->latest('created_at')
        ->first();
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    <section class="hm-card">
        <div class="hm-head">
            <span class="hm-pa hm-pa-lg" style="background:var(--hm-warning)">{{ $initials($r->subject?->display_name) }}</span>
            <div style="flex:1">
                <div class="hm-title">
                    {{ __('admin.report.category.'.$r->category) }}
                    <small>{{ __('admin.report.against') }} {{ $r->subject?->display_name }}</small>
                </div>
                <div class="hm-sub">
                    {{ __('admin.report.filed_by') }}: {{ $r->reporter?->display_name ?? '—' }}
                    &nbsp;·&nbsp; {{ $r->created_at?->format('d M Y, H:i') }}
                </div>
            </div>
            <span class="hm-pill {{ $pill }}">{{ __('admin.report.status.'.$r->status) }}</span>
        </div>
    </section>

    <div class="hm-mgrid">
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.report.prior_reports') }}</div>
            <div class="hm-mv" @if ($priors > 0) style="color:var(--hm-warning)" @endif>{{ $priors }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.dispute.age') }}</div>
            <div class="hm-mv">{{ (int) ($r->created_at?->diffInDays() ?? 0) }} <small>{{ __('admin.dispute.days') }}</small></div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.col.job') }}</div>
            <div class="hm-mv" style="font-size:15px">{{ $r->job?->reference ?? '—' }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.report.effect') }}</div>
            <div class="hm-mv" style="font-size:15px">{{ __('admin.report.no_penalty') }}</div>
        </div>
    </div>

    <div class="hm-two">
        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.report.what_was_reported') }}</h2></div>
            <p class="hm-body">{{ $r->body }}</p>

            @if ($decision)
                <div class="hm-phead" style="margin-top:18px"><h2>{{ __('admin.report.decision') }}</h2></div>
                <p class="hm-body">{{ $decision->context['note'] ?? '' }}</p>
                <div class="hm-sub" style="margin-top:8px">
                    {{ __('admin.report.status.'.($decision->context['decision'] ?? $r->status)) }}
                    · {{ $decision->created_at?->format('d M Y, H:i') }}
                </div>
            @endif
        </section>

        <div class="hm-stack">
            <section class="hm-card">
                <div class="hm-phead"><h2>{{ __('admin.details') }}</h2></div>
                <div class="hm-kv">
                    <div class="r"><span class="l">{{ __('admin.report.subject') }}</span><span class="val">{{ $r->subject?->display_name ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.report.reporter') }}</span><span class="val">{{ $r->reporter?->display_name ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.skill') }}</span><span class="val">{{ $r->job?->skill?->name_fr ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.recon.resolved_at') }}</span><span class="val">{{ $r->resolved_at?->format('d M Y, H:i') ?? '—' }}</span></div>
                </div>
            </section>

            {{-- Said out loud on the page: a report is a signal for a human, never an automatic
                 penalty. Staff should not assume the platform has already acted. --}}
            <div class="hm-card" style="padding:14px 16px">
                <p class="hm-sub" style="margin:0">{{ __('admin.report.never_auto_penalises') }}</p>
            </div>
        </div>
    </div>
</div>
</div>
