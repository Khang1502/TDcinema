@php
    use Illuminate\Support\Str;
    use Carbon\Carbon;

    $posterUrl = fn ($movie) => $movie->posterUrl();

    $bannerUrl = fn ($movie) => $movie->bannerUrl();

    $ageBadge = strtoupper($movie->age_rating ?? 'T13');
    $ogTitle = $movie->title . ' | TD Cinema';
    $ogDesc = Str::limit(trim($movie->description ?? ''), 150);

    $activeDate = array_key_first($groupedShowtimes) ?? ($dateTabs[0]['key'] ?? null);
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
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDesc }}">
    <meta name="twitter:image" content="{{ $posterUrl($movie) }}">
    @php
        $navNow = \App\Models\Movie::where('status','now_showing')->orderByDesc('release_date')->take(4)->get();
        $navUpcoming = \App\Models\Movie::where('status','upcoming')->orderByDesc('release_date')->take(4)->get();
        $navImax = \App\Models\Movie::imax()->orderByDesc('release_date')->take(4)->get();
        $navPoster = fn ($movie) => $movie->posterUrl();
    @endphp
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/movie.css') }}?v={{ filemtime(public_path('assets/css/frontend/movie.css')) }}">
    @include('frontend.partials.header-assets')
</head>
<body data-cinemas-by-city='@json($cinemasByCity)'
      data-is-customer="{{ (auth()->user()?->role ?? null) === 'customer' ? '1' : '0' }}"
      data-is-authenticated="{{ auth()->check() ? '1' : '0' }}"
      data-trailer-url="{{ $movie->trailer_source ?? '' }}"
      data-login-error="{{ $errors->has('email') && !session('reset_error') ? '1' : '0' }}"
      data-reset-error="{{ session('reset_error') ? '1' : '0' }}"
      data-reset-sent="{{ session('reset_sent') ? '1' : '0' }}"
      data-reset-email="{{ session('reset_email') }}">
@include('frontend.partials.header', ['searchId' => 'detail-movie-search', 'cinemaId' => $selectedCinema?->id])
<div class="hero">
    <div class="hero-bg" style="background-image:url('{{ $bannerUrl($movie) }}')"></div>
    <div class="hero-dim"></div>
    <div class="hero-image">
        <img src="{{ $bannerUrl($movie) }}" alt="{{ $movie->title }}">
    </div>
    @if(!empty($movie->trailer_url))
    <div class="play-btn"><button id="playTrailer">▶</button></div>
    @endif
</div>

