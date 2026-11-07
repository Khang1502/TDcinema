@php
    use Illuminate\Support\Str;
    use Carbon\Carbon;

    $movie = $showtime->movie;
    $posterUrl = $movie->posterUrl();
    $ageBadge = strtoupper($movie->age_rating ?? 'T13');
    $ticketCount = max($selectedSeats->count(), 0);
    $ticketPrice = (float) ($showtime->base_price ?? 0);
    $ticketTotal = $ticketCount * $ticketPrice;
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <title>Chọn combo - {{ $movie->title }}</title>
        <link rel="stylesheet" href="{{ asset('assets/css/booking/combo.css') }}?v={{ filemtime(public_path('assets/css/booking/combo.css')) }}">
</head>
<body data-hold-expires-at="{{ $holdExpiresAt ?? '' }}"
      data-expired-url="{{ route('booking.checkout', $showtime) }}"
      data-ticket-total="{{ $ticketTotal }}"
      data-seat-param="{{ $selectedSeats->implode(',') }}"
      data-payment-url="{{ route('booking.payment', $showtime) }}"
      data-currency-suffix=" đ"
      data-max-combo-qty="10">
    <div class="topbar">
        <div class="topbar-inner">
            <a href="{{ route('home') }}" class="topbar-logo">
                <img src="{{ asset('assets/images/logo/logo-transparent.png') }}" alt="TD Cinema">
                <span>TD Cinema</span>
            </a>
            <a href="{{ route('home') }}" class="topbar-cancel">Hủy giao dịch ×</a>
        </div>
    </div>

    <div class="page">
        <div class="progress">
            <div class="step done">1. Chọn phim / Rạp / Suất</div>
            <div class="step done">2. Chọn ghế</div>
            <div class="step active">3. Combo</div>
            <div class="step">4. Thanh toán</div>
            <div class="step">5. Xác nhận</div>
        </div>

        <div class="layout">
            <div class="card">
                <div class="list-title">Chọn Combo / Sản phẩm</div>
                @foreach($combos as $combo)
                    <div class="combo-item" data-id="{{ $combo->id }}" data-price="{{ $combo->price }}">
                        <div class="combo-thumb">
                            <img src="{{ $combo->imageUrl() ?? asset('assets/images/ui/cinema-placeholder.svg') }}" alt="{{ $combo->name }}">
                        </div>
                        <div>
                            <div class="combo-name">{{ $combo->name }}</div>
                            <div class="combo-desc">{{ Str::limit($combo->description ?? 'Combo', 120) }}</div>
                            <div class="combo-price">{{ number_format($combo->price, 0, ',', '.') }} đ</div>
                        </div>
                        <div class="qty">
                            <button class="btn-minus">-</button>
                            <span class="qty-value">0</span>
                            <button class="btn-plus">+</button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card">
                <div class="summary-bar"></div>
                <div class="timer">Thời gian giữ ghế: <span id="countdown">--:--</span></div>
                <div class="summary-head">
                    <div><img src="{{ $posterUrl }}" alt="{{ $movie->title }}" style="width:90px;height:130px;object-fit:cover;border:1px solid #e5e7eb;"></div>
                    <div>
                        <div class="summary-title">{{ $movie->title }}</div>
                        <div class="summary-meta">2D Phụ Đề – <span class="badge">{{ $ageBadge }}</span></div>
                    </div>
                </div>
                <div class="summary-meta" style="margin-top:10px;font-weight:700;">{{ $showtime->room->cinema->name ?? '-' }} - {{ strtoupper($showtime->room->name ?? '-') }}</div>
                <div class="summary-meta">Suất: <strong>{{ $startTime }}</strong> - {{ $startDate }}</div>
                <div class="summary-meta">Ghế: {{ $selectedSeats->isNotEmpty() ? $selectedSeats->implode(', ') : 'Chưa chọn ghế' }}</div>

                <div class="divider"></div>
                <div id="summaryItems">
                    @if($ticketCount > 0)
                        <div class="row"><span>{{ $ticketCount }}x Vé</span><span id="ticketTotal">{{ number_format($ticketTotal, 0, ',', '.') }} đ</span></div>
                    @endif
                </div>
                <div class="divider"></div>
                <div class="total"><span>Tổng cộng</span><span id="grandTotal">{{ number_format($ticketTotal, 0, ',', '.') }} đ</span></div>
                <div class="actions">
                    <a href="{{ route('booking.checkout', $showtime) }}" class="btn ghost">Quay lại</a>
                    <a id="payLink" href="{{ route('booking.payment', $showtime) }}" class="btn primary" style="display:inline-flex;align-items:center;justify-content:center;">Tiếp tục</a>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/js/booking/combo.js') }}" defer></script>
@include('shared.notifications')
</body>
</html>
