<!DOCTYPE html>
<html lang="vi">
<head>
    @php $adminBrand = \App\Models\HeaderSetting::current(); @endphp
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bảng điều khiển') · TD Cinema Admin</title>
    <link rel="icon" href="{{ $adminBrand->logoUrl() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('assets/css/admin/base.css') }}?v={{ filemtime(public_path('assets/css/admin/base.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin/images.css') }}?v={{ filemtime(public_path('assets/css/admin/images.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pagination.css') }}?v={{ filemtime(public_path('assets/css/pagination.css')) }}">
    @stack('styles')
</head>
<body class="admin-body">
    <div class="admin-shell">
        @include('admin.components.sidebar')
        <script src="{{ asset('assets/js/admin/navigation.js') }}?v={{ filemtime(public_path('assets/js/admin/navigation.js')) }}"></script>
        <button class="admin-backdrop" type="button" aria-label="Đóng menu" data-sidebar-close></button>
        <div class="admin-main">
            @include('admin.components.header')
            <main class="admin-content">
                @yield('content')
            </main>
        </div>
    </div>
    <script src="{{ asset('assets/js/admin/images.js') }}?v={{ filemtime(public_path('assets/js/admin/images.js')) }}" defer></script>
    @stack('scripts')
@include('shared.notifications')
</body>
</html>
