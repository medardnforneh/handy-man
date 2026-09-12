@extends('layouts.public')

@section('title', __('request.verify_title').' · '.__('app.name'))
@section('description', __('request.verify_lede', ['phone' => $phone]))

@section('content')
    <section class="py-10">
        <div class="w-full max-w-6xl mx-auto px-6">
            <div class="max-w-[36rem]">
                <span class="inline-block text-xs font-bold uppercase tracking-[0.12em] text-brand-strong">{{ __('request.eyebrow') }}</span>
                <h1 class="mt-2 text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ __('request.verify_title') }}</h1>
                <p class="mt-2 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)] text-content-muted">{{ __('request.verify_lede', ['phone' => $phone]) }}</p>

                <form class="grid gap-6 mt-8" method="post" action="{{ route('services.request.confirm', ['slug' => $skill->slug]) }}" novalidate>
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-content-muted mb-1.5" for="code">{{ __('request.code_label') }}</label>
                        <input class="block w-full max-w-[14rem] min-h-12 bg-surface-raised border border-edge-strong px-3 py-2.5 text-[1.4rem] font-extrabold tracking-[0.3em] tabular-nums text-content focus:border-brand focus:outline-none [font:inherit]"
                               id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="9" required autofocus>
                        @error('code') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                        @if ($devCode !== null)
                            {{-- Local development only (see the controller): there is no SMS gateway here. --}}
                            <p class="mt-1.5 text-sm text-content-muted">{{ __('request.dev_code_hint', ['code' => $devCode]) }}</p>
                        @endif
                    </div>
                    <div>
                        <button class="inline-flex w-full items-center justify-start gap-2 min-h-12 bg-brand px-[1.15rem] py-3 text-[0.95rem] font-extrabold text-brand-contrast border-0 cursor-pointer transition-colors hover:bg-brand-strong [font:inherit]" type="submit">
                            {{ __('request.verify_submit') }}
                        </button>
                        <p class="mt-3 text-sm text-content-muted">
                            {{ __('request.verify_wrong_number') }}
                            <a class="text-brand-strong" href="{{ route('services.request', ['slug' => $skill->slug]) }}">{{ __('request.verify_start_over') }}</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
