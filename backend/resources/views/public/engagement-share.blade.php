@extends('layouts.public')

@section('title', __('share.heading'))

@section('content')
    {{-- Someone opened this because a person they care about let a stranger into a house. It is
         three facts and a plain statement of when the link dies — no navigation, no marketing.
         `.sections` and the page-level `.hero` never existed in the stylesheet, so this page had
         been rendering as bare stacked blocks; it is laid out properly now. --}}
    <div class="w-full max-w-2xl mx-auto px-6 py-16">
        <section>
            <h1 class="text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ __('share.heading') }}</h1>
            <p class="mt-2 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)] text-content-muted">{{ __('share.reassurance') }}</p>
        </section>

        <section class="grid gap-4 mt-8" id="start">
            <div class="bg-surface-raised p-6">
                <h2 class="text-xs font-bold uppercase tracking-[0.1em] text-content-muted">{{ __('share.provider') }}</h2>
                <p class="mt-1 m-0 text-xl font-semibold text-content">{{ $firstName }}</p>
            </div>

            <div class="bg-surface-raised p-6">
                <h2 class="text-xs font-bold uppercase tracking-[0.1em] text-content-muted">{{ __('share.status') }}</h2>
                {{-- On site is the one status worth colouring: it is the answer to "are they there
                     yet", which is why this page was opened. --}}
                <p @class([
                    'mt-1 m-0 text-xl font-semibold',
                    'text-success' => $status === 'on_site',
                    'text-content' => $status !== 'on_site',
                ])>
                    {{ __('share.status_'.$status) }}
                    @if ($status === 'on_site' && $startedAt)
                        <span class="text-[0.95rem] font-normal text-content-muted">· {{ $startedAt->isoFormat('LT') }}</span>
                    @endif
                </p>
            </div>

            @if ($address)
                <div class="bg-surface-raised p-6">
                    <h2 class="text-xs font-bold uppercase tracking-[0.1em] text-content-muted">{{ __('share.location') }}</h2>
                    {{-- Quarter and city only. A share link never carries the street address. --}}
                    <p class="mt-1 m-0 text-content">{{ collect([$address->quarter, $address->city])->filter()->implode(', ') }}</p>
                </div>
            @endif
        </section>

        <p class="mt-10 text-sm text-content-muted">{{ __('share.expires', ['time' => $expiresAt->diffForHumans()]) }}</p>
    </div>
@endsection
