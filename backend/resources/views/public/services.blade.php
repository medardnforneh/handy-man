@extends('layouts.public')

@section('title', __('public.services_title').' · '.__('app.name'))
@section('description', __('public.services_description'))

@push('structured-data')
    {{-- Tells a crawler this page IS the directory, and which pages sit under it. --}}
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    <h1>{{ __('public.services_title') }}</h1>
    <p class="lede">{{ __('public.services_description') }}</p>

    @forelse ($categories as $category)
        <section class="cat">
            <h2><a href="{{ route('services.show', ['slug' => $category->slug]) }}">{{ $category->name($locale) }}</a></h2>
            <ul class="links">
                @foreach ($category->children as $leaf)
                    <li><a href="{{ route('services.show', ['slug' => $leaf->slug]) }}">{{ $leaf->name($locale) }}</a></li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="empty">{{ __('public.no_services') }}</p>
    @endforelse
@endsection
