{{-- Inline stroke icons, drawn with currentColor so they inherit whichever token the parent sets.
     Inline rather than an icon font or sprite sheet: this page must render on a throttled 3G
     connection without a second request, and an icon that arrives late is an icon that reflows. --}}
@php $name = $name ?? 'tool'; @endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('shield')
            <path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>
            @break
        @case('badge')
            <circle cx="12" cy="9" r="5"/><path d="M9 13.5L8 21l4-2 4 2-1-7.5"/>
            @break
        @case('refresh')
            <path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>
            @break
        @case('phone')
            <rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/>
            @break
        @case('check')
            <path d="M5 12.5l4.5 4.5L19 7.5"/>
            @break
        @case('alert')
            <path d="M12 4l9 16H3z"/><path d="M12 10v4"/><path d="M12 17.5h.01"/>
            @break
        @case('pin')
            <path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>
            @break
        @case('share')
            <circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/>
            <path d="M8.2 10.8l7.6-4.1M8.2 13.2l7.6 4.1"/>
            @break
        @case('scale')
            <path d="M12 4v16"/><path d="M6 20h12"/><path d="M4 9l4-3 4 3"/><path d="M12 9l4-3 4 3"/>
            <path d="M4 9a4 4 0 0 0 8 0"/><path d="M12 9a4 4 0 0 0 8 0"/>
            @break

        {{-- Trade categories. Thirteen identical wrenches told a visitor nothing about which card
             was which; a distinct silhouette per category is what makes a grid scannable. --}}
        @case('auto-mechanics')
            <path d="M5 17h14"/><path d="M6.5 17l1.2-4.5A2 2 0 0 1 9.6 11h4.8a2 2 0 0 1 1.9 1.5L17.5 17"/>
            <circle cx="7.5" cy="19" r="1.6"/><circle cx="16.5" cy="19" r="1.6"/>
            @break
        @case('carpentry')
            <path d="M4 7h9l7 7-3 3-9-9H4z"/><path d="M4 7v6"/>
            @break
        @case('cleaning')
            <path d="M8 3h3v7H8z"/><path d="M6.5 10h6l1 4.5a3.5 3.5 0 0 1-3.4 4.3H8.9a3.5 3.5 0 0 1-3.4-4.3z"/>
            <path d="M15 6l4-2M16 10l4 0"/>
            @break
        @case('electrical')
            <path d="M13 3l-7 10h5l-1 8 7-10h-5z"/>
            @break
        @case('gardening')
            <path d="M12 21V9"/><path d="M12 12c-4 0-6-2-6-6 4 0 6 2 6 6z"/><path d="M12 15c4 0 6-2 6-6-4 0-6 2-6 6z"/>
            @break
        @case('hvac-and-refrigeration')
            <path d="M12 3v18M3 12h18"/><path d="M8 6l4 3 4-3M8 18l4-3 4 3M6 8l3 4-3 4M18 8l-3 4 3 4"/>
            @break
        @case('hair-and-beauty')
            <circle cx="7" cy="17" r="2.5"/><circle cx="17" cy="17" r="2.5"/><path d="M8.6 15.2L18 4M15.4 15.2L6 4"/>
            @break
        @case('it-and-networks')
            <rect x="3" y="5" width="18" height="11" rx="2"/><path d="M8 20h8"/><path d="M12 16v4"/>
            @break
        @case('masonry')
            <rect x="3" y="6" width="18" height="5"/><rect x="3" y="13" width="18" height="5"/><path d="M9 6v5M15 13v5"/>
            @break
        @case('painting')
            <rect x="4" y="3" width="12" height="6" rx="1.5"/><path d="M16 6h3a1.5 1.5 0 0 1 1.5 1.5V11a1.5 1.5 0 0 1-1.5 1.5h-6"/>
            <path d="M11 12.5h2v3h-2z"/><path d="M12 15.5V21"/>
            @break
        @case('plumbing')
            <path d="M6 3v6a4 4 0 0 0 4 4h4"/><path d="M14 9h6"/><path d="M17 6v6"/><path d="M10 17v4M8 21h4"/>
            @break
        @case('private-tutoring')
            <path d="M3 8l9-4 9 4-9 4z"/><path d="M7 10.5V16c0 1.5 2.2 3 5 3s5-1.5 5-3v-5.5"/>
            @break
        @case('tailoring')
            <path d="M8 3l8 14M16 3L8 17"/><circle cx="6.5" cy="19" r="2"/><circle cx="17.5" cy="19" r="2"/>
            @break
        @default
            <path d="M14.7 6.3a4 4 0 0 1-5 5L4 17v3h3l5.7-5.7a4 4 0 0 1 5-5l2.6-2.6-2.6-2.6z"/>
    @endswitch
</svg>
