<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <meta name="description" content="Danh sách rạp và giá vé cập nhật tại hệ thống TD Cinema.">
    <title>Rạp & Giá vé | TD Cinema</title>
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/movies.css') }}?v={{ filemtime(public_path('assets/css/frontend/movies.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/cinemas.css') }}?v={{ filemtime(public_path('assets/css/frontend/cinemas.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/banner-slider.css') }}?v={{ filemtime(public_path('assets/css/frontend/banner-slider.css')) }}">
    @include('frontend.partials.header-assets')
</head>
<body data-login-error="{{ $errors->has('email') && !session('reset_error') ? '1' : '0' }}"
      data-reset-error="{{ session('reset_error') ? '1' : '0' }}"
      data-reset-sent="{{ session('reset_sent') ? '1' : '0' }}"
      data-reset-email="{{ session('reset_email') }}">
<div class="page" id="top">
    @include('frontend.partials.header', ['searchId' => 'cinemas-movie-search'])

    <main>
        @include('frontend.partials.banner-slider', ['bannerLabel' => 'Banner Rạp và Giá vé', 'bannerVariant' => 'cinemas'])

        <section class="cinema-container cinema-list" id="cinema-list">
            <div class="cinema-heading">
                <div>
                    <span class="cinema-eyebrow cinema-eyebrow--orange">Chọn địa điểm</span>
                    <h1>Hệ thống rạp</h1>
                </div>
                <p>{{ $cinemas->count() }} rạp đang hoạt động</p>
            </div>

            <nav class="region-tabs" aria-label="Lọc rạp theo khu vực">
                <a href="{{ route('cinemas.index') }}#cinema-list" class="{{ $selectedRegion === '' && $selectedCity === '' ? 'active' : '' }}">Tất cả khu vực <span>{{ $cinemaDirectory->count() }}</span></a>
                @foreach($regions as $region)
                    <a href="{{ route('cinemas.index', ['region' => $region['key']]) }}#cinema-list" class="{{ $selectedRegion === $region['key'] ? 'active' : '' }}">{{ $region['label'] }} <span>{{ $region['count'] }}</span></a>
                @endforeach
            </nav>
            <nav class="city-tabs" aria-label="Lọc rạp theo tỉnh thành">
                <a href="{{ route('cinemas.index', array_filter(['region' => $selectedRegion])) }}#cinema-list" class="{{ $selectedCity === '' ? 'active' : '' }}">Tất cả tỉnh/thành phố</a>
                @foreach($cities as $city)
                    <a href="{{ route('cinemas.index', array_filter(['region' => $selectedRegion, 'city' => $city])) }}#cinema-list" class="{{ $selectedCity === $city ? 'active' : '' }}">{{ $city }}</a>
                @endforeach
            </nav>

            @forelse($cinemaGroups as $group)
            <div class="cinema-region-heading"><h3>{{ $group['label'] }}</h3><span>{{ $group['count'] }} rạp</span></div>
            <div class="cinema-grid">
                @foreach($group['cinemas'] as $cinema)
                    <article class="cinema-card" id="cinema-{{ $cinema->id }}">
                        <div class="cinema-card__photo">
                            @if($cinema->hasImage())
                                <button type="button" class="cinema-photo-open" data-cinema-photo="{{ $cinema->imageUrl() }}" data-cinema-name="{{ $cinema->name }}" aria-label="Xem ảnh đầy đủ của {{ $cinema->name }}" aria-haspopup="dialog">
                                    <img src="{{ $cinema->imageUrl() }}" alt="Ảnh {{ $cinema->name }}" width="640" height="360" loading="lazy">
                                    <span class="cinema-photo-open__label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5" stroke-linecap="round" stroke-linejoin="round"/></svg>Xem ảnh</span>
                                </button>
                            @else
                                <img src="{{ $cinema->imageUrl() }}" alt="" width="640" height="360" loading="lazy">
                                <span class="cinema-photo-pending">Hình ảnh đang cập nhật</span>
                            @endif
                            <span class="cinema-card__status"><i></i> Đang hoạt động</span>
                        </div>
                        <div class="cinema-card__body">
                        <div class="cinema-card__city">{{ $cinema->city ?: 'Việt Nam' }}</div>
                        <h3>{{ $cinema->name }}</h3>
                        <p class="cinema-card__address">{{ $cinema->address ?: 'Địa chỉ đang cập nhật' }}</p>
                        <div class="cinema-card__meta">
                            <div><span>Phòng chiếu</span><strong>{{ $cinema->active_rooms_count }}</strong></div>
                            <div><span>Giá vé từ</span><strong>{{ $cinema->min_ticket_price ? number_format($cinema->min_ticket_price, 0, ',', '.') . ' đ' : 'Đang cập nhật' }}</strong></div>
                        </div>
                        <a href="{{ route('movies.index', ['status' => 'now_showing', 'cinema_id' => $cinema->id]) }}" class="cinema-card__action">Chọn phim tại rạp <span>→</span></a>
                        </div>
                    </article>
                @endforeach
            </div>
            @empty
                    <div class="cinema-empty">
                        <span aria-hidden="true">⌖</span>
                        <h3>Chưa có rạp tại khu vực này</h3>
                        <p>TD Cinema sẽ sớm cập nhật thêm địa điểm mới.</p>
                        <a href="{{ route('cinemas.index') }}">Xem toàn bộ hệ thống</a>
                    </div>
            @endforelse
        </section>

        <section class="ticket-prices" id="ticket-prices">
            <div class="cinema-container">
                <div class="cinema-heading cinema-heading--light">
                    <div>
                        <span class="cinema-eyebrow">Bảng giá tham khảo</span>
                        <h2>Giá theo định dạng phòng</h2>
                    </div>
                    <p>Cập nhật từ các suất chiếu đang hoạt động</p>
                </div>
                <div class="price-grid">
                    @forelse($priceFormats as $format)
                        <article class="price-card price-card--{{ strtolower($format->type) }}">
                            <div class="price-card__format">{{ strtoupper($format->type) }}</div>
                            <p>Trải nghiệm phòng chiếu {{ strtoupper($format->type) }}</p>
                            <div class="price-card__value">
                                <span>Từ</span>
                                <strong>{{ number_format($format->min_price, 0, ',', '.') }} đ</strong>
                            </div>
                            @if((float) $format->max_price !== (float) $format->min_price)
                                <small>Cao nhất {{ number_format($format->max_price, 0, ',', '.') }} đ</small>
                            @else
                                <small>Một mức giá hiện hành</small>
                            @endif
                        </article>
                    @empty
                        <div class="price-empty">Bảng giá đang được cập nhật.</div>
                    @endforelse
                </div>
                <div class="price-note">
                    <span aria-hidden="true">i</span>
                    <p><strong>Lưu ý về giá vé</strong>Giá thực tế phụ thuộc vào rạp, khung giờ, định dạng phòng và loại ghế. Mức chính xác luôn hiển thị trước khi bạn xác nhận đặt vé.</p>
                    <a href="{{ route('movies.index', ['status' => 'now_showing']) }}">Chọn phim <span>→</span></a>
                </div>
            </div>
        </section>
    </main>

    @include('frontend.partials.footer')
</div>

<dialog class="cinema-photo-dialog" data-cinema-photo-dialog aria-labelledby="cinema-photo-title">
    <div class="cinema-photo-dialog__heading">
        <h2 id="cinema-photo-title"></h2>
        <button type="button" data-cinema-photo-close aria-label="Đóng ảnh rạp">×</button>
    </div>
    <img class="cinema-photo-dialog__image" alt="">
</dialog>

@include('frontend.partials.auth-modal')
<script src="{{ asset('assets/js/frontend/auth-modal.js') }}" defer></script>
<script src="{{ asset('assets/js/frontend/banner-slider.js') }}?v={{ filemtime(public_path('assets/js/frontend/banner-slider.js')) }}" defer></script>
<script src="{{ asset('assets/js/frontend/cinemas.js') }}?v={{ filemtime(public_path('assets/js/frontend/cinemas.js')) }}" defer></script>
@include('shared.notifications')
</body>
</html>
