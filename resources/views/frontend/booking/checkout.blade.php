@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    $movie = $showtime->movie;
    $posterUrl = fn ($movie) => $movie->posterUrl();
    $bannerUrl = fn ($movie) => $movie->bannerUrl();

    $ageBadge = strtoupper($movie->age_rating ?? 'T13');
    $ogTitle  = 'Chọn ghế - ' . ($movie->title ?? 'TD Cinema');
    $ogDesc   = Str::limit(trim($movie->description ?? ''), 150);
    $heroBg   = $bannerUrl($movie);
    $seatLayout = \App\Support\SeatMapLayout::forRows($seatRows);
@endphp

<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $ogTitle }}</title>
    <meta name="description" content="{{ $ogDesc }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDesc }}">
    <meta property="og:image" content="{{ $posterUrl($movie) }}">
    <meta property="og:type" content="video.movie">
    <meta property="og:url" content="{{ url()->current() }}">
        <link rel="stylesheet" href="{{ asset('assets/css/booking/checkout.css') }}?v={{ filemtime(public_path('assets/css/booking/checkout.css')) }}">
    @include('frontend.partials.header-assets')
</head>
<body data-combo-url="{{ route('booking.combo', $showtime) }}"
      data-hold-url="{{ route('booking.hold', $showtime) }}"
      data-hold-expires-at="{{ $holdExpiresAt ?? '' }}"
      data-initial-seats='@json(($initialSelected ?? collect())->values())'
      data-max-seats="{{ $maxSelectable ?? 8 }}"
      data-msg-seat-required="Vui lòng chọn ít nhất 1 ghế."
      data-msg-seat-limit="Chỉ được chọn tối đa {max} ghế."
      data-currency-suffix=" đ">
@include('frontend.partials.header', ['searchId' => 'checkout-movie-search', 'cinemaId' => $showtime->room?->cinema_id])

<section class="hero">
    <div class="hero-bg" style="background-image:url('{{ $heroBg }}')"></div>
    <div class="hero-inner">
        <div class="hero-poster"><img src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}" onerror="this.onerror=null;this.src='{{ asset('assets/images/logo/logo-transparent.png') }}'"></div>
        <div>
            <div class="hero-title">{{ $movie->title }} <span class="badge">{{ $ageBadge }}</span></div>
            <div style="color:#e5e7eb;font-size:14px;display:flex;gap:8px;flex-wrap:wrap;">
                <span>{{ $movie->genres ?? 'Thể loại' }}</span><span>•</span><span>{{ $movie->duration ?? '---' }} phút</span><span>•</span><span>{{ $dateLabel ?? '' }}</span>
            </div>
        </div>
    </div>
</section>

