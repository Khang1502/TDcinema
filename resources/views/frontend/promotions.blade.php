<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="csrf-token" content="{{ csrf_token() }}">
    @include('shared.page-assets')
    <title>Khuyến mãi | TD Cinema</title>
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/movies.css') }}?v={{ filemtime(public_path('assets/css/frontend/movies.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/promotions.css') }}?v={{ filemtime(public_path('assets/css/frontend/promotions.css')) }}">
    @include('frontend.partials.header-assets')
</head>
<body data-login-error="{{ $errors->has('email') && !session('reset_error') ? '1' : '0' }}"
      data-reset-error="{{ session('reset_error') ? '1' : '0' }}"
      data-reset-sent="{{ session('reset_sent') ? '1' : '0' }}"
      data-reset-email="{{ session('reset_email') }}">
<div class="page">
    @include('frontend.partials.header', ['searchId' => 'promotions-movie-search'])
    <main class="offers-page">
        @if($errors->any())<div data-inline-notification class="offer-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <div class="offers-intro"><div><span class="offers-kicker">ƯU ĐÃI TẠI TD CINEMA</span><h1>Xem phim vui hơn.<br><em>Tiết kiệm nhiều hơn.</em></h1><p>Chọn mã yêu thích, đặt vé và áp dụng ngay khi thanh toán.</p></div><div class="offers-guide"><span>01 <b>Chọn ưu đãi</b></span><span>02 <b>Chọn phim & ghế</b></span><span>03 <b>Áp dụng tại thanh toán</b></span></div></div>
        <nav class="offers-tabs" aria-label="Trạng thái khuyến mãi"><a href="{{ route('promotions.index') }}" @class(['active' => $filter === 'active'])>Đang diễn ra <b>{{ $counts['active'] }}</b></a><a href="{{ route('promotions.index', ['filter' => 'upcoming']) }}" @class(['active' => $filter === 'upcoming'])>Sắp diễn ra <b>{{ $counts['upcoming'] }}</b></a></nav>
        <div class="offer-grid">@forelse($promotions as $promo)@include('frontend.partials.promotion-card')@empty<div class="offers-empty"><span aria-hidden="true">%</span><h2>{{ $filter === 'upcoming' ? 'Chưa có ưu đãi sắp diễn ra' : 'Ưu đãi mới đang được cập nhật' }}</h2><p>Các mã còn hiệu lực sẽ xuất hiện tại đây và ở bước thanh toán.</p><a href="{{ route('movies.index') }}">Khám phá phim →</a></div>@endforelse</div>
        @if($promotions->hasPages())<div class="offers-pagination">{{ $promotions->links() }}</div>@endif

    </main>
</div>
@include('frontend.partials.footer')
@include('frontend.partials.auth-modal')
<script src="{{ asset('assets/js/frontend/auth-modal.js') }}" defer></script>
<script src="{{ asset('assets/js/frontend/promotions.js') }}?v={{ filemtime(public_path('assets/js/frontend/promotions.js')) }}" defer></script>
@include('shared.notifications')
</body>
</html>
