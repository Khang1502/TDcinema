@php
    use Illuminate\Support\Str;
    use Carbon\Carbon;

    $posterUrl = function ($movie) {
        $path = $movie->poster_path ?? '';
        if (empty($path)) {
            return 'https://via.placeholder.com/360x540?text=Poster';
        }
        return preg_match('/^https?:\/\//', $path)
            ? $path
            : asset('storage/' . ltrim($path, '/'));
    };

    $bannerUrl = function ($movie) use ($posterUrl) {
        $path = $movie->banner_path ?? '';
        if ($path) {
            return preg_match('/^https?:\/\//', $path)
                ? $path
                : asset('storage/' . ltrim($path, '/'));
        }
        return $posterUrl($movie);
    };

    $ageBadge = strtoupper($movie->age_rating ?? 'T13');
    $ogTitle = $movie->title . ' | TD Cinema';
    $ogDesc = Str::limit(trim($movie->description ?? ''), 150);

    $activeDate = array_key_first($groupedShowtimes) ?? ($dateTabs[0]['key'] ?? null);
@endphp

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        $navImax = \App\Models\Movie::where('status','imax')->orderByDesc('release_date')->take(4)->get();
        $navPoster = function($m){
            $p = $m->poster_path ?? '';
            if(empty($p)) return 'https://via.placeholder.com/140x200?text=TD';
            return preg_match('/^https?:\\/\\//',$p) ? $p : asset('storage/'.ltrim($p,'/'));
        };
    @endphp
    <style>
        :root {
            --primary: #359eff;
            --accent: #f97316;
            --text-dark: #0f172a;
            --muted: #6b7280;
            --bg: #f5f7fb;
            --radius: 12px;
        }
        * { box-sizing:border-box; margin:0; padding:0; }
        @import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap');
        body { font-family: 'Be Vietnam Pro',sans-serif; background:#fff; color:var(--text-dark); line-height:1.5; }
        a { color:inherit; text-decoration:none; }
        img { max-width:100%; display:block; }
        /* Header */
        .site-header { background:#ffffff; padding:10px 0; border-bottom:1px solid #e5e7eb; }
        .header-container { width:100%; max-width:1200px; margin:0 auto; padding:0 24px; display:flex; align-items:center; justify-content:space-between; }
        .header-left { display:flex; align-items:center; gap:32px; }
        .logo { display:flex; align-items:center; gap:8px; font-weight:800; color:#0b2c3d; text-transform:uppercase; letter-spacing:0.3px; }
        .logo img { width:40px; height:40px; object-fit:cover; }
        .main-nav ul { display:flex; align-items:center; gap:20px; list-style:none; margin:0; padding:0; }
        .main-nav a { font-size:14px; font-weight:600; color:#4b5563; padding:6px 0; display:inline-flex; align-items:center; gap:4px; }
        .main-nav a::after { content:'▾'; font-size:10px; color:#9ca3af; }
        .main-nav a.no-caret::after { display:none; }
        /* Mega menu */
        .has-mega { position:relative; }
        .mega { position:absolute; left:0; top:54px; background:#fff; box-shadow:0 12px 32px rgba(0,0,0,.12); border-radius:14px; padding:16px; display:none; z-index:50; width:920px; max-width:95vw; }
        .has-mega:hover .mega { display:block; }
        .mega h4 { margin:0 0 10px; font-size:14px; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:.2px; }
        .mega-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
        .mega-col { border-right:1px solid #e5e7eb; padding-right:12px; }
        .mega-col:last-child { border-right:none; padding-right:0; }
        .mega-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; }
        .mega-item { display:flex; gap:8px; text-decoration:none; color:#0f172a; }
        .mega-thumb { width:70px; height:96px; border-radius:8px; overflow:hidden; background:#f1f5f9; flex-shrink:0; }
        .mega-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .mega-info { display:flex; flex-direction:column; gap:4px; }
        .mega-title { font-size:13px; font-weight:700; line-height:1.3; color:#0f172a; }
        .mega-meta { font-size:12px; color:#6b7280; }
        .mega-rating { display:inline-flex; align-items:center; gap:4px; font-size:12px; color:#f59e0b; }
        /* Mega menu */
        .has-mega { position:relative; }
        .mega { position:absolute; left:0; top:54px; background:#fff; box-shadow:0 12px 32px rgba(0,0,0,.12); border-radius:14px; padding:16px; display:none; z-index:50; width:920px; max-width:95vw; }
        .has-mega:hover .mega { display:block; }
        .mega h4 { margin:0 0 10px; font-size:14px; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:.2px; }
        .mega-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
        .mega-col { border-right:1px solid #e5e7eb; padding-right:12px; }
        .mega-col:last-child { border-right:none; padding-right:0; }
        .mega-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; }
        .mega-item { display:flex; gap:8px; text-decoration:none; color:#0f172a; }
        .mega-thumb { width:70px; height:96px; border-radius:8px; overflow:hidden; background:#f1f5f9; flex-shrink:0; }
        .mega-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .mega-info { display:flex; flex-direction:column; gap:4px; }
        .mega-title { font-size:13px; font-weight:700; line-height:1.3; color:#0f172a; }
        .mega-meta { font-size:12px; color:#6b7280; }
        .mega-rating { display:inline-flex; align-items:center; gap:4px; font-size:12px; color:#f59e0b; }
        .nav-buy-img img { height:42px; width:auto; display:block; }
        .user-info { display:flex; align-items:center; gap:10px; padding:6px 10px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; position:relative; cursor:pointer; }
        .user-avatar { width:34px; height:34px; border-radius:50%; background:#e5e7eb; display:grid; place-items:center; font-weight:700; color:#4b5563; overflow:hidden; }
        .user-avatar img { width:100%; height:100%; object-fit:cover; }
        .user-meta { display:flex; flex-direction:column; gap:2px; line-height:1.2; }
        .user-name { font-weight:700; color:#1f2937; }
        .user-tier { font-size:12px; color:#6b7280; display:flex; gap:6px; align-items:center; }
        .user-menu { position:absolute; right:0; top:115%; background:#fff; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.12); padding:10px 0; min-width:180px; display:none; z-index:40; }
        .user-menu a, .user-menu button { display:block; padding:8px 14px; color:#374151; font-size:14px; text-align:left; background:transparent; border:none; width:100%; cursor:pointer; }
        .user-menu a:hover, .user-menu button:hover { background:#f3f4f6; }
        .join-wrapper { position:relative; }
        .join-popover { position:absolute; right:0; top:110%; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.12); padding:16px; width:900px; max-width:95vw; display:none; z-index:40; }
        .join-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
        .join-card { text-align:center; border:1px solid #e5e7eb; border-radius:10px; padding:12px; background:#fff; }
        .join-card img { height:80px; margin:0 auto 10px; object-fit:contain; }
        .join-card h4 { font-size:14px; font-weight:700; color:#1f2937; margin-bottom:6px; }
        .join-card p { font-size:13px; color:#6b7280; margin-bottom:8px; }
        .join-card .btn { padding:8px 12px; }
        .header-right { display:flex; align-items:center; gap:16px; font-size:14px; }
        .header-search-btn { border:none; background:transparent; cursor:pointer; font-size:22px; line-height:1; color:#6b7280; display:flex; align-items:center; justify-content:center; }
        .header-login { font-weight:600; color:#4b5563; cursor:pointer; }
        .header-join-img img { height:38px; width:auto; display:block; object-fit:contain; }

        .page { max-width:1280px; margin:0 auto; padding:0 24px 48px; }
        .hero { position:relative; width:100%; height:320px; background:#000; border-radius:0; overflow:hidden; }
        .hero::before { display:none; }
        .hero-bg { position:absolute; inset:0; background-size:auto 100%; background-position:center; background-repeat:no-repeat; filter:brightness(0.98); }
        .hero-dim { position:absolute; inset:0; background:rgba(0,0,0,.22); z-index:1; }
        .hero-image { position:absolute; inset:0; z-index:2; display:flex; align-items:center; justify-content:center; }
        .hero-image img { height:100%; width:auto; object-fit:contain; display:block; background:#000; }
        .play-btn { position:absolute; inset:0; display:grid; place-items:center; z-index:3; }
        .play-btn button { width:80px; height:80px; border-radius:50%; border:3px solid rgba(255,255,255,.9); background:rgba(0,0,0,.2); color:#fff; font-size:28px; cursor:pointer; box-shadow:0 12px 30px rgba(0,0,0,.35); }

        .content { display:grid; grid-template-columns:2fr 0.9fr; gap:28px; margin-top:24px; position:relative; z-index:3; align-items:start; }
        .main-card { background:transparent; border-radius:0; box-shadow:none; padding:0; }
        .sidebar { background:transparent; border-radius:0; box-shadow:none; padding:0; }

        .movie-header { display:grid; grid-template-columns:190px 1fr; gap:22px; align-items:flex-start; }
        .poster { border-radius:6px; overflow:hidden; box-shadow:0 6px 16px rgba(0,0,0,.12); border:1px solid #e5e7eb; }
        .movie-title { font-size:26px; font-weight:800; display:flex; align-items:center; gap:10px; margin-bottom:10px; }
        .age { background:#f97316; color:#fff; border-radius:6px; padding:2px 8px; font-size:12px; font-weight:800; }
        .meta { display:grid; gap:6px; color:#4b5563; font-size:15px; }
        .meta strong { color:#111; font-weight:700; margin-right:4px; }
        .actions { display:flex; gap:10px; margin-top:10px; flex-wrap:wrap; }
        .btn { border:none; border-radius:8px; padding:10px 14px; font-weight:700; cursor:pointer; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-outline { background:#fff; border:1px solid #cbd5e1; color:#0f172a; }

        .section { margin-top:24px; }
        .section h3 { font-size:18px; font-weight:800; margin-bottom:10px; border-left:4px solid var(--primary); padding-left:8px; }
        .description { color:#374151; font-size:15px; line-height:1.7; }

        .showtimes { margin-top:12px; }
        .date-tabs-wrap { display:flex; align-items:center; gap:8px; }
        .date-tabs { display:flex; gap:10px; flex-wrap:wrap; }
        .date-tab { padding:10px 14px; border-radius:6px; background:#f9fafb; border:1px solid #e2e8f0; cursor:pointer; font-weight:700; font-size:13px; color:#0f172a; min-width:96px; text-align:center; }
        .date-tab.active { background:#0b5394; border-color:#0b5394; color:#fff; }
        .date-nav { border:none; background:transparent; cursor:pointer; font-size:18px; color:#374151; padding:6px; }
        .filter-bar { display:flex; gap:10px; flex-wrap:wrap; margin:12px 0; }
        .filter-bar select { padding:10px 12px; border-radius:6px; border:1px solid #e2e8f0; font-size:14px; background:#fff; }
        .cinema-block { padding:12px 0; border-top:1px solid #e5e7eb; }
        .cinema-name { font-weight:800; color:#0f172a; margin-bottom:6px; }
        .showtime-line { display:flex; gap:8px; flex-wrap:wrap; margin-top:6px; }
        .showtime-pill { padding:10px 12px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; font-weight:700; font-size:13px; color:#0f172a; text-decoration:none; box-shadow:0 1px 4px rgba(0,0,0,.05); transition:all .15s; }
        .showtime-pill:hover { border-color:var(--primary); color:#0b5394; box-shadow:0 6px 12px rgba(0,0,0,.08); }

        .sidebar h4 { font-size:17px; font-weight:800; margin-bottom:14px; text-transform:uppercase; letter-spacing:0.3px; }
        .side-list { display:flex; flex-direction:column; gap:16px; }
        .side-card { display:block; }
        .side-img-wrap { position:relative; border-radius:6px; overflow:hidden; aspect-ratio:16 / 9; background:#0b0b0b; }
        .side-large-thumb { width:100%; height:100%; object-fit:contain; display:block; background:#0b0b0b; }
        .side-overlay { position:absolute; inset:0; background:rgba(0,0,0,.35); opacity:0; transition:opacity .2s; display:flex; align-items:center; justify-content:center; }
        .side-img-wrap:hover .side-overlay { opacity:1; }
        .side-buy { background:#f26b38; color:#fff; border:none; border-radius:6px; padding:10px 14px; font-weight:700; cursor:pointer; text-decoration:none; box-shadow:0 8px 18px rgba(0,0,0,.18); }
        .side-badge { position:absolute; right:8px; bottom:8px; background:#f97316; color:#fff; border-radius:6px; padding:4px 6px; font-weight:800; font-size:12px; }
        .side-rating { position:absolute; right:8px; bottom:34px; background:rgba(0,0,0,.6); color:#fff; border-radius:4px; padding:2px 6px; font-weight:800; font-size:12px; display:flex; align-items:center; gap:4px; }
        .side-info { display:flex; flex-direction:column; gap:4px; min-width:0; margin-top:8px; }
        .side-title { font-weight:800; font-size:15px; color:#0f172a; line-height:1.3; }
        .side-genre { font-size:13px; color:#6b7280; }
        .side-more { margin-top:4px; text-align:right; }
        .side-more a { color:#f26b38; font-weight:700; font-size:13px; }

        .empty { padding:16px; border:1px dashed #cbd5e1; border-radius:8px; color:#6b7280; background:#f8fafc; }

        /* Auth modal */
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:95; padding:16px; }
        .modal-overlay.active { display:flex; }
        .modal-box { width:440px; max-width:90vw; background:#fff; border-radius:12px; box-shadow:0 12px 32px rgba(0,0,0,.18); padding:24px 28px; position:relative; }
        .modal-header { text-align:center; margin-bottom:16px; }
        .modal-title { font-size:22px; font-weight:800; color:#111827; margin-bottom:6px; }
        .modal-sub { color:#6b7280; font-size:14px; }
        .auth-form { display:flex; flex-direction:column; gap:12px; margin-top:12px; }
        .auth-form label { font-size:13px; font-weight:600; color:#374151; }
        .auth-form input { width:100%; padding:12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; }
        .auth-form input:focus { outline:none; border-color:#359eff; box-shadow:0 0 0 2px rgba(53,158,255,.15); }
        .auth-actions { display:flex; justify-content:space-between; align-items:center; font-size:13px; }
        .auth-link { color:#359eff; font-weight:600; cursor:pointer; }
        .auth-submit { width:100%; }
        .modal-close { position:absolute; top:12px; right:12px; border:none; background:transparent; font-size:18px; cursor:pointer; color:#6b7280; }
        .hidden { display:none; }

        /* Trailer modal reuse */
        .trailer-overlay { position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,.78); z-index:90; padding:20px; }
        .trailer-overlay.active { display:flex; }
        .trailer-box { width:min(960px,94vw); background:#000; border-radius:12px; overflow:hidden; position:relative; box-shadow:0 20px 50px rgba(0,0,0,.4); }
        .trailer-frame { width:100%; aspect-ratio:16 / 9; border:none; display:block; background:#000; }
        .trailer-close { position:absolute; top:10px; right:10px; width:34px; height:34px; border:none; border-radius:50%; background:rgba(255,255,255,.16); color:#fff; font-size:18px; cursor:pointer; display:grid; place-items:center; }
        .trailer-close:hover { background:rgba(255,255,255,.28); }

        /* Footer */
        .footer { background:#232633; color:#fff; padding:40px 0 24px; font-size:14px; margin-top:40px; }
        .footer-inner { max-width:1200px; margin:0 auto; display:grid; grid-template-columns: minmax(0,2.2fr) repeat(3,minmax(0,1fr)); gap:32px; align-items:flex-start; padding:0 16px; }
        .footer-brand { display:flex; align-items:flex-start; gap:16px; }
        .footer-logo img { width:110px; height:auto; display:block; }
        .footer-company-name { font-weight:700; margin-bottom:6px; }
        .footer-company p { margin:0; color:rgba(255,255,255,.7); }
        .footer h4 { text-transform:uppercase; font-size:13px; margin-bottom:12px; font-weight:700; }
        .footer-col a { display:block; margin-bottom:8px; color:rgba(255,255,255,.7); text-decoration:none; }
        .footer-col a:hover { color:#fff; text-decoration:underline; }
        .footer-contact-item { display:flex; align-items:center; margin-bottom:8px; color:rgba(255,255,255,.75); }
        .footer-icon { width:18px; height:18px; margin-right:10px; flex-shrink:0; }
        .footer-social { margin-top:10px; display:flex; gap:12px; }
        .footer-social-icon { width:26px; height:26px; display:block; opacity:.85; transition:.2s; }
        .footer-social-icon:hover { opacity:1; transform:scale(1.08); }
        .footer-bottom { border-top:1px solid rgba(255,255,255,.1); margin-top:30px; padding-top:16px; text-align:center; font-size:12px; color:rgba(255,255,255,.5); }

        @media(max-width:992px){
            .content{grid-template-columns:1fr;}
            .movie-header{grid-template-columns:120px 1fr;}
            .footer-inner{grid-template-columns:1fr 1fr;}
        }
        @media(max-width:640px){
            .hero-image{padding:0 6px;}
            .movie-header{grid-template-columns:1fr;}
            .poster{width:120px;}
            .actions{flex-wrap:wrap;}
            .filter-bar{flex-direction:column;}
            .filter-bar select{width:100%;}
            .footer-inner{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="header-container">
        <div class="header-left">
            <a href="{{ route('home') }}" class="logo">
                <img src="{{ asset('assets/images/logo/logo.jpg') }}" alt="TD Cinema">
                <span>TD Cinema</span>
            </a>
            <nav class="main-nav">
                <ul>
                    <li><a href="#" class="nav-buy-img no-caret"><img src="{{ asset('assets/images/ui/buyticket.png') }}" alt="Mua vé"></a></li>
                    <li class="has-mega">
                        <a href="{{ route('movies.index') }}">Phim</a>
                        <div class="mega">
                            <div class="mega-grid">
                                <div class="mega-col">
                                    <h4>Phim đang chiếu</h4>
                                    <div class="mega-list">
                                        @forelse($navNow as $m)
                                            <a class="mega-item" href="{{ route('movies.show', $m) }}">
                                                <div class="mega-thumb"><img src="{{ $navPoster($m) }}" alt="{{ $m->title }}"></div>
                                                <div class="mega-info">
                                                    <div class="mega-title">{{ $m->title }}</div>
                                                    <div class="mega-meta">{{ $m->genre ?? 'Đang cập nhật' }}</div>
                                                    @if($m->rating_avg)
                                                        <div class="mega-rating">★ {{ number_format($m->rating_avg,1) }}</div>
                                                    @endif
                                                </div>
                                            </a>
                                        @empty
                                            <span class="mega-meta">Chưa có phim</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div class="mega-col">
                                    <h4>Phim sắp chiếu</h4>
                                    <div class="mega-list">
                                        @forelse($navUpcoming as $m)
                                            <a class="mega-item" href="{{ route('movies.show', $m) }}">
                                                <div class="mega-thumb"><img src="{{ $navPoster($m) }}" alt="{{ $m->title }}"></div>
                                                <div class="mega-info">
                                                    <div class="mega-title">{{ $m->title }}</div>
                                                    <div class="mega-meta">{{ $m->genre ?? 'Đang cập nhật' }}</div>
                                                    @if($m->rating_avg)
                                                        <div class="mega-rating">★ {{ number_format($m->rating_avg,1) }}</div>
                                                    @endif
                                                </div>
                                            </a>
                                        @empty
                                            <span class="mega-meta">Chưa có phim</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div class="mega-col">
                                    <h4>Phim IMAX</h4>
                                    <div class="mega-list">
        @forelse($navImax as $m)
                                            <a class="mega-item" href="{{ route('movies.show', $m) }}">
                                                <div class="mega-thumb"><img src="{{ $navPoster($m) }}" alt="{{ $m->title }}"></div>
                                                <div class="mega-info">
                                                    <div class="mega-title">{{ $m->title }}</div>
                                                    <div class="mega-meta">{{ $m->genre ?? 'Đang cập nhật' }}</div>
                                                    @if($m->rating_avg)
                                                        <div class="mega-rating">★ {{ number_format($m->rating_avg,1) }}</div>
                                                    @endif
                                                </div>
                                            </a>
                                        @empty
                                            <span class="mega-meta">Chưa có phim</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li><a href="{{ route('home') }}#cinema-corner">Star Shop</a></li>
                    <li><a href="{{ route('home') }}#cinema-corner">Góc Điện Ảnh</a></li>
                    <li><a href="{{ route('home') }}#promotions">Sự kiện</a></li>
                    <li><a href="{{ route('home') }}#promotions">Rạp/Giá vé</a></li>
                </ul>
            </nav>
        </div>
        <div class="header-right">
            <button class="header-search-btn" aria-label="Search">🔍</button>
            @auth
                <div class="user-info" id="userMenuToggle">
                    <div class="user-avatar">
                        @if(auth()->user()->avatar ?? false)
                            <img src="{{ asset('storage/' . ltrim(auth()->user()->avatar, '/')) }}" alt="Avatar">
                        @else
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        @endif
                    </div>
                    <div class="user-meta">
                        <span class="user-name">{{ auth()->user()->name ?? 'User' }}</span>
                        <span class="user-tier">
                            <span>Star</span>
                            <span>·</span>
                            <span>{{ auth()->user()->points ?? 0 }} Stars</span>
                        </span>
                    </div>
                    <div class="user-menu" id="userMenu">
                        <a href="#">Tài khoản</a>
                        <a href="#">Lịch sử vé</a>
                        <a href="#">Đổi điểm/Stars</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit">Đăng xuất</button>
                        </form>
                    </div>
                </div>
            @else
                <span class="header-login" id="openLogin">Đăng nhập</span>
                <div class="join-wrapper" id="joinWrapper">
                    <a href="#" class="header-join-img" id="joinToggle">
                        <img src="{{ asset('assets/images/membership/ThamGiaCVIP.jpg') }}" alt="Tham gia" onerror="this.src='https://via.placeholder.com/90x38?text=Join'">
                    </a>
                    <div class="join-popover" id="joinPopover">
                        <div class="join-grid">
                            <div class="join-card">
                                <img src="{{ asset('assets/images/membership/rule.png') }}" alt="Thể lệ" onerror="this.src='https://via.placeholder.com/80?text=Rule'">
                                <h4>Thể lệ</h4>
                                <p>Xem quy định tham gia</p>
                                <button class="btn btn-outline">Chi tiết</button>
                            </div>
                            <div class="join-card">
                                <img src="{{ asset('assets/images/membership/benefit.png') }}" alt="Quyền lợi" onerror="this.src='https://via.placeholder.com/80?text=Benefit'">
                                <h4>Quyền lợi</h4>
                                <p>Ưu đãi hội viên</p>
                                <button class="btn btn-outline">Chi tiết</button>
                            </div>
                            <div class="join-card">
                                <img src="{{ asset('assets/images/membership/guide.png') }}" alt="Hướng dẫn" onerror="this.src='https://via.placeholder.com/80?text=Guide'">
                                <h4>Hướng dẫn</h4>
                                <p>Cách sử dụng điểm</p>
                                <button class="btn btn-outline">Chi tiết</button>
                            </div>
                            <div class="join-card">
                                <img src="{{ asset('assets/images/membership/join.png') }}" alt="Đăng ký" onerror="this.src='https://via.placeholder.com/80?text=Join'">
                                <h4>Đăng ký thành viên</h4>
                                <p>Nhận ưu đãi ngay</p>
                                <button class="btn btn-primary" id="openRegister">Đăng ký</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endauth
        </div>
    </div>
</header>
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
                <h3>Lịch Chiếu</h3>
                <div class="showtimes">
                    <div class="date-tabs-wrap">
                        <button class="date-nav" id="prevDate" aria-label="Ngày trước">‹</button>
                        <div class="date-tabs">
                            @foreach($dateTabs as $tab)
                                <button class="date-tab {{ $tab['key'] === $activeDate ? 'active' : '' }}" data-date="{{ $tab['key'] }}">
                                    @if($loop->first)
                                        <span style="display:block;font-size:12px;">Hôm nay</span>
                                    @endif
                                    <span>{{ $tab['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                        <button class="date-nav" id="nextDate" aria-label="Ngày tiếp">›</button>
                        <div class="filter-bar" style="margin-left:auto;">
                            <select id="cityFilter">
                                <option value="">Toàn quốc</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city }}">{{ $city }}</option>
                                @endforeach
                            </select>
                            <select id="cinemaFilter">
                                <option value="">Tất cả rạp</option>
                                @foreach($cinemasByCity->flatten(1) as $cinema)
                                    <option value="{{ $cinema['id'] }}">{{ $cinema['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="noShowtimeMsg" class="empty" style="{{ empty($groupedShowtimes) ? '' : 'display:none;' }}">Chưa có lịch chiếu cho phim này.</div>

                    @foreach($groupedShowtimes as $dateKey => $cinemas)
                        <div class="day-panel" data-date-panel="{{ $dateKey }}" style="{{ $dateKey === $activeDate ? '' : 'display:none;' }}">
                            @foreach($cinemas as $cinemaBlock)
                                <div class="cinema-block" data-cinema-id="{{ $cinemaBlock['cinema']->id ?? '' }}" data-city="{{ $cinemaBlock['cinema']->city ?? '' }}">
                                    <div class="cinema-name">{{ $cinemaBlock['cinema']->name ?? 'Rạp' }}</div>
                                    <div class="showtime-line">
                                        @foreach($cinemaBlock['showtimes'] as $st)
                                            <a class="showtime-pill" href="{{ route('booking.checkout', $st['id']) }}">{{ $st['time'] }}</a>
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
                            <a href="{{ route('movies.show', $side) }}" title="{{ $side->title }}">
                                <img class="side-large-thumb" src="{{ $posterUrl($side) }}" alt="{{ $side->title }}">
                                <div class="side-overlay">
                                    <span class="side-buy">Mua vé</span>
                                </div>
                                @if($rating)
                                    <div class="side-rating">★ {{ number_format($rating,1) }}</div>
                                @endif
                                <div class="side-badge">{{ strtoupper($side->age_rating ?? 'C13') }}</div>
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
            <div class="side-more"><a href="#">Xem thêm</a></div>
        </aside>
    </div>
</div>

<!-- Auth Modals -->
<div class="modal-overlay" id="authOverlay">
    <div class="modal-box" id="loginBox">
        <button class="modal-close" data-close>×</button>
        <div class="modal-header">
            <div class="modal-title">Chào mừng trở lại!</div>
            <div class="modal-sub">Đăng nhập để tiếp tục trải nghiệm của bạn.</div>
        </div>
        <form class="auth-form" method="POST" action="{{ route('login') }}">
            @csrf
            <div>
                <label>Email hoặc Tên đăng nhập</label>
                <input type="text" name="email" placeholder="Nhập email hoặc tên đăng nhập">
            </div>
            <div>
                <label>Mật khẩu</label>
                <input type="password" name="password" placeholder="Nhập mật khẩu của bạn">
            </div>
            <div class="auth-actions">
                <span class="auth-link" id="openForgot">Quên mật khẩu?</span>
            </div>
            <button type="submit" class="btn btn-primary auth-submit">Đăng nhập</button>
            <div class="auth-actions" style="justify-content:center; gap:4px; margin-top:8px;">
                <span>Chưa có tài khoản?</span>
                <span class="auth-link" id="switchRegister">Đăng ký ngay</span>
            </div>
        </form>
    </div>

    <div class="modal-box hidden" id="registerBox">
        <button class="modal-close" data-close>×</button>
        <div class="modal-header">
            <div class="modal-title">Tạo tài khoản mới</div>
            <div class="modal-sub">Bắt đầu hành trình điện ảnh của bạn hôm nay.</div>
        </div>
        <form class="auth-form" method="POST" action="{{ route('register') }}">
            @csrf
            <div>
                <label>Họ và tên</label>
                <input type="text" name="name" placeholder="Nguyễn Văn A">
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" placeholder="nguyenvana@email.com">
            </div>
            <div>
                <label>Số điện thoại</label>
                <input type="text" name="phone" placeholder="0901234567">
            </div>
            <div>
                <label>Mật khẩu</label>
                <input type="password" name="password" placeholder="Nhập mật khẩu của bạn">
            </div>
            <div>
                <label>Xác nhận mật khẩu</label>
                <input type="password" name="password_confirmation" placeholder="Nhập lại mật khẩu">
            </div>
            <button type="submit" class="btn btn-primary auth-submit">Đăng ký</button>
            <div class="auth-actions" style="justify-content:center; gap:4px; margin-top:8px;">
                <span>Đã có tài khoản?</span>
                <span class="auth-link" id="switchLogin">Đăng nhập ngay</span>
            </div>
        </form>
    </div>

    <div class="modal-box hidden" id="forgotBox">
        <button class="modal-close" data-close>×</button>
        <div class="modal-header" id="forgotHeader">
            <div class="modal-title">Quên mật khẩu</div>
            <div class="modal-sub">Nhập email để nhận mã đặt lại mật khẩu.</div>
        </div>
        <div id="forgotFormWrap">
            <form class="auth-form" method="POST" action="{{ route('password.email') }}">
                @csrf
                <div>
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com">
                </div>
                <button type="submit" class="btn btn-primary auth-submit">Gửi mã</button>
                <div class="auth-actions" style="justify-content:center; gap:4px; margin-top:8px;">
                    <span class="auth-link" id="backLogin">Quay lại đăng nhập</span>
                </div>
            </form>
        </div>
    </div>
</div>

@if(!empty($movie->trailer_url))
<div class="trailer-overlay" id="trailerOverlay">
    <div class="trailer-box">
        <button class="trailer-close" id="closeTrailer" aria-label="Đóng trailer">×</button>
        <iframe id="trailerFrame" class="trailer-frame" src="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
</div>
@endif

<footer class="footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <div class="footer-logo">
                <img src="{{ asset('assets/images/logo/logo.jpg') }}" alt="TD Cinema" />
            </div>
            <div class="footer-company">
                <p class="footer-company-name">CÔNG TY CỔ PHẦN PHIM ĐẠI TRƯỜNG</p>
                <p>180 Cao Lỗ, phường Chánh Hưng, TP.HCM</p>
            </div>
        </div>

        <div class="footer-col">
            <h4>GÓC ĐIỆN ẢNH</h4>
            <a href="#">Thể loại phim</a>
            <a href="#">Bình luận phim</a>
            <a href="#">Blog điện ảnh</a>
            <a href="#">Phim hay tháng</a>
        </div>

        <div class="footer-col">
            <h4>GÓC ĐIỆN ẢNH</h4>
            <a href="#">Thể loại phim</a>
            <a href="#">Bình luận phim</a>
            <a href="#">Blog điện ảnh</a>
            <a href="#">Phim hay tháng</a>
        </div>

        <div class="footer-col footer-contact">
            <h4>LIÊN HỆ VỚI CHÚNG TÔI</h4>

            <div class="footer-contact-item">
                <img src="{{ asset('assets/images/footer/location-dot-solid-full.svg') }}" alt="" class="footer-icon">
                <span>180 Cao Lỗ, phường Chánh Hưng, TP.HCM</span>
            </div>

            <div class="footer-contact-item">
                <img src="{{ asset('assets/images/footer/phone-solid-full.svg') }}" alt="" class="footer-icon">
                <span>1900 1234</span>
            </div>

            <div class="footer-contact-item">
                <img src="{{ asset('assets/images/footer/message-solid-full.svg') }}" alt="" class="footer-icon">
                <span>support@tdcinema.com</span>
            </div>

            <div class="footer-social">
                <a href="#">
                    <img src="{{ asset('assets/images/footer/facebook-brands-solid-full.svg') }}"
                         alt="Facebook" class="footer-social-icon">
                </a>
                <a href="#">
                    <img src="{{ asset('assets/images/footer/instagram-brands-solid-full.svg') }}"
                         alt="Instagram" class="footer-social-icon">
                </a>
                <a href="#">
                    <img src="{{ asset('assets/images/footer/youtube-brands-solid-full.svg') }}"
                         alt="Youtube" class="footer-social-icon">
                </a>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        © 2025 TD Cinema. All Rights Reserved. Designed for demonstration purposes.
    </div>
</footer>

<script>
    const dateTabs = Array.from(document.querySelectorAll('.date-tab'));
    const panels = Array.from(document.querySelectorAll('[data-date-panel]'));
    const noShowtimeMsg = document.getElementById('noShowtimeMsg');
    const prevDate = document.getElementById('prevDate');
    const nextDate = document.getElementById('nextDate');
    const cityFilter = document.getElementById('cityFilter');
    const cinemaFilter = document.getElementById('cinemaFilter');
    const cinemasByCity = @json($cinemasByCity);

    dateTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const d = tab.dataset.date;
            dateTabs.forEach(t => t.classList.toggle('active', t === tab));
            let hasPanel = false;
            panels.forEach(p => {
                const match = p.dataset.datePanel === d;
                p.style.display = match ? '' : 'none';
                if (match) hasPanel = true;
            });
            if (noShowtimeMsg) {
                noShowtimeMsg.style.display = hasPanel ? 'none' : '';
            }
        });
    });
    if (noShowtimeMsg && panels.length === 0) noShowtimeMsg.style.display = '';
    function shiftDate(direction) {
        const currentIdx = dateTabs.findIndex(t => t.classList.contains('active'));
        if (currentIdx === -1) return;
        const nextIdx = currentIdx + direction;
        if (nextIdx < 0 || nextIdx >= dateTabs.length) return;
        dateTabs[nextIdx].click();
    }
    prevDate?.addEventListener('click', () => shiftDate(-1));
    nextDate?.addEventListener('click', () => shiftDate(1));

    function renderCinemaOptions(city) {
        if (!cinemaFilter) return;
        const opts = document.createDocumentFragment();
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'Tất cả rạp';
        opts.appendChild(defaultOpt);
        if (city && cinemasByCity[city]) {
            cinemasByCity[city].forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                opts.appendChild(opt);
            });
        } else {
            Object.values(cinemasByCity).flat().forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                opts.appendChild(opt);
            });
        }
        cinemaFilter.innerHTML = '';
        cinemaFilter.appendChild(opts);
    }

    function applyCinemaFilter() {
        const cityVal = cityFilter?.value || '';
        const cinemaVal = cinemaFilter?.value || '';
        const blocks = document.querySelectorAll('.cinema-block');
        let visible = 0;
        blocks.forEach(block => {
            const cCity = block.dataset.city || '';
            const cId = block.dataset.cinemaId || '';
            const matchCity = !cityVal || cCity === cityVal;
            const matchCinema = !cinemaVal || cId === cinemaVal;
            const show = matchCity && matchCinema;
            block.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (noShowtimeMsg) {
            const anyPanelVisible = Array.from(panels).some(p => p.style.display !== 'none' && p.querySelector('.cinema-block[style*="display:"]'));
            noShowtimeMsg.style.display = visible === 0 && !anyPanelVisible ? '' : 'none';
        }
    }

    cityFilter?.addEventListener('change', () => {
        renderCinemaOptions(cityFilter.value);
        applyCinemaFilter();
    });
    cinemaFilter?.addEventListener('change', applyCinemaFilter);
    renderCinemaOptions(cityFilter?.value || '');
    applyCinemaFilter();

    // Auth modals
    const authOverlay = document.getElementById('authOverlay');
    const loginBox = document.getElementById('loginBox');
    const registerBox = document.getElementById('registerBox');
    const forgotBox = document.getElementById('forgotBox');
    const switchRegister = document.getElementById('switchRegister');
    const switchLogin = document.getElementById('switchLogin');
    const openForgot = document.getElementById('openForgot');
    const backLogin = document.getElementById('backLogin');
    const openLogin = document.getElementById('openLogin');
    const joinToggle = document.getElementById('joinToggle');
    const joinPopover = document.getElementById('joinPopover');
    const joinWrapper = document.getElementById('joinWrapper');
    const userToggle = document.getElementById('userMenuToggle');
    const userMenu = document.getElementById('userMenu');

    function showModal(box) {
        if (!authOverlay) return;
        authOverlay.classList.add('active');
        [loginBox, registerBox, forgotBox].forEach(b => { if (b) b.classList.add('hidden'); });
        if (box) box.classList.remove('hidden');
    }
    function closeModal() {
        if (authOverlay) authOverlay.classList.remove('active');
    }
    document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', closeModal));
    if (switchRegister) switchRegister.addEventListener('click', e => { e.preventDefault(); showModal(registerBox); });
    if (switchLogin) switchLogin.addEventListener('click', e => { e.preventDefault(); showModal(loginBox); });
    if (openLogin) openLogin.addEventListener('click', e => { e.preventDefault(); showModal(loginBox); });
    if (openForgot) openForgot.addEventListener('click', e => { e.preventDefault(); showModal(forgotBox); });
    if (backLogin) backLogin.addEventListener('click', e => { e.preventDefault(); showModal(loginBox); });
    authOverlay?.addEventListener('click', (e) => { if (e.target === authOverlay) closeModal(); });

    // Join popover
    function closeJoin() { if (joinPopover) joinPopover.style.display = 'none'; }
    if (joinToggle && joinPopover) {
        joinToggle.addEventListener('click', (e) => {
            e.preventDefault();
            joinPopover.style.display = joinPopover.style.display === 'block' ? 'none' : 'block';
        });
    }

    // User menu
    if (userToggle && userMenu) {
        userToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenu.style.display = userMenu.style.display === 'block' ? 'none' : 'block';
        });
    }

    // Click outside to close popovers/menus
    document.addEventListener('click', (e) => {
        if (joinWrapper && !joinWrapper.contains(e.target)) closeJoin();
        if (userMenu && userToggle && !userToggle.contains(e.target)) userMenu.style.display = 'none';
    });

    const isAuthenticated = @json(auth()->check());
    document.querySelectorAll('.showtime-pill').forEach(pill => {
        pill.addEventListener('click', (e) => {
            if (!isAuthenticated) {
                e.preventDefault();
                showModal(loginBox);
            }
        });
    });

    const trailerOverlay = document.getElementById('trailerOverlay');
    const trailerFrame = document.getElementById('trailerFrame');
    const closeTrailer = document.getElementById('closeTrailer');
    const openTrailerInline = document.getElementById('openTrailerInline');
    const playHero = document.getElementById('playTrailer');

    function toTrailerEmbed(url) {
        try {
            const u = new URL(url);
            if (u.hostname.includes('youtube.com')) {
                const vid = u.searchParams.get('v');
                if (vid) return `https://www.youtube.com/embed/${vid}?autoplay=1`;
                if (u.pathname.startsWith('/embed/')) return `${u.origin}${u.pathname}${u.search ? `${u.search}&autoplay=1` : '?autoplay=1'}`;
            }
            if (u.hostname === 'youtu.be') {
                const vid = u.pathname.replace('/', '');
                if (vid) return `https://www.youtube.com/embed/${vid}?autoplay=1`;
            }
        } catch (e) {}
        return url;
    }

    function openTrailer(url) {
        if (!trailerOverlay || !trailerFrame) return;
        trailerFrame.src = toTrailerEmbed(url);
        trailerOverlay.classList.add('active');
        document.body.classList.add('modal-open');
    }
    function hideTrailer() {
        if (!trailerOverlay || !trailerFrame) return;
        trailerOverlay.classList.remove('active');
        trailerFrame.src = '';
        document.body.classList.remove('modal-open');
    }
    openTrailerInline?.addEventListener('click', () => openTrailer(@json($movie->trailer_url)));
    playHero?.addEventListener('click', () => openTrailer(@json($movie->trailer_url)));
    closeTrailer?.addEventListener('click', hideTrailer);
    trailerOverlay?.addEventListener('click', (e) => { if (e.target === trailerOverlay) hideTrailer(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hideTrailer(); });
</script>
</body>
</html>
