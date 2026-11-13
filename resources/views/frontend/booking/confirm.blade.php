<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <title>TD Cinema - Xác nhận thanh toán</title>
    <link rel="stylesheet" href="{{ asset('assets/css/booking/confirm.css') }}?v={{ filemtime(public_path('assets/css/booking/confirm.css')) }}">
</head>
<body>
<div class="page">
    @if(!empty($success))
        <div class="banner">
            <div class="badge ok">✔</div>
            <div>
                <div class="value">{{ !empty($demoPayment) ? 'Đặt vé mô phỏng thành công' : 'Đặt vé thành công' }}</div>
                <div class="label">{{ !empty($demoPayment) ? 'Thông tin vé đã được ghi nhận cho đồ án. Không có tiền được trừ từ tài khoản của bạn.' : 'Cảm ơn bạn đã thanh toán, thông tin vé đã được ghi nhận.' }}</div>
                @if(($pointsEarned ?? 0) > 0)<div class="label">Bạn đã nhận {{ number_format($pointsEarned,0,',','.') }} Stars. <a href="{{ route('customer.membership') }}">Xem hạng & quyền lợi →</a></div>@endif
            </div>
        </div>
    @else
        <div class="banner" style="background:#fdecec;border-color:#f5c2c0;">
            <div class="badge fail">!</div>
            <div>
                <div class="value" style="color:#dc2626;">Thanh toán thất bại</div>
                <div class="label">{{ $message ?? 'Vui lòng thử lại.' }}</div>
            </div>
        </div>
    @endif

    @if(!empty($showtime))
        <h1>{{ $showtime->movie->title ?? 'Vé phim' }}</h1>

        <div class="grid">
            <div class="panel">
                <div class="movie-info">
                    @php
                        $poster = $showtime->movie->posterUrl();
                    @endphp
                    <img src="{{ $poster }}" alt="Poster">
                    <div>
                        <div class="label">Định dạng</div>
                        <div class="value">{{ strtoupper($showtime->movie->format ?? '2D') }}</div>
                        <div class="label" style="margin-top:6px;">Rạp chiếu</div>
                        <div class="value">{{ $showtime->room->cinema->name ?? '' }} - {{ $showtime->room->name ?? '' }}</div>
                    </div>
                </div>

                <div class="divider"></div>
                <div class="list">
                    <div class="item"><span class="label">Ngày</span><span class="value">{{ \Carbon\Carbon::parse($showtime->start_time)->format('d/m/Y') }}</span></div>
                    <div class="item"><span class="label">Suất chiếu</span><span class="value">{{ \Carbon\Carbon::parse($showtime->start_time)->format('H:i') }}</span></div>
                    <div class="item"><span class="label">Ghế</span><span class="value">{{ $selectedSeats->implode(', ') ?: 'N/A' }}</span></div>
                    @if(!empty($success) && !empty($foodPickupCode))
                        <div class="item"><span class="label">Mã nhận bắp nước</span><span class="value">{{ $foodPickupCode }}</span></div>
                    @endif
                </div>

                <div class="divider"></div>
                <div class="list">
                    <div class="item"><span class="label">Vé</span><span class="value">{{ number_format($ticketTotal, 0, ',', '.') }} đ</span></div>
                    <div class="item"><span class="label">Combo</span><span class="value">{{ number_format($comboTotal, 0, ',', '.') }} đ</span></div>
                    @if(($promoDiscount ?? 0) > 0)<div class="item"><span class="label">Khuyến mãi {{ $appliedPromoCode ?? '' }}</span><span class="value">-{{ number_format($promoDiscount, 0, ',', '.') }} đ</span></div>@endif
                </div>
                <div class="divider"></div>
                <div class="total"><span>Tổng thanh toán</span><span>{{ number_format($grandTotal, 0, ',', '.') }} đ</span></div>
            </div>

            <div class="panel">
                <div class="ticket-box">
                    <div class="label">Mã vé</div>
                    <div class="ticket-id">{{ $bookingCode ?? strtoupper(substr(md5(now()), 0, 10)) }}</div>
                    @if(!empty($qrUrl))
                        <img src="{{ $qrUrl }}" alt="QR code" style="width:160px;height:160px;object-fit:contain;margin:10px auto 6px;display:block;">
                    @endif
                    <div class="muted">Vui lòng xuất trình mã hoặc QR này khi vào rạp.</div>
                </div>
                <div class="actions">
                    <a class="btn btn-primary" href="#">Xem E-Ticket</a>
                    <button class="btn btn-outline" onclick="window.print()">In vé</button>
                    <button type="button" class="btn btn-neutral" data-share-ticket>Chia sẻ</button>
                    <a class="btn btn-outline" href="{{ route('home') }}">Về trang chủ</a>
                </div>
            </div>
        </div>
    @else
        <p class="label" style="margin-top:12px;">Không tìm thấy thông tin suất chiếu.</p>
    @endif
</div>
@include('shared.notifications', ['pageNotifications' => [['type' => !empty($success) ? 'success' : 'error', 'message' => !empty($success) ? (!empty($demoPayment) ? 'Đặt vé mô phỏng thành công. Không có tiền được trừ từ tài khoản của bạn.' : 'Đặt vé thành công. Bạn có thể xem vé trong lịch sử vé.') : ($message ?? 'Thanh toán thất bại. Vui lòng thử lại.')]]])
</body>
</html>
