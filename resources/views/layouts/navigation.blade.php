<header class="site-header" style="font-family:'Be Vietnam Pro',sans-serif;">
    <style>
        .site-header { background:#fff; border-bottom:1px solid #e5e7eb; }
        .header-container { width:100%; max-width:1200px; margin:0 auto; padding:10px 16px; display:flex; align-items:center; justify-content:space-between; }
        .header-left { display:flex; align-items:center; gap:16px; }
        .logo { display:flex; align-items:center; gap:10px; font-weight:800; color:#0b2c3d; text-transform:uppercase; letter-spacing:0.2px; }
        .logo img { width:42px; height:42px; object-fit:contain; }
        .main-nav ul { display:flex; align-items:center; gap:12px; list-style:none; margin:0; padding:0; }
        .main-nav a { font-size:14px; font-weight:600; color:#475569; padding:6px 8px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
        .main-nav a:hover { background:#f3f4f6; color:#111827; }
        .nav-buy-img img { height:38px; width:auto; display:block; }
        .header-right { display:flex; align-items:center; gap:12px; }
        .header-search-btn { border:none; background:transparent; cursor:pointer; font-size:20px; line-height:1; color:#6b7280; display:flex; align-items:center; justify-content:center; }
        .user-chip { display:flex; align-items:center; gap:10px; padding:8px 10px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; box-shadow:0 4px 12px rgba(15,23,42,0.06); }
        .chip-avatar { width:34px; height:34px; border-radius:50%; background:#e5e7eb; display:grid; place-items:center; font-weight:800; color:#0b2c3d; overflow:hidden; }
        .chip-avatar img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
        .chip-name { font-weight:800; font-size:14px; color:#111827; line-height:1.1; }
        .chip-sub { font-size:12px; color:#6b7280; }
        @media(max-width:960px){
            .main-nav{display:none;}
        }
        /* Mega menu Phim */
        .has-mega { position:relative; }
        .mega { position:absolute; left:0; top:48px; background:#fff; box-shadow:0 12px 32px rgba(0,0,0,.12); border-radius:14px; padding:16px; display:none; z-index:50; width:920px; max-width:95vw; }
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
        @media(max-width:640px){ .mega{left:0; width:100vw;} }
    </style>
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
                        <a href="{{ route('movies.index') }}">Phim ▾</a>
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
                    <li><a href="#cinema-corner">Star Shop</a></li>
                    <li><a href="#cinema-corner">Góc Điện Ảnh</a></li>
                    <li><a href="#promotions">Sự kiện</a></li>
                    <li><a href="#promotions">Rạp/Giá vé</a></li>
                </ul>
            </nav>
        </div>
        <div class="header-right">
            <button type="button" class="header-search-btn" aria-label="Tìm kiếm">🔍</button>
            @auth
                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button class="user-chip">
                            <div class="chip-avatar">
                                @if(auth()->user()->avatar ?? false)
                                    <img src="{{ asset('storage/' . ltrim(auth()->user()->avatar, '/')) }}" alt="Avatar">
                                @else
                                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                                @endif
                            </div>
                            <div>
                                <div class="chip-name">{{ auth()->user()->name ?? 'User' }}</div>
                                <div class="chip-sub">Star · {{ auth()->user()->points ?? 0 }} Stars</div>
                            </div>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Tài khoản</x-dropdown-link>
                        <x-dropdown-link :href="route('customer.tickets')">Lịch sử vé</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                Đăng xuất
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            @endauth
        </div>
    </div>
</header>