<div class="page">

    <div class="progress">
        <div class="step done">1. Chọn phim / Rạp / Suất</div>
        <div class="step active">2. Chọn ghế</div>
        <div class="step">3. Combo</div>
        <div class="step">4. Thanh toán</div>
        <div class="step">5. Xác nhận</div>
    </div>

    @php
        $currentTime  = Carbon::parse($showtime->start_time)->format('H:i');
    @endphp
    <div class="card" style="margin-bottom:12px;">
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <div class="info" style="font-weight:700;margin-bottom:0;">Đổi suất chiếu</div>
            <div class="pills">
                @foreach($switchShowtimes as $st)
                    @php $isActive = !empty($st['active']); @endphp
                    <a class="pill {{ $isActive ? 'active' : '' }}" href="{{ $isActive ? '#' : route('booking.checkout', $st['id']) }}">
                        {{ $st['time'] ?? '--:--' }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid">
        <div class="card seat-card">
            <div class="seat-card-heading"><div><span>BƯỚC 02</span><h1>Chọn vị trí đẹp nhất</h1></div><p>Chọn tối đa {{ $maxSelectable ?? 8 }} ghế</p></div>
            <div class="screen"><span>Màn hình</span><div class="screen-bar"></div><small>Hướng nhìn</small></div>
            <div class="seat-map" tabindex="0" role="region" aria-label="Sơ đồ ghế, cuộn ngang để xem toàn bộ ghế trên màn hình nhỏ">
                <div class="seat-map-rows" style="--seat-layout: {{ $seatLayout['template'] }}; --seat-map-width: {{ $seatLayout['width'] }}px; --seat-map-min-width: {{ $seatLayout['min_width'] }}px;">
                @forelse($seatRows as $rowLabel => $rowSeats)
                    <div class="row {{ $seatLayout['rows'][$rowLabel]['cross_aisle'] ? 'row--cross-aisle' : '' }}">
                        <div class="row-label">{{ $rowLabel }}</div>
                        <div class="row-seats">
                            @foreach($seatLayout['aisles'] as $aisleColumn)
                                <span class="seat-aisle" style="grid-column: {{ $aisleColumn }};" aria-hidden="true"></span>
                            @endforeach
                            @foreach($rowSeats as $seat)
                                @php
                                    $state = match ($seat['status']) {
                                        'sold' => 'sold',
                                        'held' => 'held',
                                        'inactive' => 'inactive',
                                        default => '',
                                    };
                                @endphp
                                <button type="button" class="seat {{ $seat['type'] }} {{ $state }}"
                                        style="grid-column: {{ $seatLayout['columns'][$seat['number']] }};"
                                        data-seat-id="{{ $seat['id'] }}"
                                        data-code="{{ $seat['code'] }}"
                                        data-price="{{ $seat['price'] }}"
                                        data-row="{{ $seat['row'] }}"
                                        data-number="{{ $seat['number'] }}"
                                        aria-label="Ghế {{ $seat['code'] }}, {{ number_format($seat['price'], 0, ',', '.') }} đồng{{ match ($state) { 'sold' => ', đã bán', 'held' => ', đang được giữ', 'inactive' => ', không khả dụng', default => '' } }}"
                                        @if($state) title="Ghế {{ $seat['code'] }} · {{ match ($state) { 'sold' => 'Đã bán', 'held' => 'Đang được giữ', default => 'Không khả dụng' } }}" @endif
                                        aria-pressed="false"
                                        @if($state) disabled @endif>{{ $seat['number'] }}</button>
                            @endforeach
                        </div>
                        <div class="row-label">{{ $rowLabel }}</div>
                    </div>
                @empty
                    <div class="empty">Chưa có sơ đồ ghế.</div>
                @endforelse
                </div>
            </div>
            <p class="seat-map-hint"><span aria-hidden="true">↔</span> Lối đi giữa các cụm ghế <span class="seat-map-hint__scroll">· Vuốt ngang để xem toàn bộ ghế</span></p>
            <div class="legend">
                <span><span class="dot"></span> Ghế thường</span>
                <span><span class="dot sold"></span> Đã bán</span>
                <span><span class="dot held"></span> Đang được giữ</span>
                <span><span class="dot sel"></span> Đang chọn</span>
                <span><span class="dot vip"></span> Ghế VIP</span>
                <span><span class="dot double"></span> Ghế đôi</span>
            </div>
        </div>

        <div class="card summary">
            <div class="summary-bar"></div>
            @if($holdExpiresAt)
                <div class="hold-timer">Ghế đang được giữ · <strong id="countdown">--:--</strong></div>
            @endif
            <div class="summary-movie">
                <div style="width:90px;overflow:hidden;border:1px solid #e5e7eb;"><img src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}" onerror="this.onerror=null;this.src='{{ asset('assets/images/logo/logo-transparent.png') }}'"></div>
                <div>
                    <div class="summary-title">{{ $movie->title }}</div>
                    <div class="summary-genre">2D Phụ Đề – <span class="badge" style="background:var(--p);color:#fff;border-radius:6px;padding:2px 8px;font-size:12px;">{{ $ageBadge }}</span></div>
                </div>
            </div>
            <div class="summary-movie">
                <div></div>
                <div style="display:flex;flex-direction:column;gap:4px;">
                    <div class="summary-genre" style="font-weight:700;color:#1f2937;">{{ $showtime->room->cinema->name ?? '-' }} - {{ strtoupper($showtime->room->name ?? '-') }}</div>
                    <div class="summary-meta">Suất: <strong>{{ Carbon::parse($showtime->start_time)->format('H:i') }}</strong> - {{ Carbon::parse($showtime->start_time)->translatedFormat('l, d/m/Y') }}</div>
                </div>
            </div>
            <div class="summary-meta" style="margin-top:4px;">Giá cơ bản: {{ number_format($showtime->base_price ?? 0, 0, ',', '.') }} đ</div>
            <div style="border-top:1px dashed #e5e7eb;margin:12px 0 0;padding-top:12px;">
                <div class="selected-heading"><span>Ghế đã chọn</span><b id="selectedCount">0/{{ $maxSelectable ?? 8 }}</b></div>
                <div id="selectedList" class="chips"></div>
                <div id="emptySelected" class="empty">Chưa chọn ghế</div>
                <div id="seatAlert" class="alert"></div>
                <div class="total"><span>Tổng cộng</span><span id="totalPrice">0 đ</span></div>
            </div>
            <div class="actions">
                <a href="{{ route('movies.show', ['movie' => $movie, 'cinema_id' => $showtime->room?->cinema_id]) }}#showtimes" class="btn ghost" style="flex:1;">Quay lại</a>
                <button type="button" class="btn primary" id="btnContinue" style="flex:1;">Tiếp tục</button>
            </div>
            <p class="note">Các ghế dùng chung hạn 10 phút từ lượt giữ đầu tiên, xuyên suốt Combo → Thanh toán. Thêm hoặc đổi ghế không gia hạn thời gian.</p>
        </div>
    </div>
</div>

@include('frontend.partials.footer')

<div class="overlay" id="authOverlay">
    <div class="modal" id="loginModal">
        <button class="close-btn" id="closeLogin">×</button>
        <h4>Đăng nhập</h4>
        <p>Đăng nhập để tiếp tục.</p>
        <form>
            <input type="email" placeholder="Email">
            <input type="password" placeholder="Mật khẩu">
            <button type="button" class="btn primary" style="width:100%;">Đăng nhập</button>
            <div class="info" style="margin-top:4px;">Chưa có tài khoản? <a href="#" id="openRegister" style="color:var(--p);font-weight:700;">Đăng ký</a></div>
        </form>
    </div>
    <div class="modal" id="registerModal" style="display:none;">
        <button class="close-btn" id="closeRegister">×</button>
        <h4>Đăng ký</h4>
        <p>Tạo tài khoản mới.</p>
        <form>
            <input type="text" placeholder="Họ và tên">
            <input type="email" placeholder="Email">
            <input type="password" placeholder="Mật khẩu">
            <button type="button" class="btn primary" style="width:100%;">Đăng ký</button>
            <div class="info" style="margin-top:4px;">Đã có tài khoản? <a href="#" id="backLogin" style="color:var(--p);font-weight:700;">Đăng nhập</a></div>
        </form>
    </div>
</div>

    <script src="{{ asset('assets/js/booking/checkout.js') }}?v={{ filemtime(public_path('assets/js/booking/checkout.js')) }}" defer></script>
@include('shared.notifications')
</body>
</html>
