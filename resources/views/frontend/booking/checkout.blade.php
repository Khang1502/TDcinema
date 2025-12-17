@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    $movie = $showtime->movie;
    $posterUrl = function ($movie) {
        $path = $movie->poster_path ?? '';
        if (empty($path)) return 'https://via.placeholder.com/360x540?text=Poster';
        return preg_match('/^https?:\/\//', $path) ? $path : asset('storage/' . ltrim($path, '/'));
    };
    $bannerUrl = function ($movie) use ($posterUrl) {
        $path = $movie->banner_path ?? '';
        if ($path) return preg_match('/^https?:\/\//', $path) ? $path : asset('storage/' . ltrim($path, '/'));
        return $posterUrl($movie);
    };

    $ageBadge = strtoupper($movie->age_rating ?? 'T13');
    $ogTitle  = 'Chọn ghế - ' . ($movie->title ?? 'TD Cinema');
    $ogDesc   = Str::limit(trim($movie->description ?? ''), 150);
    $heroBg   = $bannerUrl($movie);
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
    <style>
        :root { --p:#f26b38; --p-dark:#0b5394; --text:#0f172a; --muted:#6b7280; --bg:#f7f8fb; --blue:#0d6efd; }
        @import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap');
        *{box-sizing:border-box;margin:0;padding:0;} body{font-family:'Be Vietnam Pro',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;} a{text-decoration:none;color:inherit;} img{max-width:100%;display:block;}

        /* Hide hero for checkout */
        .hero{display:none;}

        /* Header */
        .header{background:#fff;border-bottom:1px solid #e5e7eb;padding:10px 0;box-shadow:0 6px 18px rgba(0,0,0,.08);position:sticky;top:0;z-index:50;}
        .header-inner{max-width:1240px;margin:0 auto;padding:0 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;}
        .header-left{display:flex;align-items:center;gap:26px;}
        .logo{display:flex;align-items:center;gap:10px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:#0b2c3d;} .logo img{width:40px;height:40px;object-fit:cover;border-radius:8px;}
        .nav{display:flex;gap:18px;align-items:center;font-weight:700;font-size:14px;color:#4b5563;} .nav a{display:inline-flex;align-items:center;gap:6px;} .nav a::after{content:'▾';font-size:10px;color:#94a3b8;} .nav a.no-caret::after{display:none;} .nav a:hover{color:#0b3954;}
        .nav-buy-img img{height:40px;width:auto;display:block;}
        .header-right{display:flex;align-items:center;gap:12px;font-size:14px;color:#4b5563;}
        .header-search-btn{border:none;background:transparent;cursor:pointer;font-size:22px;line-height:1;color:#6b7280;display:flex;align-items:center;justify-content:center;}
        .join-img img{height:38px;width:auto;display:block;object-fit:contain;}
        .user-area{display:flex;align-items:center;gap:12px;font-size:14px;color:#4b5563;} .user-chip{display:flex;align-items:center;gap:8px;border:1px solid #e5e7eb;border-radius:12px;padding:6px 10px;background:#fff;cursor:pointer;position:relative;box-shadow:0 4px 12px rgba(0,0,0,.05);} .avatar{width:36px;height:36px;border-radius:50%;background:#e5e7eb;display:grid;place-items:center;font-weight:700;color:#0f172a;overflow:hidden;} .avatar img{width:100%;height:100%;object-fit:cover;} .user-menu{position:absolute;right:0;top:110%;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.18);min-width:180px;display:none;z-index:30;color:#0f172a;} .user-menu a,.user-menu button{display:block;width:100%;padding:10px 14px;font-size:14px;border:none;background:transparent;text-align:left;cursor:pointer;} .user-menu a:hover,.user-menu button:hover{background:#f5f5f5;}

        /* Progress + layout */
        .page{max-width:1240px;margin:0 auto;padding:18px 20px 40px;position:relative;z-index:2;}
        .progress{display:flex;justify-content:center;gap:8px;overflow:auto;padding:6px 0 16px;margin-bottom:16px;}
        .step{display:flex;align-items:center;gap:6px;padding:10px 12px;border:none;font-weight:800;font-size:14px;color:#8c99a8;min-width:150px;white-space:nowrap;background:transparent;position:relative;}
        .step::after{content:'';position:absolute;left:8px;right:8px;bottom:0;height:2px;background:#dfe3e8;border-radius:999px;}
        .step.active{color:var(--p-dark);}
        .step.active::after{background:var(--p-dark);}
        .step.done{color:var(--p-dark);}
        .step.done::after{background:var(--p-dark);}

        .meta{display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;margin:6px 0 12px;color:var(--text);} .meta h2{font-size:22px;font-weight:800;display:flex;align-items:center;gap:8px;} .info{color:#475569;font-size:14px;}
        .pills{display:flex;gap:8px;flex-wrap:wrap;} .pill{padding:10px 14px;border:1px solid #d7e5f3;border-radius:4px;font-weight:700;font-size:13px;display:inline-flex;gap:6px;align-items:center;background:#fff;color:#0f172a;} .pill.active{background:#0d6efd;color:#fff;border-color:#0d6efd;}

        .grid{display:grid;grid-template-columns:2fr 1fr;gap:18px;align-items:start;}
        .card{background:#fff;color:var(--text);border:1px solid #eef1f5;border-radius:10px;box-shadow:0 10px 24px rgba(0,0,0,.06);padding:18px;}

        .screen{text-align:center;font-weight:800;color:#0f172a;margin-bottom:10px;text-transform:uppercase;letter-spacing:1px;} .screen-bar{height:3px;background:#f26b38;border-radius:999px;margin-top:6px;width:70%;margin-left:auto;margin-right:auto;}
        .legend{display:flex;gap:14px;flex-wrap:wrap;font-size:13px;color:#475569;margin:10px 0 0;} .dot{width:16px;height:16px;border:1px solid #cbd5e1;border-radius:4px;display:inline-block;} .dot.sold{background:#93c5fd;border-color:#3b82f6;} .dot.sel{background:#f97316;border-color:#f97316;} .dot.vip{background:#fff7e6;border-color:#f59e0b;} .dot.double{background:#e0e7ff;border-color:#818cf8;} .dot.single{background:#fff;border-color:#d7e5f3;}
        .seat-map{background:#fff;border:1px solid #eef1f5;border-radius:10px;padding:22px 18px;display:flex;flex-direction:column;gap:12px;overflow:auto;} .row{display:flex;align-items:center;gap:12px;justify-content:center;} .row-label{width:24px;text-align:center;font-weight:700;color:#94a3b8;font-size:13px;} .row-seats{display:flex;gap:8px;flex-wrap:wrap;flex:1;justify-content:center;}
        .seat{width:34px;height:34px;border-radius:4px;border:1px solid #cbd5e1;background:#fff;font-weight:700;font-size:13px;display:grid;place-items:center;cursor:pointer;transition:.12s;color:#0f172a;} .seat:hover{border-color:var(--blue);box-shadow:0 2px 6px rgba(0,0,0,.08);} .seat.sel{background:var(--p);color:#fff;border-color:var(--p);} .seat.vip{border-color:#f59e0b;background:#fff7e6;} .seat.sweetbox{min-width:64px;background:#e0e7ff;border-color:#818cf8;} .seat.sold,.seat.inactive{background:#93c5fd;color:#1e3a8a;border-color:#3b82f6;cursor:not-allowed;box-shadow:none;}

        .summary-bar{height:6px;background:var(--p);margin:-18px -18px 14px -18px;}
        .summary-movie{display:grid;grid-template-columns:90px 1fr;gap:14px;margin-bottom:14px;} .summary-title{font-size:18px;font-weight:800;color:#1f2937;line-height:1.25;} .summary-genre{color:#6b7280;font-size:14px;margin-top:2px;}
        .summary-meta{color:#475569;font-size:14px;line-height:1.5;margin-top:2px;}
        .chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;} .chip{padding:8px 10px;border:1px solid #e2e8f0;border-radius:0;background:#f5f7fb;font-weight:700;color:#0f172a;} .empty{color:#94a3b8;font-size:13px;}
        .total{display:flex;justify-content:space-between;align-items:center;font-size:18px;font-weight:800;margin-top:16px;margin-bottom:6px;} .actions{display:flex;gap:12px;margin-top:14px;} .btn{border:none;border-radius:0;padding:12px 18px;font-weight:700;cursor:pointer;} .btn.ghost{background:#fff;border:1px solid #e5e7eb;color:#e68a00;} .btn.primary{background:var(--p);color:#fff;box-shadow:0 6px 16px rgba(242,107,56,.2);} .note{color:#8f8f8f;font-size:13px;margin-top:8px;} .alert{color:#b91c1c;font-size:13px;margin-top:6px;display:none;}

        .footer{background:#0f1f33;color:#fff;padding:36px 0 18px;margin-top:32px;font-size:14px;} .footer-inner{max-width:1200px;margin:0 auto;padding:0 16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:18px;} .footer a{color:rgba(255,255,255,.75);} .footer a:hover{color:#fff;text-decoration:underline;} .footer-bottom{text-align:center;color:rgba(255,255,255,.6);font-size:12px;margin-top:14px;border-top:1px solid rgba(255,255,255,.12);padding-top:10px;}

        .overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:90;padding:16px;} .overlay.active{display:flex;} .modal{background:#fff;border-radius:12px;padding:22px;max-width:420px;width:92vw;box-shadow:0 12px 30px rgba(0,0,0,.2);position:relative;} .modal h4{font-size:20px;font-weight:800;margin-bottom:6px;} .modal p{color:#6b7280;font-size:14px;} .modal form{display:flex;flex-direction:column;gap:10px;margin-top:12px;} .modal input{padding:12px;border:1px solid #e5e7eb;border-radius:8px;} .close-btn{position:absolute;top:10px;right:10px;border:none;background:transparent;font-size:18px;color:#6b7280;cursor:pointer;}

        @media(max-width:900px){
            .grid{grid-template-columns:1fr;}
            .meta{flex-direction:column;align-items:flex-start;}
        }
    </style>
</head>
<body>
<header class="header">
    <div class="header-inner">
        <div class="header-left">
            <a href="{{ route('home') }}" class="logo">
                <img src="{{ asset('assets/images/logo/logo.jpg') }}" alt="TD Cinema"><span>TD Cinema</span>
            </a>
            <nav class="nav">
                <a href="#" class="nav-buy-img no-caret"><img src="{{ asset('assets/images/ui/buyticket.png') }}" alt="Mua vé"></a>
                <a href="{{ route('home') }}#now">Phim</a>
                <a href="#">Star Shop</a>
                <a href="{{ route('home') }}#cinema-corner">Góc Điện Ảnh</a>
                <a href="#">Sự kiện</a>
                <a href="#">Rạp/Giá vé</a>
            </nav>
        </div>
        <div class="header-right">
            <button class="header-search-btn" aria-label="Search">🔍</button>
            @auth
                <div class="user-chip" id="userToggle">
                    <div class="avatar">
                        @if(auth()->user()->avatar ?? false)
                            <img src="{{ asset('storage/' . ltrim(auth()->user()->avatar, '/')) }}" alt="avatar">
                        @else
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        @endif
                    </div>
                    <div>
                        <div style="font-weight:700;">{{ auth()->user()->name ?? 'User' }}</div>
                        <div style="font-size:12px;color:#6b7280;">Star - {{ auth()->user()->points ?? 0 }} pts</div>
                    </div>
                    <div class="user-menu" id="userMenu">
                        <a href="#">Tài khoản</a>
                        <a href="#">Lịch sử vé</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit">Đăng xuất</button>
                        </form>
                    </div>
                </div>
            @else
                <span class="header-login" id="openLogin" style="font-weight:700;cursor:pointer;color:#0b2c3d;">Đăng nhập</span>
                <a href="#" class="join-img"><img src="{{ asset('assets/images/membership/ThamGiaCVIP.jpg') }}" alt="Tham gia" onerror="this.src='https://via.placeholder.com/90x38?text=Join'"></a>
            @endauth
        </div>
    </div>
</header>

<section class="hero">
    <div class="hero-bg" style="background-image:url('{{ $heroBg }}')"></div>
    <div class="hero-inner">
        <div class="hero-poster"><img src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}"></div>
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
        <div class="card">
            <div class="screen">Màn hình<div class="screen-bar"></div></div>
            <div class="seat-map">
                @forelse($seatRows as $rowLabel => $rowSeats)
                    <div class="row">
                        <div class="row-label">{{ $rowLabel }}</div>
                        <div class="row-seats">
                            @foreach($rowSeats as $seat)
                                @php $state = $seat['status'] === 'sold' ? 'sold' : ($seat['status'] === 'inactive' ? 'inactive' : ''); @endphp
                                <button class="seat {{ $seat['type'] }} {{ $state }}"
                                        data-seat-id="{{ $seat['id'] }}"
                                        data-code="{{ $seat['code'] }}"
                                        data-price="{{ $seat['price'] }}"
                                        data-row="{{ $seat['row'] }}"
                                        data-number="{{ $seat['number'] }}"
                                        @if($state) disabled @endif>{{ $seat['number'] }}</button>
                            @endforeach
                        </div>
                        <div class="row-label">{{ $rowLabel }}</div>
                    </div>
                @empty
                    <div class="empty">Chưa có sơ đồ ghế.</div>
                @endforelse
            </div>
            <div class="legend">
                <span><span class="dot"></span> Trống</span>
                <span><span class="dot sold"></span> Đã bán</span>
                <span><span class="dot sel"></span> Đang chọn</span>
                <span><span class="dot vip"></span> Ghế VIP</span>
                <span><span class="dot single"></span> Ghế đơn</span>
                <span><span class="dot double"></span> Ghế đôi</span>
            </div>
        </div>

        <div class="card summary">
            <div class="summary-bar"></div>
            <div class="summary-movie">
                <div style="width:90px;overflow:hidden;border:1px solid #e5e7eb;"><img src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}"></div>
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
                <div class="summary-meta" style="font-weight:700;">Ghế đã chọn</div>
                <div id="selectedList" class="chips"></div>
                <div id="emptySelected" class="empty">Chưa chọn ghế</div>
                <div id="seatAlert" class="alert"></div>
                <div class="total"><span>Tổng cộng</span><span id="totalPrice">0 đ</span></div>
            </div>
            <div class="actions">
                <a href="{{ route('movies.show', $movie) }}" class="btn ghost" style="flex:1;">Quay lại</a>
                <button type="button" class="btn primary" id="btnContinue" style="flex:1;">Tiếp tục</button>
            </div>
            <p class="note">Bước tiếp: chọn combo và thanh toán. Đăng nhập để giữ ghế.</p>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="footer-inner">
        <div class="footer-brand" style="display:flex;align-items:flex-start;gap:16px;">
            <div class="footer-logo">
                <img src="{{ asset('assets/images/logo/logo.jpg') }}" alt="TD Cinema" style="width:110px;height:auto;display:block;" />
            </div>
            <div class="footer-company">
                <p style="font-weight:700;margin-bottom:6px;">CÔNG TY CỔ PHẦN PHIM ĐẠI TRƯỜNG</p>
                <p style="margin:0;color:rgba(255,255,255,.75);">180 Cao Lỗ, phường Chánh Hưng, tp. Hồ Chí Minh</p>
            </div>
        </div>

        <div>
            <div style="text-transform:uppercase;font-size:13px;margin-bottom:12px;font-weight:700;">GÓC ĐIỆN ẢNH</div>
            <a href="#" style="display:block;margin-bottom:8px;">Thể loại phim</a>
            <a href="#" style="display:block;margin-bottom:8px;">Bình luận phim</a>
            <a href="#" style="display:block;margin-bottom:8px;">Blog điện ảnh</a>
            <a href="#" style="display:block;margin-bottom:8px;">Phim hay tháng</a>
        </div>

        <div>
            <div style="text-transform:uppercase;font-size:13px;margin-bottom:12px;font-weight:700;">HỖ TRỢ</div>
            <a href="#" style="display:block;margin-bottom:8px;">Góp ý</a>
            <a href="#" style="display:block;margin-bottom:8px;">Rạp / Giá vé</a>
            <a href="#" style="display:block;margin-bottom:8px;">FAQ</a>
        </div>

        <div>
            <div style="text-transform:uppercase;font-size:13px;margin-bottom:12px;font-weight:700;">LIÊN HỆ</div>
            <div class="footer-contact-item" style="display:flex;align-items:center;margin-bottom:8px;color:rgba(255,255,255,.75);"><img src="{{ asset('assets/images/footer/location-dot-solid-full.svg') }}" alt="" style="width:18px;height:18px;margin-right:10px;">180 Cao Lỗ, phường Chánh Hưng, tp.HCM</div>
            <div class="footer-contact-item" style="display:flex;align-items:center;margin-bottom:8px;color:rgba(255,255,255,.75);"><img src="{{ asset('assets/images/footer/phone-solid-full.svg') }}" alt="" style="width:18px;height:18px;margin-right:10px;">1900 1234</div>
            <div class="footer-contact-item" style="display:flex;align-items:center;margin-bottom:8px;color:rgba(255,255,255,.75);"><img src="{{ asset('assets/images/footer/message-solid-full.svg') }}" alt="" style="width:18px;height:18px;margin-right:10px;">support@tdcinema.com</div>
            <div class="footer-social" style="margin-top:10px;display:flex;gap:12px;">
                <a href="#"><img src="{{ asset('assets/images/footer/facebook-brands-solid-full.svg') }}" alt="Facebook" style="width:26px;height:26px;display:block;opacity:.85;"></a>
                <a href="#"><img src="{{ asset('assets/images/footer/instagram-brands-solid-full.svg') }}" alt="Instagram" style="width:26px;height:26px;display:block;opacity:.85;"></a>
                <a href="#"><img src="{{ asset('assets/images/footer/youtube-brands-solid-full.svg') }}" alt="Youtube" style="width:26px;height:26px;display:block;opacity:.85;"></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">© 2025 TD Cinema. All Rights Reserved. Designed for demonstration purposes.</div>
</footer>

<div class="overlay" id="authOverlay">
    <div class="modal" id="loginModal">
        <button class="close-btn" id="closeLogin">×</button>
        <h4>Đăng nhập</h4>
        <p>Đăng nhập để tiếp tục.</p>
        <form>
            <input type="email" placeholder="Email hoặc tên đăng nhập">
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

<script>
    const userToggle = document.getElementById('userToggle');
    const userMenu = document.getElementById('userMenu');
    const openLogin = document.getElementById('openLogin');
    const authOverlay = document.getElementById('authOverlay');
    const loginModal = document.getElementById('loginModal');
    const registerModal = document.getElementById('registerModal');
    const openRegister = document.getElementById('openRegister');
    const backLogin = document.getElementById('backLogin');
    const closeLogin = document.getElementById('closeLogin');
    const closeRegister = document.getElementById('closeRegister');

    userToggle?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (userMenu) userMenu.style.display = userMenu.style.display === 'block' ? 'none' : 'block';
    });
    document.addEventListener('click', (e) => {
        if (userMenu && userToggle && !userToggle.contains(e.target)) userMenu.style.display = 'none';
    });

    function showOverlay(target){
        if (!authOverlay) return;
        loginModal.style.display='none';
        registerModal.style.display='none';
        target.style.display='block';
        authOverlay.classList.add('active');
    }
    function closeOverlay(){ authOverlay?.classList.remove('active'); }
    openLogin?.addEventListener('click',(e)=>{ e.preventDefault(); showOverlay(loginModal); });
    openRegister?.addEventListener('click',(e)=>{ e.preventDefault(); showOverlay(registerModal); });
    backLogin?.addEventListener('click',(e)=>{ e.preventDefault(); showOverlay(loginModal); });
    closeLogin?.addEventListener('click', closeOverlay);
    closeRegister?.addEventListener('click', closeOverlay);
    authOverlay?.addEventListener('click',(e)=>{ if(e.target===authOverlay) closeOverlay(); });

    const seatButtons = Array.from(document.querySelectorAll('.seat:not(.sold):not(.inactive)'));
    const selectedList = document.getElementById('selectedList');
    const emptySelected = document.getElementById('emptySelected');
    const totalPriceEl = document.getElementById('totalPrice');
    const seatAlert = document.getElementById('seatAlert');
    const btnContinue = document.getElementById('btnContinue');
    const maxSeats = {{ $maxSelectable ?? 8 }};
    let selected = [];

    function currency(v){ return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; }
    function render(){
        selectedList.innerHTML='';
        if(selected.length===0){ emptySelected.style.display='block'; }
        else {
            emptySelected.style.display='none';
            selected.forEach(s=>{
                const chip=document.createElement('div');
                chip.className='chip';
                chip.textContent=`${s.code} (${currency(s.price)})`;
                selectedList.appendChild(chip);
            });
        }
        const total=selected.reduce((t,s)=>t+Number(s.price||0),0);
        totalPriceEl.textContent=currency(total);
    }
    function toggle(btn){
        const id=btn.dataset.seatId; const code=btn.dataset.code; const price=Number(btn.dataset.price||0);
        const idx=selected.findIndex(s=>s.id===id); seatAlert.style.display='none';
        if(idx>=0){ selected.splice(idx,1); btn.classList.remove('sel'); }
        else {
            if(selected.length>=maxSeats){ seatAlert.textContent=`Chỉ được chọn tối đa ${maxSeats} ghế.`; seatAlert.style.display='block'; return; }
            selected.push({id,code,price}); btn.classList.add('sel');
        }
        render();
    }
    seatButtons.forEach(btn=>btn.addEventListener('click',()=>toggle(btn)));
    const comboUrl = @json(route('booking.combo', $showtime));
    btnContinue?.addEventListener('click',()=>{
        if(selected.length===0){
            seatAlert.textContent='Vui lòng chọn ít nhất 1 ghế.';
            seatAlert.style.display='block';
            return;
        }
        const seatCodes = selected.map(s=>s.code).join(',');
        const target = seatCodes ? `${comboUrl}?seats=${encodeURIComponent(seatCodes)}` : comboUrl;
        window.location.href = target;
    });
    render();
</script>
</body>
</html>
