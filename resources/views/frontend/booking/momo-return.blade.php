<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <title>Kết quả thanh toán MoMo</title>
        <link rel="stylesheet" href="{{ asset('assets/css/booking/momo-return.css') }}?v={{ filemtime(public_path('assets/css/booking/momo-return.css')) }}">
</head>
<body>
    <div class="card">
        <div class="{{ $success ? 'success' : 'fail' }}">
            {{ $success ? 'Thanh toán thành công' : 'Thanh toán thất bại' }}
        </div>
        <p style="margin-top:12px;">{{ $message ?? '' }}</p>
        <div style="margin-top:18px;"><a href="{{ route('home') }}">Về trang chủ</a></div>
    </div>
@include('shared.notifications', ['pageNotifications' => [['type' => $success ? 'success' : 'error', 'message' => $message ?: ($success ? 'Thanh toán thành công.' : 'Thanh toán thất bại. Vui lòng thử lại.')]]])
</body>
</html>

