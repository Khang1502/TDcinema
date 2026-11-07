@once
    @push('styles')<link rel="stylesheet" href="{{ asset('assets/css/admin/form-menus.css') }}?v={{ filemtime(public_path('assets/css/admin/form-menus.css')) }}">@endpush
    @push('scripts')<script src="{{ asset('assets/js/admin/form-menus.js') }}?v={{ filemtime(public_path('assets/js/admin/form-menus.js')) }}" defer></script>@endpush
@endonce
