@extends('layouts.public')

@section('title', __('share.heading'))

@section('content')
    {{-- Someone opened this because a person they care about let a stranger into a house. It is
         three facts and a plain statement of when the link dies — no navigation, no marketing. --}}
    <div class="mx-auto w-full max-w-2xl px-5 py-16 sm:px-8">
        <section>
            <h1 class="title">{{ __('share.heading') }}</h1>
            <p class="lede mt-4">{{ __('share.reassurance') }}</p>
        </section>

        <section class="card mt-8 grid divide-y divide-edge" id="start">
            <div class="px-6 py-5">
                <h2 class="micro">{{ __('share.provider') }}</h2>
                <p class="m-0 mt-1.5 text-xl font-bold text-content">{{ $firstName }}</p>
            </div>

            <div class="px-6 py-5">
                <h2 class="micro">{{ __('share.status') }}</h2>
                {{-- On site is the one status worth colouring: it is the answer to "are they there
                     yet", which is why this page was opened. --}}
                <p @class([
                    'm-0 mt-1.5 text-xl font-bold',
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
                <div class="px-6 py-5">
                    <h2 class="micro">{{ __('share.location') }}</h2>
                    {{-- Quarter and city only. A share link never carries the street address. --}}
                    <p class="m-0 mt-1.5 text-content">{{ collect([$address->quarter, $address->city])->filter()->implode(', ') }}</p>
                </div>
            @endif
        </section>

        <p class="mt-10 text-[0.9rem] text-content-muted">{{ __('share.expires', ['time' => $expiresAt->diffForHumans()]) }}</p>
    </div>
@endsection
