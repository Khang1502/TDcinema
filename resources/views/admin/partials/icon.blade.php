@php
$size = $size ?? 18;
$classes = $classes ?? 'text-slate-300';
@endphp

@switch($name)
    @case('dashboard')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 13h8V3H3v10Zm10 8h8V3h-8v18Zm-10 0h8v-6H3v6Z"/>
        </svg>
        @break
    @case('film')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 4h16v16H4V4Zm0 4h16m-9-4v16m-3-3h2m-2-6h2m-2-6h2m4 12h2m-2-6h2m-2-6h2"/>
        </svg>
        @break
    @case('ticket')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 7h16v4a2 2 0 1 1 0 4v4H4v-4a2 2 0 1 1 0-4V7Zm10 1v8M8 8v1m0 3v1m0 3v1"/>
        </svg>
        @break
    @case('users')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1m22 0v-1a4 4 0 0 0-3-3.87M17 4a4 4 0 1 1-2 7.465"/>
        </svg>
        @break
    @case('gift')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M20 12v8H4v-8m16-4H4v4h16V8Zm-8 0v16V8Zm0 0H8.5a2.5 2.5 0 1 1 0-5C12 3 12 8 12 8Zm0 0h3.5a2.5 2.5 0 1 0 0-5C12 3 12 8 12 8Z"/>
        </svg>
        @break
    @case('building')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 21V5a2 2 0 0 1 2-2h6l6 6v12H4Zm8-14v4h4M8 9h2m-2 3h2m-2 3h2m4-3h2m-2 3h2"/>
        </svg>
        @break
    @case('bag')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M6 7h12l-1 12H7L6 7Zm2 0a4 4 0 1 1 8 0M9 11v2m6-2v2"/>
        </svg>
        @break
    @case('calendar')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M7 3v2m10-2v2M5 8h14M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm2 6h2v2H8v-2Z"/>
        </svg>
        @break
    @case('settings')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M10.325 4.317a1 1 0 0 1 1.35-.937l.334.112a9 9 0 0 1 4.841 4.84l.111.334a1 1 0 0 1-.936 1.35l-.383.03a3 3 0 0 0-2.686 2.686l-.03.383a1 1 0 0 1-1.35.936l-.334-.112a9 9 0 0 1-4.841-4.84l-.111-.334a1 1 0 0 1 .936-1.35l.383-.03a3 3 0 0 0 2.686-2.686l.03-.383Z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
        </svg>
        @break
    @case('logout')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l3-3m0 0 3 3m-3-3v12"/>
        </svg>
        @break
    @case('bell')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 1 1-6 0v-1h6Z"/>
        </svg>
        @break
    @case('download')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16"/>
        </svg>
        @break
    @case('seat')
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $classes }}" width="{{ $size }}" height="{{ $size }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M7 12h10l1 7H6l1-7Zm1-6h8a2 2 0 0 1 2 2v3H6V8a2 2 0 0 1 2-2Z"/>
        </svg>
        @break
@endswitch
