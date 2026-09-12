@extends('layouts.public')

@section('title', __('request.posted_title').' · '.__('app.name'))
@section('description', __('request.posted_lede', ['reference' => $reference]))

@section('content')
    @php
        // A reference is ONE token, and a hyphen is a licence to break that has to be revoked
        // explicitly — "JOB-" above "KKXXX" reads as a broken page, not a code. The span is the
        // only markup interpolated into copy here, and the reference inside it is escaped.
        $ref = '<span class="whitespace-nowrap font-semibold">'.e($reference).'</span>';
    @endphp
    {{-- The poster statement: the one place the accent runs as a field, and a request that has
         just been posted is the moment that earns it. --}}
    <section class="bg-brand text-brand-contrast">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="py-20 max-w-[44rem]">
                <span class="inline-block text-xs font-bold uppercase tracking-[0.12em]">{{ __('request.posted_eyebrow') }}</span>
                <h1 class="mt-2 text-[clamp(1.9rem,1.2rem+2.6vw,3.4rem)] font-extrabold leading-[1.1] tracking-[-0.025em]">{{ __('request.posted_title') }}</h1>
                <p class="mt-3 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)]">{!! __('request.posted_lede', ['reference' => $ref]) !!}</p>
                <p class="mt-6 inline-block bg-brand-contrast px-4 py-2 text-[1.4rem] font-extrabold tracking-[-0.01em] text-brand tabular-nums">{{ $reference }}</p>
            </div>
        </div>
    </section>

    <section class="py-10">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="grid gap-4 max-w-[44rem]">
                <h2 class="text-[clamp(1.05rem,0.95rem+0.4vw,1.2rem)] font-extrabold leading-tight tracking-[-0.025em]">{{ __('request.posted_next_title') }}</h2>
                <ol class="m-0 grid gap-3 list-none p-0">
                    @foreach (['request.posted_next_1', 'request.posted_next_2', 'request.posted_next_3'] as $i => $key)
                        <li class="flex items-start gap-4 border-s-2 border-edge-strong ps-4">
                            <span class="text-xs font-bold uppercase tracking-[0.1em] text-brand-strong mt-1">{{ $i + 1 }}</span>
                            <span>{{ __($key) }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="m-0 text-sm text-content-muted">{!! __('request.posted_keep', ['reference' => $ref]) !!}</p>
                <div class="flex flex-wrap gap-2 mt-2">
                    <a class="inline-flex items-center justify-center gap-2 min-h-11 border border-edge-strong bg-transparent px-[1.15rem] py-3 text-[0.95rem] font-extrabold text-content no-underline transition-colors hover:bg-content/7"
                       href="{{ route('services.index') }}">{{ __('request.posted_back') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
