@php
    use Illuminate\Support\Facades\File;
    use Illuminate\Support\Str;

    $posterUrl = fn ($movie) => $movie->posterUrl();

    // Fallback banners from public/assets/images/banner
    $bannerFallbacks = [];
    $bannerDir = public_path('assets/images/banner');
    if (File::isDirectory($bannerDir)) {
        $bannerFallbacks = collect(File::files($bannerDir))
            ->filter(fn ($f) => preg_match('/\.(png|jpe?g|webp)$/i', $f->getFilename()))
            ->map(fn ($f) => asset('assets/images/banner/' . $f->getFilename()))
            ->values()
            ->toArray();
    }
    if (empty($bannerFallbacks)) {
        $bannerFallbacks[] = asset('assets/images/banners/hero.jpg');
    }

    $bannerUrl = function ($movie) use ($posterUrl, $bannerFallbacks) {
        $fallback = $bannerFallbacks[array_rand($bannerFallbacks)];
        $path = $movie?->banner_path ?? '';
        if ($path) {
            return $movie->bannerUrl();
        }
        return $movie ? $posterUrl($movie) : $fallback;
    };


    $shortIntro = function ($movie) {
        $desc = trim($movie->description ?? '');
        if (!empty($desc)) {
            return Str::limit($desc, 120);
        }
        return ucfirst(strtolower($movie->genre ?? ''));
    };

    // SEO meta + schema prep
    $seoMovie = ($nowShowing ?? collect())->first();
    $seoTitle = 'TD Cinema - Đặt vé phim, xem lịch chiếu, trailer';
    $seoDesc = 'Đặt vé phim, xem lịch chiếu, trailer tại TD Cinema.';
    if ($seoMovie) {
        $seoTitle = ($seoMovie->title ?? 'TD Cinema') . ' | TD Cinema';
        $desc = trim($seoMovie->description ?? '');
        if (!empty($desc)) {
            $seoDesc = Str::limit($desc, 150);
        }
    }

    $orgSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'TD Cinema',
        'url' => url('/'),
        'logo' => asset('assets/images/logo-td-cinema.png'),
    ];
    $siteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'TD Cinema',
        'url' => url('/'),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => url('/search') . '?q={query}',
            'query-input' => 'required name=query',
        ],
    ];
    $movieSchema = null;
    if ($seoMovie) {
        $movieSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Movie',
            'name' => $seoMovie->title,
            'description' => $seoDesc,
            'image' => $posterUrl($seoMovie),
            'genre' => $seoMovie->genre ?? 'Phim',
        ];
        if (!empty($seoMovie->release_date)) {
            $movieSchema['datePublished'] = $seoMovie->release_date;
        }
        if (!empty($seoMovie->trailer_source)) {
            $movieSchema['trailer'] = $seoMovie->trailer_source;
        }
        if (!empty($seoMovie->rating)) {
            $movieSchema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $seoMovie->rating,
                'ratingCount' => $seoMovie->rating_count ?? 1,
            ];
        }
    }
@endphp

