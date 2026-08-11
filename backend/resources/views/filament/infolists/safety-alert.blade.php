@php
    /** @var \App\Models\SafetyAlert $a */
    $a = $getRecord();
    $a->loadMissing(['user.party', 'resolvedBy', 'assignment.engagement.job.skill', 'assignment.engagement.job.address']);

    $party = $a->user?->party;
    $job = $a->assignment?->engagement?->job;
    $pill = match ($a->status) {
        'resolved' => 'hm-completed',
        'acknowledged' => 'hm-progress',
        default => 'hm-danger',
    };
    $initials = function (?string $n): string {
        $p = preg_split('/\s+/', trim((string) $n)) ?: [];

        return mb_strtoupper(mb_substr($p[0] ?? '?', 0, 1).mb_substr($p[1] ?? '', 0, 1));
    };

    // Who the panic SMS actually reached. RaisePanicAlert texts every emergency contact directly
    // rather than through the relay, so staff picking this up need to know it already went out —
    // and to whom — before they start phoning people themselves.
    $contacts = \App\Models\EmergencyContact::query()->where('user_id', $a->user_id)->get();

    $minutes = (int) $a->created_at->diffInMinutes();
    $elapsed = $minutes < 60
        ? __('admin.safety.minutes', ['n' => $minutes])
        : __('admin.safety.hours', ['n' => (int) floor($minutes / 60)]);
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    <section class="hm-card">
        <div class="hm-head">
            <span class="hm-pa hm-pa-lg" style="background:var(--hm-danger)">{{ $initials($party?->display_name) }}</span>
            <div style="flex:1">
                <div class="hm-title">
                    {{ __('admin.safety.kind.'.$a->kind->value) }}
                    <small>{{ $party?->display_name }}</small>
                </div>
                <div class="hm-sub">{{ $a->created_at->format('d M Y, H:i') }} · {{ $elapsed }}</div>
            </div>
            <span class="hm-pill {{ $pill }}">{{ __('admin.safety.status.'.$a->status) }}</span>
        </div>
    </section>

    <div class="hm-mgrid">
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.safety.elapsed') }}</div>
            <div class="hm-mv" @if ($a->status === 'open') style="color:var(--hm-danger)" @endif>{{ $elapsed }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.safety.contacts_texted') }}</div>
            <div class="hm-mv">{{ $contacts->count() }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.safety.location') }}</div>
            <div class="hm-mv" style="font-size:15px">{{ $a->point ? __('admin.safety.captured') : __('admin.safety.no_location') }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.col.job') }}</div>
            <div class="hm-mv" style="font-size:15px">{{ $job?->reference ?? '—' }}</div>
        </div>
    </div>

    <div class="hm-two">
        <div class="hm-stack">
            <section class="hm-card">
                <div class="hm-phead"><h2>{{ __('admin.safety.where') }}</h2></div>
                <div class="hm-kv">
                    <div class="r">
                        <span class="l">{{ __('admin.safety.coordinates') }}</span>
                        <span class="val hm-num">{{ $a->point ? number_format($a->point->latitude, 5).', '.number_format($a->point->longitude, 5) : '—' }}</span>
                    </div>
                    <div class="r"><span class="l">{{ __('admin.quarter') }}</span><span class="val">{{ $job?->address?->quarter ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.city') }}</span><span class="val">{{ $job?->address?->city ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.skill') }}</span><span class="val">{{ $job?->skill?->name_fr ?? '—' }}</span></div>
                </div>
                @if ($a->point)
                    {{-- A raw lat/long is not something a person under pressure can act on; give them
                         a map link they can hand to whoever is going there. --}}
                    <a class="hm-linkcard" style="margin-top:14px"
                       href="https://www.openstreetmap.org/?mlat={{ $a->point->latitude }}&mlon={{ $a->point->longitude }}#map=17/{{ $a->point->latitude }}/{{ $a->point->longitude }}"
                       target="_blank" rel="noopener noreferrer">
                        <span>{{ __('admin.safety.open_map') }}</span>
                        <span aria-hidden="true">→</span>
                    </a>
                @endif
            </section>

            @if ($a->note)
                <section class="hm-card">
                    <div class="hm-phead"><h2>{{ __('admin.safety.note') }}</h2></div>
                    <p class="hm-body">{{ $a->note }}</p>
                </section>
            @endif
        </div>

        <div class="hm-stack">
            <section class="hm-card">
                <div class="hm-phead">
                    <h2>{{ __('admin.safety.emergency_contacts') }}</h2>
                    <span class="hm-sub">{{ __('admin.safety.already_texted') }}</span>
                </div>
                <div class="hm-tl">
                    @forelse ($contacts as $c)
                        <div class="hm-mile">
                            <span class="hm-pa" style="background:var(--hm-danger)">{{ $initials($c->name) }}</span>
                            <div class="hm-mt">
                                <b>{{ $c->name }}</b>
                                <div class="hm-sub hm-num">{{ $c->phone_e164 }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="hm-empty">{{ __('admin.safety.no_contacts') }}</div>
                    @endforelse
                </div>
            </section>

            <section class="hm-card">
                <div class="hm-phead"><h2>{{ __('admin.details') }}</h2></div>
                <div class="hm-kv">
                    <div class="r"><span class="l">{{ __('admin.safety.raised_at') }}</span><span class="val">{{ $a->created_at->format('d M Y, H:i') }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.recon.resolved_at') }}</span><span class="val">{{ $a->resolved_at?->format('d M Y, H:i') ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.dispute.decided_by') }}</span><span class="val">{{ $a->resolvedBy?->name ?? '—' }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.safety.phone') }}</span><span class="val hm-num">{{ $a->user?->phone_e164 ?? '—' }}</span></div>
                </div>
            </section>
        </div>
    </div>
</div>
</div>
