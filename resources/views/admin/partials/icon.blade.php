@php
    $iconName = config('admin_icons.aliases.'.$name, $name);
    $iconName = in_array($iconName, config('admin_icons.names', []), true) ? $iconName : 'circle-help';
    $size = $size ?? 18;
@endphp
<svg xmlns="http://www.w3.org/2000/svg" class="admin-icon {{ $classes ?? '' }}" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" data-admin-icon="{{ $iconName }}"><use href="{{ asset('assets/icons/admin/sprite.svg') }}?v={{ filemtime(public_path('assets/icons/admin/sprite.svg')) }}#{{ $iconName }}"></use></svg>