<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDesc }}">
    <script type="application/ld+json">
        {!! json_encode($orgSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode($siteSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}
    </script>
    @if($movieSchema)
        <script type="application/ld+json">
            {!! json_encode($movieSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif
        <link rel="stylesheet" href="{{ asset('assets/css/frontend/home.css') }}?v={{ filemtime(public_path('assets/css/frontend/home.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/cinema-corner.css') }}?v={{ filemtime(public_path('assets/css/frontend/cinema-corner.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/banner-slider.css') }}?v={{ filemtime(public_path('assets/css/frontend/banner-slider.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/promotions.css') }}?v={{ filemtime(public_path('assets/css/frontend/promotions.css')) }}">
    @php
        $navNow = \App\Models\Movie::where('status','now_showing')->orderByDesc('release_date')->take(4)->get();
        $navUpcoming = \App\Models\Movie::where('status','upcoming')->orderByDesc('release_date')->take(4)->get();
        $navImax = \App\Models\Movie::imax()->orderByDesc('release_date')->take(4)->get();
        $navPoster = fn ($movie) => $movie->posterUrl();
    @endphp
    @include('frontend.partials.header-assets')
</head>
<body data-booking-options-url="{{ route('booking.options') }}"
      data-quick-booking-save-url="{{ route('booking.quick-intent') }}"
      data-booking-checkout-url="{{ url('/booking/checkout') }}"
      data-is-customer="{{ (auth()->user()?->role ?? null) === 'customer' ? '1' : '0' }}"
      data-is-authenticated="{{ auth()->check() ? '1' : '0' }}"
      data-login-error="{{ $errors->has('email') && !session('reset_error') ? '1' : '0' }}"
      data-reset-error="{{ session('reset_error') ? '1' : '0' }}"
      data-reset-sent="{{ session('reset_sent') ? '1' : '0' }}"
      data-reset-email="{{ session('reset_email') }}"
      data-session-message="{{ session('session_expired') }}"
      data-quick-selection='@json(session('booking.quick_selection'))'
      data-movies='@json(($nowShowing ?? collect())->map(fn($m) => ['value'=>$m->id,'label'=>$m->title])->values())'>
<div class="page" id="top">
    @include('frontend.partials.header', ['searchId' => 'home-movie-search'])

    @include('frontend.partials.banner-slider')

    <div class="booking-bar">
            <div class="booking-steps">
                <div class="step-item active" id="stepMovie">
                    <div class="step-number">1</div>
                    <div class="step-labels">
                        <span class="step-title">Chọn Phim</span>
                        <div class="qb-custom" data-target="movie">
                            <div class="qb-display">
                                <span class="qb-value" id="movieValue"> Chọn phim </span>
                                <span class="qb-arrow">▾</span>
                            </div>
                            <div class="qb-panel hidden" id="moviePanel"></div>
                        </div>
                    </div>
                </div>
                <div class="step-item disabled" id="stepCinema">
                    <div class="step-number">2</div>
                    <div class="step-labels">
                        <span class="step-title">Chọn Rạp</span>
                        <div class="qb-custom qb-disabled" data-target="cinema">
                            <div class="qb-display">
                                <span class="qb-value" id="cinemaValue">-- Chọn rạp --</span>
                                <span class="qb-arrow">▾</span>
                            </div>
                            <div class="qb-panel hidden" id="cinemaPanel"></div>
                        </div>
                    </div>
                </div>
                <div class="step-item disabled" id="stepDate">
                    <div class="step-number">3</div>
                    <div class="step-labels">
                        <span class="step-title">Chọn Ngày</span>
                        <div class="qb-custom qb-disabled" data-target="date">
                            <div class="qb-display">
                                <span class="qb-value" id="dateValue">-- Chọn ngày --</span>
                                <span class="qb-arrow">▾</span>
                            </div>
                            <div class="qb-panel hidden" id="datePanel"></div>
                        </div>
                    </div>
                </div>
                <div class="step-item disabled" id="stepShowtime">
                    <div class="step-number">4</div>
                    <div class="step-labels">
                        <span class="step-title">Chọn Suất</span>
                        <div class="qb-custom qb-disabled" data-target="showtime">
                            <div class="qb-display">
                                <span class="qb-value" id="showtimeValue">-- Chọn suất --</span>
                                <span class="qb-arrow">▾</span>
                            </div>
                            <div class="qb-panel hidden" id="showtimePanel"></div>
                        </div>
                    </div>
                </div>
                <div class="quick-buy">
                    <button type="button" class="btn btn-primary" id="quickBuyBtn" disabled>Đặt vé nhanh</button>
                </div>
            </div>
        </div>

    <main class="movies" id="now">
        <div class="container">
            <nav class="tabs">
                <a href="#" class="tab-link active" data-tab="now">PHIM ĐANG CHIẾU</a>
                <a href="#" class="tab-link" data-tab="upcoming">PHIM SẮP CHIẾU</a>
                <a href="#" class="tab-link" data-tab="imax">PHIM IMAX</a>
            </nav>

            <div class="tab-content" data-tab-panel="now">
                <div class="movie-grid">
                    @foreach($nowShowing as $movie)
                        @php
                            $age = strtoupper($movie->age_rating ?? 'T13');
                            $ratingScore = $movie->rating_avg ?? null;
                        @endphp
                        <div class="movie-card">
                            <div class="movie-poster">
                                <a class="poster-link" href="{{ route('movies.show', $movie) }}" title="{{ $movie->title }}">
                                    <img src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}" loading="lazy" decoding="async">
                                </a>
                                @if($ratingScore)
                                    <div class="rating-badge">{{ number_format($ratingScore,1) }}</div>
                                @endif
                                <div class="age-badge">{{ $age }}</div>
                                <div class="overlay">
                                    <button class="btn btn-buy" data-movie-id="{{ $movie->id }}" data-movie-title="{{ $movie->title }}" data-detail="{{ route('movies.show', $movie) }}">Mua vé</button>
                                    <button class="btn btn-trailer" data-trailer="{{ $movie->trailer_source }}" data-title="{{ $movie->title }}">Trailer</button>
                                </div>
                            </div>
                            <div class="movie-card-info">
                                <h3 class="title">{{ $movie->title }}</h3>
                                <p class="genre">{{ strtolower($movie->genre) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="see-more">
                    <a class="btn btn-outline" href="{{ url('/movies?status=now_showing') }}">Xem thêm</a>
                </div>
            </div>

            <div class="tab-content" data-tab-panel="upcoming" style="display:none;">
                <div class="movie-grid">
                    @foreach($upcoming as $movie)
                        @php
                            $age = strtoupper($movie->age_rating ?? 'T13');
                        @endphp
                        <div class="movie-card">
                            <div class="movie-poster">
                                <a class="poster-link" href="{{ route('movies.show', $movie) }}" title="{{ $movie->title }}">
                                    <img src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}" loading="lazy" decoding="async">
                                </a>
                                <div class="age-badge">{{ $age }}</div>
                                <div class="overlay">
                                    <button class="btn btn-buy" data-movie-id="{{ $movie->id }}" data-movie-title="{{ $movie->title }}" data-detail="{{ route('movies.show', $movie) }}">Mua vé</button>
                                    <button class="btn btn-trailer" data-trailer="{{ $movie->trailer_source }}" data-title="{{ $movie->title }}">Trailer</button>
                                </div>
                            </div>
                            <div class="movie-card-info">
                                <h3 class="title">{{ $movie->title }}</h3>
                                <p class="genre">{{ strtolower($movie->genre) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="tab-content" data-tab-panel="imax" style="display:none;">
                <div class="movie-grid">
                    @foreach($imax as $movie)
                        @php
                            $age = strtoupper($movie->age_rating ?? 'T13');
                            $ratingScore = $movie->rating_avg ?? null;
                        @endphp
                        <div class="movie-card">
                            <div class="movie-poster">
                                <a class="poster-link" href="{{ route('movies.show', $movie) }}" title="{{ $movie->title }}">
                                    <img src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}" loading="lazy" decoding="async">
                                </a>
                                @if($ratingScore)
                                    <div class="rating-badge">{{ number_format($ratingScore,1) }}</div>
                                @endif
                                <div class="age-badge">{{ $age }}</div>
                                <div class="overlay">
                                    <button class="btn btn-buy" data-movie-id="{{ $movie->id }}" data-movie-title="{{ $movie->title }}" data-detail="{{ route('movies.show', $movie) }}">Mua vé</button>
                                    <button class="btn btn-trailer" data-trailer="{{ $movie->trailer_source }}" data-title="{{ $movie->title }}">Trailer</button>
                                </div>
                            </div>
                            <div class="movie-card-info">
                                <h3 class="title">{{ $movie->title }}</h3>
                                <p class="genre">{{ strtolower($movie->genre) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </main>

    @include('frontend.partials.cinema-corner')
    <script src="{{ asset('assets/js/frontend/cinema-corner.js') }}?v={{ filemtime(public_path('assets/js/frontend/cinema-corner.js')) }}" defer></script>

    @if($promotionDisplay->is_visible)
    <section id="promotions" class="home-offers">
        <div class="container">
            <div class="section-header promo-heading">
                <div class="section-header-left"><span class="section-kicker">{{ $promotionDisplay->eyebrow }}</span><h2 class="active" id="home-promotions-title">{{ $promotionDisplay->title }}</h2></div>
                <a href="{{ route('promotions.index') }}" class="see-all-link">Tất cả ưu đãi →</a>
            </div>
            @if($promotions->isNotEmpty())
                <div class="offer-carousel" data-offer-carousel data-mode="{{ $promotionDisplay->display_mode }}" data-autoplay="{{ $promotionDisplay->autoplay ? '1' : '0' }}" data-interval="{{ $promotionDisplay->interval_seconds * 1000 }}" role="region" aria-labelledby="home-promotions-title">
                    <div class="offer-track" id="home-offers-track" data-offer-track tabindex="0" aria-label="Danh sách khuyến mãi, dùng phím mũi tên để xem thêm">
                        @foreach ($promotions as $promo)
                            @include('frontend.partials.promotion-card')
                        @endforeach
                    </div>
                    <div class="offer-carousel-footer" data-offer-controls hidden>
                        <div class="offer-carousel-nav">
                            <span class="offer-visible-count" data-offer-count></span>
                            <button type="button" class="offer-scroll-button" data-offer-prev aria-label="Khuyến mãi trước" aria-controls="home-offers-track"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m12 5-5 5 5 5" /></svg></button>
                            <button type="button" class="offer-scroll-button" data-offer-next aria-label="Khuyến mãi tiếp theo" aria-controls="home-offers-track"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m8 5 5 5-5 5" /></svg></button>
                        </div>
                    </div>
                </div>
            @else
                <div class="offers-empty"><h3>Ưu đãi mới đang được cập nhật</h3><p>Các mã còn hiệu lực sẽ hiển thị tại đây và ở bước thanh toán.</p></div>
            @endif

        </div>
    </section>
    @endif

    <script src="{{ asset('assets/js/frontend/promotions.js') }}?v={{ filemtime(public_path('assets/js/frontend/promotions.js')) }}" defer></script>
    <section id="app-promo" class="app-promo-section" style="padding:0;">
        <div class="app-promo">
            <div class="app-image">
                <div class="app-phone" aria-label="Thông tin website TD Cinema trên điện thoại">
                    <div class="app-phone__speaker"></div>
                    <div class="app-phone__screen">
                        <div class="web-mini-brand"><img src="{{ asset('assets/images/logo/logo-transparent.png') }}" alt=""><div><strong>TD CINEMA</strong><span>Website đặt vé xem phim</span></div></div>
                        <div class="web-mini-hero">
                            <span>KHÁM PHÁ · ĐẶT VÉ · TRẢI NGHIỆM</span>
                            <h3>Rạp phim trong<br>tầm tay bạn.</h3>
                            <p>Chọn phim yêu thích và vị trí ngồi ngay trên website.</p>
                            <a href="{{ route('movies.index') }}">Khám phá phim <span aria-hidden="true">→</span></a>
                        </div>
                        <div class="web-mini-services">
                            <div><span aria-hidden="true">▷</span><strong>Phim & trailer</strong><small>Tìm phim hợp gu</small></div>
                            <div><span aria-hidden="true">◷</span><strong>Lịch chiếu</strong><small>Chọn rạp & thời gian</small></div>
                            <div><span aria-hidden="true">▦</span><strong>Chọn ghế</strong><small>Sơ đồ trực quan</small></div>
                            <div><span aria-hidden="true">☆</span><strong>Thành viên</strong><small>Vé & điểm Stars</small></div>
                        </div>
                        <div class="web-mini-about"><span>ĐỒ ÁN CHUYÊN NGÀNH</span><strong>Huỳnh Gia Khang</strong><p>Hệ thống rạp chiếu phim mô phỏng</p></div>
                    </div>
                </div>
            </div>
            <div class="app-content">
                <span class="app-kicker">Chào mừng đến với TD Cinema</span>
                <h2>Khám phá phim hay.<br>Đặt vé theo cách bạn.</h2>
                <p class="app-description">TD Cinema là website mô phỏng hệ thống rạp chiếu phim, được phát triển bởi Huỳnh Gia Khang trong đồ án chuyên ngành Công nghệ Thông tin. Bạn có thể xem trailer, tìm lịch chiếu theo rạp, chọn ghế và quản lý vé ngay trong tài khoản.</p>
                <div class="app-benefits"><span>✓ Đặt vé nhanh</span><span>✓ Chọn ghế trực quan</span><span>✓ Vé QR trong tài khoản</span></div>
                <div class="web-intro-actions">
                    <a class="web-intro-primary" href="{{ route('movies.index') }}">Khám phá phim <span aria-hidden="true">→</span></a>
                    <a class="web-intro-secondary" href="{{ route('cinemas.index') }}">Rạp & giá vé</a>
                </div>
            </div>
        </div>
    </section>

    <section class="about-section-extended">
        <div class="container">
            <div class="about-hero">
                <h2>{{ $websiteSettings->about_title }}</h2>
                <p>{{ $websiteSettings->about_subtitle }}</p>
            </div>
            <div class="about-body">@foreach(preg_split('/\n\s*\n/', $websiteSettings->about_body) as $paragraph)<p>{{ $paragraph }}</p>@endforeach</div>
        </div>
    </section>

    @include('frontend.partials.footer')

</div>

@include('frontend.partials.trailer-modal')

@include('frontend.partials.auth-modal')
<script src="{{ asset('assets/js/frontend/auth-modal.js') }}" defer></script>
<script src="{{ asset('assets/js/frontend/banner-slider.js') }}?v={{ filemtime(public_path('assets/js/frontend/banner-slider.js')) }}" defer></script>
<script src="{{ asset('assets/js/frontend/home.js') }}?v={{ filemtime(public_path('assets/js/frontend/home.js')) }}" defer></script>
@include('shared.notifications')
</body>
</html>
