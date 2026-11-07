<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} | TD Cinema</title>
    <meta name="description" content="@yield('description', 'Bình luận phim, blog và những câu chuyện điện ảnh tại TD Cinema.')">
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/movies.css') }}?v={{ filemtime(public_path('assets/css/frontend/movies.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/cinema-corner.css') }}?v={{ filemtime(public_path('assets/css/frontend/cinema-corner.css')) }}">
    @include('frontend.partials.header-assets')
</head>
<body class="editorial-page" data-is-authenticated="{{ auth()->check() ? '1' : '0' }}" data-is-customer="{{ auth()->user()?->role === 'customer' ? '1' : '0' }}"
      data-booking-options-url="{{ route('booking.options') }}" data-quick-booking-save-url="{{ route('booking.quick-intent') }}" data-booking-checkout-url="{{ url('/booking/checkout') }}"
      data-quick-selection='@json(session('booking.quick_selection'))'>
@include('frontend.partials.header', ['searchId' => 'editorial-movie-search'])
<main class="editorial-container editorial-page-main">
    <nav class="editorial-breadcrumb" aria-label="Đường dẫn"><a href="{{ route('home') }}">Trang chủ</a><span aria-hidden="true">›</span><a href="{{ route('cinema-corner.index', $category) }}">{{ \App\Models\CinemaCornerItem::CATEGORIES[$category] }}</a>@yield('breadcrumb')</nav>
    <div class="editorial-page-grid">
        <div class="editorial-page-content">@yield('content')</div>
        @include('frontend.cinema-corner.sidebar')
    </div>
</main>
@include('frontend.partials.footer')
@include('frontend.partials.auth-modal')
@include('frontend.partials.trailer-modal')
<script src="{{ asset('assets/js/frontend/auth-modal.js') }}?v={{ filemtime(public_path('assets/js/frontend/auth-modal.js')) }}" defer></script>
<script src="{{ asset('assets/js/frontend/cinema-corner.js') }}?v={{ filemtime(public_path('assets/js/frontend/cinema-corner.js')) }}" defer></script>
@include('shared.notifications')
</body>
</html>
