@php
    $posterUrl = fn ($movie) => $movie->posterUrl();
@endphp

<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <title>Phim | TD Cinema</title>
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/movies.css') }}?v={{ filemtime(public_path('assets/css/frontend/movies.css')) }}">
    @include('frontend.partials.header-assets')
</head>
<body data-login-error="{{ $errors->has('email') && !session('reset_error') ? '1' : '0' }}"
      data-reset-error="{{ session('reset_error') ? '1' : '0' }}"
      data-reset-sent="{{ session('reset_sent') ? '1' : '0' }}"
      data-reset-email="{{ session('reset_email') }}">
<div class="page" id="top">
    @php
        $navNow = \App\Models\Movie::where('status','now_showing')->orderByDesc('release_date')->take(4)->get();
        $navUpcoming = \App\Models\Movie::where('status','upcoming')->orderByDesc('release_date')->take(4)->get();
        $navImax = \App\Models\Movie::imax()->orderByDesc('release_date')->take(4)->get();
        $navPoster = fn ($movie) => $movie->posterUrl();
    @endphp
    @include('frontend.partials.header', ['searchId' => 'movies-movie-search', 'cinemaId' => $selectedCinema?->id])
    @if(session('promotion.preferred_code'))<div class="container" style="padding-top:18px"><p role="status" style="padding:14px 18px;border:1px solid #b9d7ee;border-radius:10px;background:#edf6ff;color:#145589;font-size:13px">Đã chọn ưu đãi <strong>{{ session('promotion.preferred_code') }}</strong>. Mã sẽ được áp dụng ở bước thanh toán nếu còn hiệu lực.</p></div>@endif

    <div class="container">
        @if(($search ?? '') !== '')
        <section class="movie-search-summary" aria-live="polite">
            <div><span>Kết quả tìm kiếm</span><h1>“{{ $search }}”</h1><p>Tìm thấy {{ $movies->total() }} phim phù hợp.</p></div>
            <a href="{{ route('movies.index', array_filter(['status' => 'all', 'cinema_id' => $selectedCinema?->id])) }}">Xóa tìm kiếm</a>
        </section>
        @endif
        @if($selectedCinema)
            <section class="selected-cinema" aria-label="Rạp đã chọn">
                <div class="selected-cinema__icon" aria-hidden="true">⌖</div>
                <div class="selected-cinema__info">
                    <span>Rạp đã chọn</span>
                    <strong>{{ $selectedCinema->name }}</strong>
                    <small>{{ $selectedCinema->address ?: 'Địa chỉ đang cập nhật' }}</small>
                </div>
                <div class="selected-cinema__step"><b>1</b><span>Chọn phim</span><i>→</i><b>2</b><span>Chọn suất & ghế</span></div>
                <a href="{{ route('cinemas.index') }}#cinema-list">Đổi rạp</a>
            </section>
        @endif
        <div class="nav-tabs">
            <a href="{{ route('movies.index', array_filter(['status' => 'now_showing', 'cinema_id' => $selectedCinema?->id])) }}" class="{{ $status==='now_showing' ? 'active' : '' }}">PHIM ĐANG CHIẾU</a>
            <a href="{{ route('movies.index', array_filter(['status' => 'upcoming', 'cinema_id' => $selectedCinema?->id])) }}" class="{{ $status==='upcoming' ? 'active' : '' }}"> PHIM SẮP CHIẾU</a>
            <a href="{{ route('movies.index', array_filter(['status' => 'imax', 'cinema_id' => $selectedCinema?->id])) }}" class="{{ $status==='imax' ? 'active' : '' }}">PHIM IMAX</a>
        </div>

        <div class="grid">
            @forelse($movies as $movie)
                @php
                    $poster = $posterUrl($movie);
                    $trailer = $movie->trailer_source ?? null;
                    $movieUrl = route('movies.show', array_filter([
                        'movie' => $movie,
                        'cinema_id' => $selectedCinema?->id,
                    ])) . ($selectedCinema ? '#showtimes' : '');
                @endphp
                <div class="card">
                    <div class="movie-poster">
                        <a class="poster-link" href="{{ $movieUrl }}" title="{{ $movie->title }}">
                            <img src="{{ $poster }}" alt="{{ $movie->title }}">
                        </a>
                        <div class="overlay">
                            <a href="{{ $movieUrl }}" class="btn btn-buy">Chọn suất</a>
                            <a href="{{ $trailer ?: $movieUrl }}" class="btn btn-trailer" @if($trailer) target="_blank" rel="noopener" @endif>Trailer</a>
                        </div>
                    </div>
                    <div class="card-info">
                        <div class="title">{{ $movie->title }}</div>
                        <div class="genre">{{ $movie->genre ?: 'Đang cập nhật' }}</div>
                        <span class="status-badge
                            @if($status === 'imax') badge-imax @elseif($movie->status === 'now_showing') badge-now @else badge-upcoming @endif">
                            @if($status === 'imax') IMAX
                            @elseif($movie->status === 'now_showing') Đang chiếu
                            @else Sắp chiếu @endif
                        </span>
                    </div>
                </div>
            @empty
                <div class="movie-empty-state">
                    @if($selectedCinema)
                        <strong>Rạp này chưa có phim phù hợp</strong>
                        <p>Hiện chưa có lịch chiếu thuộc mục này tại {{ $selectedCinema->name }}.</p>
                        <a href="{{ route('cinemas.index') }}#cinema-list">Chọn rạp khác</a>
                    @elseif($search !== '')
                        <strong>Không tìm thấy phim phù hợp</strong>
                        <p>Thử tên phim ngắn hơn hoặc tìm theo thể loại.</p>
                        <a href="{{ route('movies.index', ['status' => 'all']) }}">Xem tất cả phim</a>
                    @else
                        <strong>Chưa có phim nào ở phần này, vui lòng chờ đón trong thời gian sắp tới</strong>
                        <p>TD Cinema sẽ cập nhật phim mới ngay khi có lịch phát hành.</p>
                        <a href="{{ route('movies.index', ['status' => 'all']) }}">Khám phá phim khác</a>
                    @endif
                </div>
            @endforelse
        </div>

        @if($movies->hasPages() && $movies->count())
        <div class="pagination-wrap">
            {{ $movies->links() }}
        </div>
        @endif
    </div>

    @include('frontend.partials.footer')
</div>

@include('frontend.partials.auth-modal')

<script src="{{ asset('assets/js/frontend/auth-modal.js') }}" defer></script>
@include('shared.notifications')
</body>
</html>
