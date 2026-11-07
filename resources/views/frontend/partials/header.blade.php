@php $headerSettings = \App\Models\HeaderSetting::current(); @endphp
<header class="site-header td-site-header">
    <div class="header-container">
        <div class="header-left">
            <a href="{{ route('home') }}" class="logo" aria-label="Về trang chủ TD Cinema">
                <img src="{{ $headerSettings->logoUrl() }}" alt="{{ $headerSettings->brand_name }}">
                <span>{{ $headerSettings->brand_name }}</span>
            </a>
            @include('frontend.partials.header-navigation')
        </div>

        <div class="header-right">
            @if($headerSettings->show_search) @include('frontend.partials.movie-search', [
                'searchId' => $searchId ?? 'header-movie-search',
                'cinemaId' => $cinemaId ?? null,
            ]) @endif

            @auth
                <div class="user-info" id="userMenuToggle" role="button" tabindex="0" aria-label="Mở menu tài khoản" aria-controls="userMenu" aria-expanded="false">
                    <div class="user-avatar">
                        @if(auth()->user()->avatar ?? false)
                            <img src="{{ asset('storage/' . ltrim(auth()->user()->avatar, '/')) }}" alt="Ảnh đại diện">
                        @else
                            {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        @endif
                    </div>
                    <div class="user-meta">
                        <span class="user-name">{{ auth()->user()->name ?? 'User' }}</span>
                        <span class="user-tier">{{ auth()->user()->role === 'customer' ? (auth()->user()->member_tier?->name ?? 'Thành viên TD') : auth()->user()->adminRoleLabel() }} · {{ number_format(auth()->user()->points ?? 0,0,',','.') }} Stars</span>
                    </div>
                    <span class="user-chevron" aria-hidden="true">▾</span>
                    <div class="user-menu" id="userMenu">
                        @if(in_array(auth()->user()->role ?? null, ['admin', 'manager', 'staff'], true))
                            <a href="{{ route('admin.dashboard') }}">Về trang admin</a>
                        @else
                            <a href="{{ route('profile.edit') }}">Tài khoản</a>
                            <a href="{{ route('customer.membership') }}">Thành viên & quyền lợi</a>
                            <a href="{{ route('customer.tickets') }}">Lịch sử vé</a>
                            <a href="{{ route('customer.gifts') }}">Quà tặng</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit">Đăng xuất</button>
                        </form>
                    </div>
                </div>
            @else
                @if($headerSettings->show_membership)<div class="join-wrapper" id="joinWrapper">
                    <a href="#" class="header-membership" id="joinToggle" aria-controls="joinPopover" aria-expanded="false">{{ $headerSettings->join_label }}</a>
                    <div class="join-popover" id="joinPopover">
                        <div class="join-grid">
                            <div class="join-card">
                                <img src="{{ asset('assets/images/membership/rule.png') }}" alt="Thể lệ">
                                <h4>Thể lệ</h4>
                                <p>Xem quy định tham gia</p>
                                <a href="{{ route('membership.index') }}#terms" class="btn btn-outline">Chi tiết</a>
                            </div>
                            <div class="join-card">
                                <img src="{{ asset('assets/images/membership/benefit.png') }}" alt="Quyền lợi">
                                <h4>Quyền lợi</h4>
                                <p>Ưu đãi dành cho hội viên</p>
                                <a href="{{ route('membership.index') }}#member-benefits" class="btn btn-outline">Chi tiết</a>
                            </div>
                            <div class="join-card">
                                <img src="{{ asset('assets/images/membership/guide.png') }}" alt="Hướng dẫn">
                                <h4>Hướng dẫn</h4>
                                <p>Cách sử dụng điểm Stars</p>
                                <a href="{{ route('membership.index') }}#how-it-works" class="btn btn-outline">Chi tiết</a>
                            </div>
                            <div class="join-card">
                                <img src="{{ asset('assets/images/membership/join.png') }}" alt="Đăng ký thành viên">
                                <h4>Đăng ký thành viên</h4>
                                <p>Nhận ưu đãi ngay hôm nay</p>
                                <button type="button" class="btn btn-primary" id="openRegister">Đăng ký</button>
                            </div>
                        </div>
                    </div>
                </div>@endif
                <a href="#" class="header-login" id="openLogin">{{ $headerSettings->login_label }}</a>
            @endauth
        </div>
    </div>
</header>
<script src="{{ asset('assets/js/frontend/header.js') }}?v={{ filemtime(public_path('assets/js/frontend/header.js')) }}" defer></script>
