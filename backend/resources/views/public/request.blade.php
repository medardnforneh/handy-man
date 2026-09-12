@extends('layouts.public')

@section('title', __('request.title', ['service' => $skill->name($locale)]).' · '.__('app.name'))
@section('description', __('request.lede'))

@section('content')
    @php
        $input = 'block w-full min-h-11 bg-surface-raised border border-edge-strong px-3 py-2.5 text-md text-content placeholder:text-content-muted focus:border-brand focus:outline-none [font:inherit]';
        $label = 'block text-xs font-semibold text-content-muted mb-1.5';
        $error = 'mt-1.5 text-sm text-danger';
        $mode = old('engagement_mode', 'onsite');
    @endphp

    <section class="border-b-2 border-edge-strong py-10">
        <div class="w-full max-w-6xl mx-auto px-6">
            <nav class="flex flex-wrap items-center gap-1.5 text-sm text-content-muted" aria-label="{{ __('public.nav_label') }}">
                <a class="text-content-muted no-underline hover:text-content hover:underline" href="{{ route('services.index') }}">{{ __('public.services_title') }}</a>
                <span aria-hidden="true">&rsaquo;</span>
                <a class="text-content-muted no-underline hover:text-content hover:underline" href="{{ route('services.show', ['slug' => $skill->slug]) }}">{{ $skill->name($locale) }}</a>
            </nav>
            <div class="max-w-[44rem] mt-4">
                <span class="inline-block text-xs font-bold uppercase tracking-[0.12em] text-brand-strong">{{ __('request.eyebrow') }}</span>
                <h1 class="mt-2 text-[clamp(1.55rem,1.15rem+1.7vw,2.4rem)] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ __('request.title', ['service' => $skill->name($locale)]) }}</h1>
                <p class="mt-2 text-[clamp(1.02rem,0.96rem+0.35vw,1.2rem)] text-content-muted">{{ __('request.lede') }}</p>
            </div>
        </div>
    </section>

    <section class="py-10">
        <div class="w-full max-w-6xl mx-auto px-6">
            {{-- Plain HTML, no script: this page has to work on a feature phone's browser over a
                 throttled connection, which is the whole reason it exists beside the app. --}}
            <form class="grid gap-6 max-w-[36rem]" method="post" action="{{ route('services.request.store', ['slug' => $skill->slug]) }}" novalidate>
                @csrf

                <div>
                    <label class="{{ $label }}" for="phone">{{ __('request.phone_label') }}</label>
                    <input class="{{ $input }}" id="phone" name="phone_e164" type="tel" inputmode="tel" autocomplete="tel" required
                           value="{{ old('phone_e164') }}" placeholder="{{ __('request.phone_placeholder') }}">
                    <p class="mt-1.5 text-sm text-content-muted">{{ __('request.phone_help') }}</p>
                    @error('phone_e164') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <fieldset class="m-0 p-0 border-0">
                    <legend class="{{ $label }}">{{ __('request.mode_label') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (['onsite' => 'request.mode_onsite', 'remote' => 'request.mode_remote'] as $value => $key)
                            <label class="flex items-start gap-3 border border-edge-strong bg-surface-raised p-4 cursor-pointer has-checked:border-brand has-checked:bg-brand-tint">
                                <input class="sr-only peer" type="radio" name="engagement_mode" value="{{ $value }}" @checked($mode === $value)>
                                <span class="mt-0.5 grid size-4 flex-none place-items-center rounded-full border-[1.5px] border-edge-strong peer-checked:border-brand peer-checked:bg-brand peer-checked:shadow-[inset_0_0_0_3px_var(--hm-color-surface-raised)]" aria-hidden="true"></span>
                                <span>
                                    <span class="block font-extrabold">{{ __($key) }}</span>
                                    <span class="block text-sm text-content-muted">{{ __($key.'_help') }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('engagement_mode') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </fieldset>

                <div>
                    <label class="{{ $label }}" for="title">{{ __('request.title_label') }}</label>
                    <input class="{{ $input }}" id="title" name="title" type="text" required maxlength="120"
                           value="{{ old('title') }}" placeholder="{{ __('request.title_placeholder') }}">
                    @error('title') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $label }}" for="description">{{ __('request.description_label') }}</label>
                    <textarea class="{{ $input }} min-h-28 resize-y" id="description" name="description" maxlength="2000"
                              placeholder="{{ __('request.description_placeholder') }}">{{ old('description') }}</textarea>
                    @error('description') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                {{-- The place. Shown for both modes (the form has no script to hide it) and
                     required only on-site — the rule the server enforces. --}}
                <div class="grid gap-4 border-t-2 border-edge-strong pt-6">
                    <p class="m-0 text-sm text-content-muted">{{ __('request.place_note') }}</p>
                    <div>
                        <label class="{{ $label }}" for="city">{{ __('request.city_label') }}</label>
                        <select class="{{ $input }}" id="city" name="city">
                            <option value="">{{ __('request.city_choose') }}</option>
                            @foreach ($cities as $value => $name)
                                <option value="{{ $value }}" @selected(old('city') === $value)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('city') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="{{ $label }}" for="line1">{{ __('request.line1_label') }}</label>
                            <input class="{{ $input }}" id="line1" name="line1" type="text" maxlength="200" autocomplete="street-address"
                                   value="{{ old('line1') }}" placeholder="{{ __('request.line1_placeholder') }}">
                            @error('line1') <p class="{{ $error }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="quarter">{{ __('request.quarter_label') }}</label>
                            <input class="{{ $input }}" id="quarter" name="quarter" type="text" maxlength="120"
                                   value="{{ old('quarter') }}" placeholder="{{ __('request.quarter_placeholder') }}">
                            @error('quarter') <p class="{{ $error }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <label class="flex items-start gap-3 text-sm cursor-pointer">
                        <input class="mt-1 size-4 flex-none accent-brand" type="checkbox" name="consent_location" value="1" @checked(old('consent_location'))>
                        <span>{{ __('request.consent_location') }}</span>
                    </label>
                    @error('consent_location') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 border-t-2 border-edge-strong pt-6">
                    <label class="flex items-start gap-3 text-sm cursor-pointer">
                        <input class="mt-1 size-4 flex-none accent-brand" type="checkbox" name="consent_terms" value="1" required @checked(old('consent_terms'))>
                        <span>{{ __('request.consent_terms') }}</span>
                    </label>
                    @error('consent_terms') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <button class="inline-flex w-full items-center justify-start gap-2 min-h-12 bg-brand px-[1.15rem] py-3 text-[0.95rem] font-extrabold text-brand-contrast border-0 cursor-pointer transition-colors hover:bg-brand-strong [font:inherit]" type="submit">
                        {{ __('request.submit') }}
                    </button>
                    <p class="mt-3 text-sm text-content-muted">{{ __('request.submit_note') }}</p>
                </div>
            </form>
        </div>
    </section>
@endsection
