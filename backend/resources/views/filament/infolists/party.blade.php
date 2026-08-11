@php
    /** @var \App\Models\Party $p */
    $p = $getRecord();
    $p->loadMissing(['user', 'organization', 'providerProfile']);

    $profile = $p->providerProfile;
    $initials = function (?string $n): string {
        $parts = preg_split('/\s+/', trim((string) $n)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    };

    // Both sides of the marketplace, counted from the rows themselves. A party is never "a customer"
    // or "a provider" in this product (doc 10) — everyone has both sections — so show both.
    $asCustomer = \App\Models\Job::query()->where('customer_party_id', $p->id)->count();
    $asProvider = \App\Models\Engagement::query()->where('provider_party_id', $p->id)->count();
    $completed = \App\Models\Engagement::query()->where('provider_party_id', $p->id)->whereNotNull('completed_at')->count();

    $openReports = \App\Models\Report::query()
        ->where('subject_party_id', $p->id)
        ->whereIn('status', ['open', 'reviewing'])
        ->count();

    // Consents are per USER, and the latest row per purpose is the current state (the log is
    // append-only — a revocation is a new row, not an edit).
    $consents = $p->user
        ? \App\Models\Consent::query()
            ->where('user_id', $p->user->id)
            ->orderByDesc('created_at')
            ->get()
            ->unique('purpose')
        : collect();
@endphp

<div class="hm-dash">
@include('filament.partials.hm-theme')

<div class="hm-grid">
    <section class="hm-card">
        <div class="hm-head">
            <span class="hm-pa hm-pa-lg">{{ $initials($p->display_name) }}</span>
            <div style="flex:1">
                <div class="hm-title">
                    {{ $p->display_name }}
                    <small>{{ __('admin.party.kind.'.$p->kind) }}</small>
                </div>
                <div class="hm-sub">{{ $p->user?->phone_e164 ?? '—' }} &nbsp;·&nbsp; {{ __('admin.party.joined') }} {{ $p->created_at?->format('d M Y') }}</div>
            </div>
            @if ($p->erased_at)
                <span class="hm-pill hm-neutral">{{ __('admin.party.erased') }}</span>
            @endif
            <span class="hm-pill {{ $p->status === 'active' ? 'hm-completed' : ($p->status === 'suspended' ? 'hm-danger' : 'hm-progress') }}">
                {{ __('admin.party.status.'.$p->status) }}
            </span>
        </div>
    </section>

    @if ($p->erased_at)
        {{-- The row survives so ledger FKs stay intact; nothing below it is personal data any more,
             and the key that could decrypt it is gone. Say that rather than showing blank fields. --}}
        <div class="hm-card" style="padding:14px 16px">
            <p class="hm-sub" style="margin:0">{{ __('admin.party.erased_explainer') }}</p>
        </div>
    @endif

    <div class="hm-mgrid">
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.party.jobs_posted') }}</div>
            <div class="hm-mv">{{ $asCustomer }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.party.work_taken') }}</div>
            <div class="hm-mv">{{ $asProvider }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.party.completed') }}</div>
            <div class="hm-mv" style="color:var(--hm-brand)">{{ $completed }}</div>
        </div>
        <div class="hm-card hm-metric">
            <div class="hm-mk">{{ __('admin.party.open_reports') }}</div>
            <div class="hm-mv" @if ($openReports > 0) style="color:var(--hm-warning)" @endif>{{ $openReports }}</div>
        </div>
    </div>

    <div class="hm-two">
        <section class="hm-card">
            <div class="hm-phead"><h2>{{ __('admin.party.provider_side') }}</h2></div>
            @if ($profile)
                <div class="hm-kv">
                    <div class="r"><span class="l">{{ __('admin.party.tier') }}</span><span class="val">{{ __('admin.party.tier_n', ['n' => $profile->verification_tier]) }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.party.rating') }}</span><span class="val">{{ $profile->rating_avg !== null ? number_format((float) $profile->rating_avg, 2) : __('admin.party.unrated') }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.party.reviews') }}</span><span class="val">{{ $profile->rating_count }}</span></div>
                    <div class="r"><span class="l">{{ __('admin.party.headline') }}</span><span class="val">{{ $profile->headline ?? '—' }}</span></div>
                </div>
            @else
                <p class="hm-sub" style="margin:0">{{ __('admin.party.no_provider_profile') }}</p>
            @endif
        </section>

        <section class="hm-card">
            <div class="hm-phead">
                <h2>{{ __('admin.party.consents') }}</h2>
                <span class="hm-sub">{{ __('admin.party.latest_per_purpose') }}</span>
            </div>
            <div class="hm-kv">
                @forelse ($consents as $c)
                    <div class="r">
                        <span class="l">{{ $c->purpose }}</span>
                        <span class="val" style="color:var({{ $c->granted ? '--hm-success' : '--hm-danger' }})">
                            {{ $c->granted ? __('admin.party.granted') : __('admin.party.revoked') }}
                        </span>
                    </div>
                @empty
                    <p class="hm-sub" style="margin:0">{{ __('admin.party.no_consents') }}</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
</div>
