@extends('layouts.public')

@section('title', __('request.verify_title').' · '.__('app.name'))
@section('description', __('request.verify_lede', ['phone' => $phone]))

@section('content')
    <section class="pt-12 pb-20 lg:pt-16">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <div class="max-w-[36rem]">
                <span class="eyebrow">{{ __('request.eyebrow') }}</span>
                <h1 class="title mt-4">{{ __('request.verify_title') }}</h1>
                <p class="lede mt-4">{{ __('request.verify_lede', ['phone' => $phone]) }}</p>

                <form class="card mt-8 grid gap-6 rounded-[24px] p-6 sm:p-8" method="post" action="{{ route('services.request.confirm', ['slug' => $skill->slug]) }}" novalidate>
                    @csrf
                    <div>
                        <label class="micro mb-2" for="code">{{ __('request.code_label') }}</label>
                        <input class="block w-full max-w-[15rem] min-h-14 rounded-md border border-edge bg-surface-sunken px-4 py-2.5 text-[1.5rem] font-extrabold tracking-[0.3em] text-content focus:border-brand focus:outline-none [font:inherit]"
                               id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="9" required autofocus>
                        @error('code') <p class="mt-2 text-[0.85rem] text-danger">{{ $message }}</p> @enderror
                        @if ($devCode !== null)
                            {{-- Local development only (see the controller): there is no SMS gateway here. --}}
                            <p class="mt-2 text-[0.85rem] text-content-muted">{{ __('request.dev_code_hint', ['code' => $devCode]) }}</p>
                        @endif
                    </div>
                    <div>
                        <button class="btn btn-primary w-full min-h-[3.5rem] [font:inherit]" type="submit">
                            {{ __('request.verify_submit') }}
                        </button>
                        <p class="mt-3 text-[0.85rem] text-content-muted">
                            {{ __('request.verify_wrong_number') }}
                            <a href="{{ route('services.request', ['slug' => $skill->slug]) }}">{{ __('request.verify_start_over') }}</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
