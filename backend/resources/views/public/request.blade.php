@extends('layouts.public')

@section('title', __('request.title', ['service' => $skill->name($locale)]).' · '.__('app.name'))
@section('description', __('request.lede'))

@section('content')
    @php
        // The design's filled field: surface-1, 18px radius, a 1px line that turns accent on focus.
        $input = 'block w-full min-h-12 rounded-md border border-edge bg-surface-raised px-[17px] py-3 text-[0.95rem] text-content placeholder:text-content-muted focus:border-brand focus:outline-none [font:inherit]';
        $label = 'micro mb-2';
        $error = 'mt-2 text-[0.85rem] text-danger';
        $mode = old('engagement_mode', 'onsite');
    @endphp

    <section class="pt-12 pb-8 lg:pt-16">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            <nav class="flex flex-wrap items-center gap-2 text-sm text-content-muted" aria-label="{{ __('public.nav_label') }}">
                <a class="text-content-muted no-underline hover:text-content" href="{{ route('services.index') }}">{{ __('public.services_title') }}</a>
                <span class="text-content-faint" aria-hidden="true">&rsaquo;</span>
                <a class="text-content-muted no-underline hover:text-content" href="{{ route('services.show', ['slug' => $skill->slug]) }}">{{ $skill->name($locale) }}</a>
            </nav>
            <div class="mt-6 max-w-[44rem]">
                <span class="eyebrow">{{ __('request.eyebrow') }}</span>
                <h1 class="title mt-4">{{ __('request.title', ['service' => $skill->name($locale)]) }}</h1>
                <p class="lede mt-4">{{ __('request.lede') }}</p>
            </div>
        </div>
    </section>

    <section class="pb-20">
        <div class="mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-16">
            {{-- Plain HTML, no script: this page has to work on a feature phone's browser over a
                 throttled connection, which is the whole reason it exists beside the app. --}}
            <form class="card grid max-w-[40rem] gap-6 rounded-[24px] p-6 sm:p-8" method="post" action="{{ route('services.request.store', ['slug' => $skill->slug]) }}" novalidate>
                @csrf

                <div>
                    <label class="{{ $label }}" for="phone">{{ __('request.phone_label') }}</label>
                    <input class="{{ $input }}" id="phone" name="phone_e164" type="tel" inputmode="tel" autocomplete="tel" required
                           value="{{ old('phone_e164') }}" placeholder="{{ __('request.phone_placeholder') }}">
                    <p class="mt-2 text-[0.85rem] text-content-muted">{{ __('request.phone_help') }}</p>
                    @error('phone_e164') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <fieldset class="m-0 border-0 p-0">
                    <legend class="{{ $label }}">{{ __('request.mode_label') }}</legend>
                    {{-- The design's address rows: selected is accent-tinted inside an accent line. --}}
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (['onsite' => 'request.mode_onsite', 'remote' => 'request.mode_remote'] as $value => $key)
                            <label class="flex cursor-pointer items-start gap-3 rounded-md border border-edge bg-surface-raised p-4 has-checked:border-brand has-checked:bg-brand-tint">
                                <input class="peer sr-only" type="radio" name="engagement_mode" value="{{ $value }}" @checked($mode === $value)>
                                <span class="mt-0.5 grid size-[19px] flex-none place-items-center rounded-full border-[1.5px] border-edge-strong peer-checked:border-brand peer-checked:bg-brand peer-checked:shadow-[inset_0_0_0_4px_var(--hm-color-surface-raised)]" aria-hidden="true"></span>
                                <span>
                                    <span class="block text-[0.95rem] font-bold">{{ __($key) }}</span>
                                    <span class="block text-[0.8125rem] text-content-muted">{{ __($key.'_help') }}</span>
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
                <div class="grid gap-4 border-t border-edge pt-6">
                    <p class="m-0 text-[0.85rem] text-content-muted">{{ __('request.place_note') }}</p>
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
                    <label class="flex cursor-pointer items-start gap-3 text-[0.9rem]">
                        <input class="mt-1 size-4 flex-none accent-brand" type="checkbox" name="consent_location" value="1" @checked(old('consent_location'))>
                        <span>{{ __('request.consent_location') }}</span>
                    </label>
                    @error('consent_location') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 border-t border-edge pt-6">
                    <label class="flex cursor-pointer items-start gap-3 text-[0.9rem]">
                        <input class="mt-1 size-4 flex-none accent-brand" type="checkbox" name="consent_terms" value="1" required @checked(old('consent_terms'))>
                        <span>{{ __('request.consent_terms') }}</span>
                    </label>
                    @error('consent_terms') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <button class="btn btn-primary w-full min-h-[3.5rem] [font:inherit]" type="submit">
                        {{ __('request.submit') }}
                        <span class="[&_svg]:size-4" aria-hidden="true">@include('public.partials.icon', ['name' => 'arrow-right'])</span>
                    </button>
                    <p class="mt-3 text-[0.85rem] text-content-muted">{{ __('request.submit_note') }}</p>
                </div>
            </form>
        </div>
    </section>
@endsection
