@extends('layouts.public')

@section('title', __('request.posted_title').' · '.__('app.name'))
@section('description', __('request.posted_lede', ['reference' => $reference]))

@section('content')
    @php
        // A reference is ONE token, and a hyphen is a licence to break that has to be revoked
        // explicitly — "JOB-" above "KKXXX" reads as a broken page, not a code. The span is the
        // only markup interpolated into copy here, and the reference inside it is escaped.
        $ref = '<span class="whitespace-nowrap font-bold">'.e($reference).'</span>';
    @endphp
    {{-- The accent as a field: a request that has just been posted is the moment that earns it. --}}
    <section class="pt-12 lg:pt-16">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="rounded-[32px] bg-brand px-8 py-14 text-brand-contrast lg:px-16 lg:py-16">
                <div class="max-w-[50ch]">
                    <span class="inline-block text-xs font-extrabold uppercase tracking-[0.12em] text-brand-contrast/80">{{ __('request.posted_eyebrow') }}</span>
                    <h1 class="mt-4 text-[clamp(2rem,1.4rem+2.4vw,2.875rem)] font-extrabold leading-[1.05] tracking-[-0.038em] text-brand-contrast">{{ __('request.posted_title') }}</h1>
                    <p class="mt-4 text-[1.0625rem] leading-relaxed text-brand-contrast/80">{!! __('request.posted_lede', ['reference' => $ref]) !!}</p>
                    <p class="mt-6 inline-block rounded-md bg-surface px-5 py-3 text-[1.4rem] font-extrabold tracking-[-0.01em] text-brand">{{ $reference }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-14">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="grid max-w-[44rem] gap-5">
                <h2 class="text-[1.1875rem] font-bold leading-tight tracking-[-0.02em]">{{ __('request.posted_next_title') }}</h2>
                <ol class="card m-0 grid list-none divide-y divide-edge p-0">
                    @foreach (['request.posted_next_1', 'request.posted_next_2', 'request.posted_next_3'] as $i => $key)
                        <li class="flex items-start gap-4 px-5 py-4">
                            <span class="grid size-7 shrink-0 place-items-center rounded-pill bg-brand-tint text-[0.75rem] font-extrabold text-brand">{{ $i + 1 }}</span>
                            <span class="pt-0.5 text-[0.95rem]">{{ __($key) }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="m-0 text-[0.9rem] text-content-muted">{!! __('request.posted_keep', ['reference' => $ref]) !!}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <a class="btn btn-secondary" href="{{ route('services.index') }}">{{ __('request.posted_back') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
