@php
    $posterUrl = function ($movie) {
        $path = $movie->poster_path ?? '';
        if (empty($path)) {
            return 'https://via.placeholder.com/360x540?text=Poster';
        }
        return preg_match('/^https?:\/\//', $path)
            ? $path
            : asset('storage/' . ltrim($path, '/'));
    };
@endphp

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phim | TD Cinema</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap');
        :root {
            --color-primary: #359eff;
            --color-secondary: #f97316;
            --color-text-dark: #374151;
            --color-text-light: #ffffff;
            --font-main: 'Be Vietnam Pro', sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-main); background: #f6f7fb; color: #0f172a; }
        a { text-decoration: none; color: inherit; }
        .page { width: 100%; max-width: 100%; margin: 0; }
        .container { width: 100%; max-width: 1280px; margin: 0 auto; padding: 0 15px; }

        /* Header (giữ giống home) */
        .site-header { background: #fff; border-bottom: 1px solid #e5e7eb; }
        .header-container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; }
        .header-left { display: flex; align-items: center; gap: 32px; }
        .logo { display: flex; align-items: center; gap: 12px; font-weight: 800; color: #0b2c3d; text-transform: uppercase; letter-spacing: .2px; }
        .logo img { width: 42px; height: 42px; object-fit: contain; }
        .main-nav ul { list-style: none; display: flex; align-items: center; gap: 14px; }
        .main-nav a { font-weight: 600; font-size: 14px; color: #475569; padding: 6px 8px; border-radius: 8px; }
        .main-nav a:hover { background: #f3f4f6; color: #111827; }
        .nav-buy-img img { height: 38px; display: block; }
        .header-right { display: flex; align-items: center; gap: 14px; }
        .header-search-btn { border: none; background: transparent; cursor: pointer; font-size: 20px; color: #6b7280; }
        .btn { display:inline-flex; align-items:center; justify-content:center; border:none; border-radius:6px; font-weight:700; cursor:pointer; text-decoration:none; }
        .btn-primary { background: var(--color-primary); color: var(--color-text-light); padding:10px 18px; }
        .btn-outline { border:1px solid #ff5e00; color:#ff5e00; padding:8px 18px; background:transparent; }
        .user-info { display:flex; align-items:center; gap:10px; padding:6px 10px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; position:relative; cursor:pointer; box-shadow:0 4px 12px rgba(15,23,42,.06); }
        .user-avatar { width:34px; height:34px; border-radius:50%; background:#e5e7eb; display:grid; place-items:center; font-weight:700; color:#4b5563; overflow:hidden; }
        .user-avatar img { width:100%; height:100%; object-fit:cover; }
        .user-meta { display:flex; flex-direction:column; line-height:1.1; }
        .user-name { font-weight:800; font-size:14px; color:#111827; }
        .user-tier { font-size:12px; color:#6b7280; }
        .user-menu { position:absolute; right:0; top:110%; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.12); min-width:180px; display:none; z-index:40; }
        .user-menu a, .user-menu button { display:block; padding:10px 14px; color:#111827; font-size:14px; border:none; background:transparent; width:100%; text-align:left; cursor:pointer; }
        .user-menu a:hover, .user-menu button:hover { background:#f3f4f6; }

        /* Join popover (guest) */
        .header-login { font-weight:500; color:#6b7280; }
        .header-join-img img { height:38px; width:auto; display:block; object-fit:contain; }
        .join-wrapper { position:relative; }
        .join-popover { position:absolute; right:0; top:110%; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.12); padding:16px; width:900px; max-width:95vw; display:none; z-index:40; }
        .join-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
        .join-card { text-align:center; border:1px solid #e5e7eb; border-radius:10px; padding:12px; background:#fff; }
        .join-card img { height:80px; margin:0 auto 10px; object-fit:contain; }
        .join-card h4 { font-size:14px; font-weight:700; color:#1f2937; margin-bottom:6px; }
        .join-card p { font-size:13px; color:#6b7280; margin-bottom:8px; }
        .join-card .btn { padding:8px 12px; }
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
        @media(max-width: 960px){ .main-nav{display:none;} }

        /* Page intro */
        /* Tabs + grid */
        .nav-tabs { display: flex; gap: 26px; border-bottom: 1px solid rgba(0,0,0,.08); margin: 12px 0 22px; flex-wrap: wrap; }
        .nav-tabs a { font-weight: 800; font-size: 15px; padding: 12px 0; color: #6b6b6b; position: relative; text-transform: uppercase; letter-spacing: .2px; }
        .nav-tabs a.active { color: #034ea2; }
        .nav-tabs a.active::after { content:''; position:absolute; bottom:-1px; left:0; right:0; height:2px; background:#034ea2; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 22px; }
        .card { background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,.04); box-shadow: 0 10px 24px rgba(0,0,0,.05); position: relative; transition: transform .2s ease, box-shadow .2s ease; display:flex; flex-direction:column; }
        .card:hover { transform:translateY(-4px); box-shadow:0 16px 32px rgba(0,0,0,.08); }
        .poster-link { display:block; text-decoration:none; color:inherit; height:100%; position:relative; z-index:1; }
        .card img { width: 100%; height: 360px; object-fit: cover; display:block; border-radius:12px 12px 0 0; }
        .overlay { position:absolute; inset:0; background: linear-gradient(180deg,rgba(0,0,0,.05),rgba(0,0,0,.55)); opacity:0; transition:opacity .2s; display:flex; align-items:center; justify-content:center; gap:10px; z-index:3; pointer-events:none; }
        .card:hover .overlay { opacity:1; }
        .overlay .btn { border-radius:8px; padding:10px 12px; font-size:13px; font-weight:700; pointer-events:auto; cursor:pointer; }
        .overlay .btn-buy { background:#f26b38; color:#fff; border:none; }
        .overlay .btn-trailer { background:transparent; color:#fff; border:1px solid #fff; }
        .card-info { padding: 12px; }
        .title { font-size: 14px; font-weight: 800; margin-bottom: 6px; color: #0f172a; line-height:1.3; }
        .genre { font-size: 12px; color: #6b7280; text-transform: capitalize; }
        .status-badge { display: inline-block; font-size: 12px; padding: 4px 8px; border-radius: 999px; font-weight: 700; margin-top: 6px; }
        .badge-now { background: #e0f2ff; color: #0b70e0; }
        .badge-upcoming { background: #fff7ed; color: #ea580c; }
        .badge-imax { background: #eef2ff; color: #4338ca; }

        /* Auth modal */
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:60; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:#fff; border-radius:16px; padding:18px 20px; width:420px; max-width:95vw; box-shadow:0 16px 40px rgba(0,0,0,.18); position:relative; }
        .modal-close { position:absolute; top:10px; right:10px; border:none; background:transparent; font-size:20px; cursor:pointer; color:#6b7280; }
        .modal-header { text-align:center; margin-bottom:16px; }
        .modal-title { font-size:20px; font-weight:800; color:#0f172a; }
        .modal-sub { color:#6b7280; font-size:14px; margin-top:4px; }
        .auth-form { display:flex; flex-direction:column; gap:12px; }
        .auth-form label { font-size:13px; font-weight:700; color:#0f172a; margin-bottom:4px; display:block; }
        .auth-form input { width:100%; border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; font-size:14px; }
        .auth-actions { display:flex; justify-content:space-between; align-items:center; font-size:13px; color:#6b7280; }
        .auth-link { color:#034ea2; font-weight:700; cursor:pointer; }
        .auth-submit { width:100%; margin-top:4px; }
        .auth-hint { font-size:12px; color:#9ca3af; margin-top:4px; }
        .auth-alert { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; padding:10px 12px; border-radius:10px; font-size:13px; margin-bottom:10px; font-weight:700; }
        .hidden { display:none; }

        /* Pagination */
        .pagination-wrap { margin-top: 16px; display: flex; justify-content: center; }
        .pagination > nav { display: flex; gap: 6px; flex-wrap: wrap; }
        .pagination nav a, .pagination nav span { padding: 8px 10px; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 13px; color: #334155; text-decoration: none; background: #fff; }
        .pagination nav .active { background: #2563eb; color: #fff; border-color: #2563eb; }

        /* Footer (giống home) */
        .footer { background: #232633; color: #fff; padding: 40px 0 24px; font-size: 14px; margin-top: 40px; }
        .footer-inner { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: minmax(0,2.2fr) repeat(3,minmax(0,1fr)); gap: 32px; align-items: flex-start; padding: 0 16px; }
        .footer-brand { display: flex; align-items: flex-start; gap: 16px; }
        .footer-logo img { width: 110px; height: auto; display: block; }
        .footer-company-name { font-weight: 700; margin-bottom: 6px; }
        .footer-company p { margin: 0; color: rgba(255,255,255,.7); }
        .footer h4 { text-transform: uppercase; font-size: 13px; margin-bottom: 12px; font-weight: 700; }
        .footer-col a { display: block; margin-bottom: 8px; color: rgba(255,255,255,.7); text-decoration: none; }
        .footer-col a:hover { color: #fff; text-decoration: underline; }
        .footer-contact-item { display: flex; align-items: center; margin-bottom: 8px; color: rgba(255,255,255,.75); }
        .footer-icon { width: 18px; height: 18px; margin-right: 10px; flex-shrink: 0; }
        .footer-social { margin-top: 10px; display: flex; gap: 12px; }
        .footer-social-icon { width: 26px; height: 26px; display: block; opacity: .85; transition: .2s; }
        .footer-social-icon:hover { opacity: 1; transform: scale(1.08); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,.1); margin-top: 30px; padding-top: 16px; text-align: center; font-size: 12px; color: rgba(255,255,255,.5); }
        @media(max-width: 960px){ .footer-inner{grid-template-columns:1fr 1fr;} }
        @media(max-width: 720px){ .footer-inner{grid-template-columns:1fr;} .hero-compact{flex-direction:column; align-items:flex-start; gap:8px;} }
    </style>
</head>
<body>
<div class="page" id="top">
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
    <header class="site-header">
        <div class="header-container">
            <div class="header-left">
                <a href="{{ route('home') }}" class="logo">
                    <img src="{{ asset('assets/images/logo/logo.jpg') }}" alt="TD Cinema Logo" onerror="this.src='https://via.placeholder.com/40?text=TD'">
                    <span>TD Cinema</span>
                </a>
                <nav class="main-nav">
                    <ul>
                        <li><a href="{{ route('home') }}" class="nav-buy-img no-caret"><img src="{{ asset('assets/images/ui/buyticket.png') }}" alt="Mua vé"></a></li>
                        <li class="has-mega">
                            <a href="{{ route('movies.index') }}">Phim</a>
                            <div class="mega">
                                <div class="mega-grid">
                                    <div class="mega-col">
                                        <h4>Phim đang chiếu</h4>
                                        <div class="mega-list">
                                            @forelse($navNow as $movie)
                                                <a class="mega-item" href="{{ route('movies.show', $movie) }}">
                                                    <div class="mega-thumb"><img src="{{ $navPoster($movie) }}" alt="{{ $movie->title }}"></div>
                                                    <div class="mega-info">
                                                        <div class="mega-title">{{ $movie->title }}</div>
                                                        <div class="mega-meta">{{ $movie->genre ?? 'Đang cập nhật' }}</div>
                                                        @if($movie->rating_avg)
                                                            <div class="mega-rating">★ {{ number_format($movie->rating_avg,1) }}</div>
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
                                            @forelse($navUpcoming as $movie)
                                                <a class="mega-item" href="{{ route('movies.show', $movie) }}">
                                                    <div class="mega-thumb"><img src="{{ $navPoster($movie) }}" alt="{{ $movie->title }}"></div>
                                                    <div class="mega-info">
                                                        <div class="mega-title">{{ $movie->title }}</div>
                                                        <div class="mega-meta">{{ $movie->genre ?? 'Đang cập nhật' }}</div>
                                                        @if($movie->rating_avg)
                                                            <div class="mega-rating">★ {{ number_format($movie->rating_avg,1) }}</div>
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
                                            @forelse($navImax as $movie)
                                                <a class="mega-item" href="{{ route('movies.show', $movie) }}">
                                                    <div class="mega-thumb"><img src="{{ $navPoster($movie) }}" alt="{{ $movie->title }}"></div>
                                                    <div class="mega-info">
                                                        <div class="mega-title">{{ $movie->title }}</div>
                                                        <div class="mega-meta">{{ $movie->genre ?? 'Đang cập nhật' }}</div>
                                                        @if($movie->rating_avg)
                                                            <div class="mega-rating">★ {{ number_format($movie->rating_avg,1) }}</div>
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
                <button type="button" class="header-search-btn" aria-label="Tìm kiếm">🔍</button>
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
                            <a href="{{ route('profile.edit') }}">Tài khoản</a>
                            <a href="{{ route('customer.tickets') }}">Lịch sử vé</a>
                            <a href="#">Đổi điểm/Stars</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit">Đăng xuất</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="#" class="header-login" id="openLogin">Đăng nhập</a>
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
                                    <img src="{{ asset('assets/images/membership/join.png') }}" alt="Đăng ký thành viên" onerror="this.src='https://via.placeholder.com/80?text=Join'">
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

    <div class="container">
        <div class="nav-tabs">
            <a href="{{ url('/movies?status=now_showing') }}" class="{{ $status==='now_showing' ? 'active' : '' }}">PHIM ĐANG CHIẾU</a>
            <a href="{{ url('/movies?status=upcoming') }}" class="{{ $status==='upcoming' ? 'active' : '' }}"> PHIM SẮP CHIẾU</a>
            <a href="{{ url('/movies?status=imax') }}" class="{{ $status==='imax' ? 'active' : '' }}">PHIM IMAX</a>
        </div>

        <div class="grid">
            @forelse($movies as $movie)
                @php
                    $poster = $posterUrl($movie);
                    $trailer = $movie->trailer_url ?? null;
                @endphp
                <div class="card">
                    <div class="movie-poster">
                        <a class="poster-link" href="{{ route('movies.show', $movie) }}" title="{{ $movie->title }}">
                            <img src="{{ $poster }}" alt="{{ $movie->title }}">
                        </a>
                        <div class="overlay">
                            <a href="{{ route('movies.show', $movie) }}" class="btn btn-buy">Mua vé</a>
                            <a href="{{ $trailer ?: route('movies.show', $movie) }}" class="btn btn-trailer" @if($trailer) target="_blank" rel="noopener" @endif>Trailer</a>
                        </div>
                    </div>
                    <div class="card-info">
                        <div class="title">{{ $movie->title }}</div>
                        <div class="genre">{{ $movie->genre ?: 'Đang cập nhật' }}</div>
                        <span class="status-badge
                            @if($movie->status === 'now_showing') badge-now @elseif($movie->status === 'upcoming') badge-upcoming @else badge-imax @endif">
                            @if($movie->status === 'now_showing') Đang chiếu
                            @elseif($movie->status === 'upcoming') Sắp chiếu
                            @else IMAX @endif
                        </span>
                    </div>
                </div>
            @empty
                <p>Chưa có phim.</p>
            @endforelse
        </div>

        <div class="pagination-wrap pagination">
            {{ $movies->links() }}
        </div>
    </div>

    <footer class="footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <div class="footer-logo">
                    <img src="{{ asset('assets/images/logo/logo.jpg') }}" alt="TD Cinema" onerror="this.src='https://via.placeholder.com/110x40?text=TD'">
                </div>
                <div class="footer-company">
                    <p class="footer-company-name">CÔNG TY CỔ PHẦN PHIM ĐẠI TRƯỜNG</p>
                    <p>180 Cao Lỗ, phường Chánh Hưng, TP. Hồ Chí Minh</p>
                </div>
            </div>
            <div class="footer-col">
                <h4>Góc điện ảnh</h4>
                <a href="#">Thể loại phim</a>
                <a href="#">Bình luận phim</a>
                <a href="#">Blog điện ảnh</a>
                <a href="#">Phim hay tháng</a>
            </div>
            <div class="footer-col">
                <h4>Góc điện ảnh</h4>
                <a href="#">Thể loại phim</a>
                <a href="#">Bình luận phim</a>
                <a href="#">Blog điện ảnh</a>
                <a href="#">Phim hay tháng</a>
            </div>
            <div class="footer-col footer-contact">
                <h4>Liên hệ với chúng tôi</h4>
                <div class="footer-contact-item">
                    <img src="{{ asset('assets/images/footer/location-dot-solid-full.svg') }}" alt="" class="footer-icon">
                    180 Cao Lỗ, phường Chánh Hưng, tp.HCM
                </div>
                <div class="footer-contact-item">
                    <img src="{{ asset('assets/images/footer/phone-solid-full.svg') }}" alt="" class="footer-icon">
                    1900 1234
                </div>
                <div class="footer-contact-item">
                    <img src="{{ asset('assets/images/footer/message-solid-full.svg') }}" alt="" class="footer-icon">
                    support@tdcinema.com
                </div>
                <div class="footer-social">
                    <a href="#"><img src="{{ asset('assets/images/footer/facebook-brands-solid-full.svg') }}" alt="Facebook" class="footer-social-icon"></a>
                    <a href="#"><img src="{{ asset('assets/images/footer/instagram-brands-solid-full.svg') }}" alt="Instagram" class="footer-social-icon"></a>
                    <a href="#"><img src="{{ asset('assets/images/footer/youtube-brands-solid-full.svg') }}" alt="Youtube" class="footer-social-icon"></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">© 2025 TD Cinema. All Rights Reserved. Designed for demonstration purposes.</div>
    </footer>
</div>

<!-- Auth Modals (giống trang home) -->
<div class="modal-overlay" id="authOverlay">
    <div class="modal-box" id="loginBox">
        <button class="modal-close" data-close>×</button>
        <div class="modal-header">
            <div class="modal-title">Chào mừng trở lại!</div>
            <div class="modal-sub">Đăng nhập để tiếp tục trải nghiệm của bạn.</div>
        </div>
        @if($errors->has('email'))
            <div class="auth-alert">{{ $errors->first('email') }}</div>
        @endif
        <form class="auth-form" method="POST" action="{{ route('login') }}">
            @csrf
            <div>
                <label>Email hoặc Tên đăng nhập</label>
                <input type="text" name="email" value="{{ old('email') }}" placeholder="Nhập email hoặc tên đăng nhập">
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

    <div class="modal-box" id="registerBox" style="display:none;">
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
                <div class="auth-hint">Phải có ít nhất 8 ký tự.</div>
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

    <div class="modal-box" id="forgotBox" style="display:none;">
        <button class="modal-close" data-close>×</button>
        <div class="modal-header" id="forgotHeader">
            <div class="modal-title">Quên mật khẩu</div>
            <div class="modal-sub">Nhập email để nhận liên kết đặt lại.</div>
        </div>
        <div id="forgotFormWrap">
            <form class="auth-form" method="POST" action="{{ route('password.email') }}">
                @csrf
                <div>
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com">
                </div>
                <button type="submit" class="btn btn-primary auth-submit">Gửi liên kết</button>
                <div class="auth-actions" style="justify-content:center; gap:4px; margin-top:8px;">
                    <span class="auth-link" id="backLogin">Quay lại đăng nhập</span>
                </div>
            </form>
        </div>
        <div id="forgotSuccess" class="hidden">
            <p>Email khôi phục đã được gửi tới <strong id="resetEmailTxt"></strong>.</p>
            <button type="button" class="btn btn-primary auth-submit" id="closeForgotSuccess">Đóng</button>
        </div>
    </div>
</div>

<script>
    // User menu toggle
    const userToggle = document.getElementById('userMenuToggle');
    const userMenu = document.getElementById('userMenu');
    if (userToggle && userMenu) {
        userToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenu.style.display = userMenu.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', (e) => {
            if (!userToggle.contains(e.target)) userMenu.style.display = 'none';
        });
    }

    // Join popover toggle (guest)
    const joinToggle = document.getElementById('joinToggle');
    const joinPopover = document.getElementById('joinPopover');
    if (joinToggle && joinPopover) {
        joinToggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const isOpen = joinPopover.style.display === 'block';
            joinPopover.style.display = isOpen ? 'none' : 'block';
        });
        document.addEventListener('click', (e) => {
            if (joinPopover && !joinPopover.contains(e.target) && !joinToggle.contains(e.target)) {
                joinPopover.style.display = 'none';
            }
        });
    }

    // Auth modals (copy from home)
    const authOverlay = document.getElementById('authOverlay');
    const loginBox = document.getElementById('loginBox');
    const registerBox = document.getElementById('registerBox');
    const forgotBox = document.getElementById('forgotBox');
    const openLogin = document.getElementById('openLogin');
    const openRegister = document.getElementById('openRegister');
    const switchRegister = document.getElementById('switchRegister');
    const switchLogin = document.getElementById('switchLogin');
    const openForgot = document.getElementById('openForgot');
    const backLogin = document.getElementById('backLogin');

    function showModal(box) {
        if (!authOverlay) return;
        authOverlay.classList.add('active');
        [loginBox, registerBox, forgotBox].forEach(b => { if (b) b.style.display = 'none'; });
        if (box) box.style.display = 'block';
    }
    function closeModal() { if (authOverlay) authOverlay.classList.remove('active'); }

    if (openLogin) openLogin.addEventListener('click', e => { e.preventDefault(); if (joinPopover) joinPopover.style.display='none'; showModal(loginBox); });
    if (openRegister) openRegister.addEventListener('click', e => { e.preventDefault(); if (joinPopover) joinPopover.style.display='none'; showModal(registerBox); });
    if (switchRegister) switchRegister.addEventListener('click', e => { e.preventDefault(); showModal(registerBox); });
    if (switchLogin) switchLogin.addEventListener('click', e => { e.preventDefault(); showModal(loginBox); });
    if (openForgot) openForgot.addEventListener('click', e => { e.preventDefault(); showModal(forgotBox); });
    if (backLogin) backLogin.addEventListener('click', e => { e.preventDefault(); showModal(loginBox); });
    document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', closeModal));
    if (authOverlay) authOverlay.addEventListener('click', e => { if (e.target === authOverlay) closeModal(); });

    // Nếu có lỗi login, mở sẵn modal
    @if($errors->has('email'))
        showModal(loginBox);
    @endif
</script>
</body>
</html>
