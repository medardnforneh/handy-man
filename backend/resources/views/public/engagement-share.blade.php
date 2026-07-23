@extends('layouts.public')

@section('title', __('share.heading'))

@section('content')
    <section class="hero">
        <h1>{{ __('share.heading') }}</h1>
        <p>{{ __('share.reassurance') }}</p>
    </section>

    <section class="sections" id="start">
        <div class="card">
            <h2>{{ __('share.provider') }}</h2>
            <p style="font-size:1.25rem;font-weight:600;color:var(--hm-color-text-primary);margin:0;">
                {{ $firstName }}
            </p>
        </div>

        <div class="card">
            <h2>{{ __('share.status') }}</h2>
            <p style="font-size:1.25rem;font-weight:600;margin:0;color:{{ $status === 'on_site' ? 'var(--hm-color-status-success)' : 'var(--hm-color-text-primary)' }};">
                {{ __('share.status_'.$status) }}
                @if ($status === 'on_site' && $startedAt)
                    <span style="color:var(--hm-color-text-muted);font-weight:400;font-size:0.95rem;">
                        · {{ $startedAt->isoFormat('LT') }}
                    </span>
                @endif
            </p>
        </div>

        @if ($address)
            <div class="card">
                <h2>{{ __('share.location') }}</h2>
                <p style="margin:0;color:var(--hm-color-text-primary);">
                    {{ collect([$address->quarter, $address->city])->filter()->implode(', ') }}
                </p>
            </div>
        @endif
    </section>

    <p style="margin-top:var(--hm-space-xl);color:var(--hm-color-text-muted);font-size:0.9rem;">
        {{ __('share.expires', ['time' => $expiresAt->diffForHumans()]) }}
    </p>
@endsection