<div class="page">
    <div class="content">
        <div class="main-card">
            <div class="movie-header">
                <div class="poster"><img src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}"></div>
                <div>
                    <div class="movie-title">{{ $movie->title }} <span class="age">{{ $ageBadge }}</span></div>
                    <div class="meta">
                        @if($movie->director)<div><strong>Đạo diễn:</strong> {{ $movie->director }}</div>@endif
                        @if($movie->actors)<div><strong>Diễn viên:</strong> {{ $movie->actors }}</div>@endif
                        @if($movie->genre)<div><strong>Thể loại:</strong> {{ $movie->genre }}</div>@endif
                        @if($movie->release_date)<div><strong>Khởi chiếu:</strong> {{ Carbon::parse($movie->release_date)->format('d/m/Y') }}</div>@endif
                        @if($movie->duration)<div><strong>Thời lượng:</strong> {{ $movie->duration }} phút</div>@endif
                    </div>
                </div>
            </div>

            <div class="section" id="synopsis">
                <h3>Nội Dung Phim</h3>
                <div class="description">{{ $movie->description ?? 'Chưa có nội dung.' }}</div>
            </div>

            <div class="section" id="showtimes">
                <div class="schedule-heading">
                    <div>
                        <span class="schedule-eyebrow">ĐẶT VÉ TRỰC TUYẾN</span>
                        <h3>Lịch chiếu</h3>
                    </div>
                    <p>{{ $selectedCinema ? 'Chọn ngày và suất chiếu tại rạp đã chọn để sang bước chọn ghế.' : 'Chọn ngày, khu vực và suất chiếu phù hợp với bạn.' }}</p>
                </div>
                <div class="showtimes schedule-shell">
                    <div class="date-tabs-wrap schedule-controls">
                        <button class="date-nav" id="prevDate" aria-label="Ngày trước">‹</button>
                        <div class="date-tabs">
                            @foreach($dateTabs as $tab)
                                <button class="date-tab {{ $tab['key'] === $activeDate ? 'active' : '' }}" data-date="{{ $tab['key'] }}" aria-pressed="{{ $tab['key'] === $activeDate ? 'true' : 'false' }}">
                                    @if($loop->first)
                                        <small>Hôm nay</small>
                                    @endif
                                    <strong>{{ $tab['label'] }}</strong>
                                </button>
                            @endforeach
                        </div>
                        <button class="date-nav" id="nextDate" aria-label="Ngày tiếp">›</button>
                    </div>
                    @if($selectedCinema)
                        <div class="locked-cinema">
                            <span class="locked-cinema__pin" aria-hidden="true">⌖</span>
                            <div><small>Rạp đã chọn</small><strong>{{ $selectedCinema->name }}</strong><p>{{ $selectedCinema->address ?: 'Địa chỉ đang cập nhật' }}</p></div>
                            <a href="{{ route('cinemas.index') }}#cinema-list">Đổi rạp</a>
                        </div>
                    @else
                        <div class="filter-bar schedule-filters">
                            <label>
                                <span>Khu vực</span>
                                <select id="cityFilter">
                                    <option value="">Toàn quốc</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city }}">{{ $city }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Rạp chiếu</span>
                                <select id="cinemaFilter">
                                    <option value="">Tất cả rạp</option>
                                    @foreach($cinemasByCity->flatten(1) as $cinema)
                                        <option value="{{ $cinema['id'] }}">{{ $cinema['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @endif

                    <div id="noShowtimeMsg" class="empty" style="{{ empty($groupedShowtimes) ? '' : 'display:none;' }}">Chưa có lịch chiếu cho phim này.</div>

                    @foreach($groupedShowtimes as $dateKey => $cinemas)
                        <div class="day-panel" data-date-panel="{{ $dateKey }}" style="{{ $dateKey === $activeDate ? '' : 'display:none;' }}">
                            @foreach($cinemas as $cinemaBlock)
                                <div class="cinema-block" data-cinema-id="{{ $cinemaBlock['cinema']->id ?? '' }}" data-city="{{ $cinemaBlock['cinema']->city ?? '' }}">
                                    <div class="cinema-card-head">
                                        <span class="cinema-pin" aria-hidden="true">⌖</span>
                                        <div><div class="cinema-name">{{ $cinemaBlock['cinema']->name ?? 'Rạp' }}</div><div class="cinema-address">{{ $cinemaBlock['cinema']->address ?? 'Thông tin địa chỉ đang cập nhật' }}</div></div>
                                    </div>
                                    <div class="showtime-line">
                                        @foreach($cinemaBlock['showtimes'] as $st)
                                            <a class="showtime-pill" href="{{ route('booking.checkout', $st['id']) }}"><strong>{{ $st['time'] }}</strong><small>{{ $st['room'] ?: 'Phòng chiếu' }}</small></a>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <aside class="sidebar">
            <h4>Phim đang chiếu</h4>
            <div class="side-list">
                @forelse($sidebarMovies as $side)
                    @php $rating = $side->rating_avg ?? null; @endphp
                    <div class="side-card">
                        <div class="side-img-wrap">
                            <a href="{{ route('movies.show', array_filter(['movie' => $side, 'cinema_id' => $selectedCinema?->id])) }}{{ $selectedCinema ? '#showtimes' : '' }}" title="{{ $side->title }}">
                                <img class="side-large-thumb" src="{{ $posterUrl($side) }}" alt="{{ $side->title }}">
                                <div class="side-overlay">
                                    <span class="side-buy">Mua vé</span>
                                </div>
                                @if($rating)
                                    <div class="side-rating">★ {{ number_format($rating,1) }}</div>
                                @endif
                                <div class="side-badge">{{ strtoupper($side->age_rating ?? 'T13') }}</div>
                            </a>
                        </div>
                        <div class="side-info">
                            <div class="side-title">{{ Str::limit($side->title, 40) }}</div>
                            <div class="side-genre">{{ $side->genre }}</div>
                        </div>
                    </div>
                @empty
                    <div class="empty">Chưa có phim khác.</div>
                @endforelse
            </div>
            <div class="side-more"><a href="{{ route('movies.index', array_filter(['status' => 'now_showing', 'cinema_id' => $selectedCinema?->id])) }}">Xem thêm</a></div>
        </aside>
    </div>
</div>

@include('frontend.partials.footer')
@include('frontend.partials.auth-modal')
@include('frontend.partials.trailer-modal')

<script src="{{ asset('assets/js/frontend/auth-modal.js') }}" defer></script>
<script src="{{ asset('assets/js/frontend/movie.js') }}?v={{ filemtime(public_path('assets/js/frontend/movie.js')) }}" defer></script>
@include('shared.notifications')
</body>
</html>
