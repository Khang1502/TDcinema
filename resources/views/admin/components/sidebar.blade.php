@php $adminBrand = $adminBrand ?? \App\Models\HeaderSetting::current(); @endphp
<aside class="admin-sidebar" id="admin-sidebar">
    <div class="sidebar-brand">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand__link">
            <img src="{{ $adminBrand->logoUrl() }}" class="sidebar-brand__logo" width="48" height="48" alt="Logo {{ $adminBrand->brand_name }}">
            <span><strong>{{ $adminBrand->brand_name }}</strong><small>Management Center</small></span>
        </a>
        <button type="button" class="sidebar-close" data-sidebar-close aria-label="Đóng menu">@include('admin.partials.icon', ['name'=>'x', 'size'=>18])</button>
    </div>
    @php
        $menuGroups = [
            'Tổng quan' => [
                ['route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'label' => 'Bảng điều khiển', 'icon' => 'dashboard'],
                ['route' => 'admin.reports.index', 'pattern' => 'admin.reports.*', 'label' => 'Doanh thu & báo cáo', 'icon' => 'chart-no-axes-combined', 'permission' => 'reports.view'],
                ['route' => 'admin.website.index', 'pattern' => 'admin.website.*', 'label' => 'Quản lý website', 'icon' => 'globe'],
            ],
            'Giao diện và nội dung' => [
                ['route' => 'admin.header.edit', 'pattern' => 'admin.header.*', 'label' => 'Header & website', 'icon' => 'panel-top', 'permission' => 'header.manage'],
                ['route' => 'admin.banners.index', 'pattern' => 'admin.banners.*', 'label' => 'Quản lý banner', 'icon' => 'image', 'permission' => 'banners.manage'],
                ['route' => 'admin.footer.edit', 'pattern' => 'admin.footer.*', 'label' => 'Quản lý footer', 'icon' => 'panel-bottom', 'permission' => 'footer.manage'],
                ['route' => 'admin.movies.index', 'pattern' => 'admin.movies.*', 'label' => 'Phim', 'icon' => 'film', 'permission' => 'movies.manage'],
                ['route' => 'admin.monthly-movies.index', 'pattern' => 'admin.monthly-movies.*', 'label' => 'Phim hay tháng', 'icon' => 'trending-up', 'permission' => 'movies.manage'],
                ['route' => 'admin.genres.index', 'pattern' => 'admin.genres.*', 'label' => 'Thể loại phim', 'icon' => 'tags', 'permission' => 'movies.manage'],
                ['route' => 'admin.cinema-corner.index', 'pattern' => 'admin.cinema-corner.*', 'label' => 'Góc Điện Ảnh', 'icon' => 'newspaper', 'permission' => 'cinema-corner.manage'],
                ['route' => 'admin.showtimes.index', 'pattern' => 'admin.showtimes.*', 'exclude' => 'admin.showtimes.schedule', 'label' => 'Suất chiếu', 'icon' => 'calendar-clock', 'permission' => 'showtimes.manage'],
                ['route' => 'admin.showtimes.schedule', 'pattern' => 'admin.showtimes.schedule', 'label' => 'Lịch chiếu', 'icon' => 'calendar', 'permission' => 'showtimes.manage'],
            ],
            'Vé & check-in' => [
                ['route' => 'admin.tickets.index', 'pattern' => 'admin.tickets.*', 'label' => 'Quản lý vé', 'icon' => 'ticket', 'permission' => 'tickets.manage'],
                ['route' => 'admin.food-orders.index', 'pattern' => 'admin.food-orders.*', 'label' => 'Quản lý bắp nước', 'icon' => 'bag', 'permission' => 'food-sales.manage'],
            ],
            'Vận hành' => [
                ['route' => 'admin.payments.index', 'pattern' => 'admin.payments.*', 'label' => 'Lịch sử thanh toán', 'icon' => 'wallet', 'permission' => 'payments.manage'],
                ['route' => 'admin.payment-methods.index', 'pattern' => 'admin.payment-methods.*', 'label' => 'Phương thức thanh toán', 'icon' => 'settings', 'permission' => 'payment-methods.manage'],
                ['route' => 'admin.cinemas.index', 'pattern' => 'admin.cinemas.*', 'label' => 'Hệ thống rạp', 'icon' => 'building', 'permission' => 'cinemas.manage'],
                ['route' => 'admin.rooms.index', 'pattern' => 'admin.rooms.*', 'label' => 'Phòng chiếu', 'icon' => 'projector', 'permission' => 'rooms.manage'],
                ['route' => 'admin.seats.index', 'pattern' => 'admin.seats.*', 'label' => 'Sơ đồ ghế', 'icon' => 'seat', 'permission' => 'seats.manage'],
            ],
            'Khách hàng & bán hàng' => [
                ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Người dùng', 'icon' => 'users', 'permission' => 'users.view'],
                ['route' => 'admin.membership.index', 'pattern' => 'admin.membership.*', 'label' => 'Thành viên & quyền lợi', 'icon' => 'crown', 'permission' => 'membership.manage'],
                ['route' => 'admin.gifts.index', 'pattern' => 'admin.gifts.*', 'label' => 'Quà tặng thành viên', 'icon' => 'gift', 'permission' => 'gifts.manage'],
                ['route' => 'admin.promotions.index', 'pattern' => 'admin.promotions.*', 'label' => 'Khuyến mãi', 'icon' => 'tags', 'permission' => 'promotions.manage'],
                ['route' => 'admin.foods.index', 'pattern' => 'admin.foods.*', 'label' => 'Dịch vụ & đồ ăn', 'icon' => 'bag', 'permission' => 'foods.manage'],
                ['route' => 'admin.permissions.index', 'pattern' => 'admin.permissions.*', 'label' => 'Phân quyền admin', 'icon' => 'shield-check', 'permission' => 'permissions.manage'],
            ],
        ];
    @endphp
    <nav class="sidebar-nav" aria-label="Điều hướng quản trị">
        @foreach ($menuGroups as $group => $items)
            @php
                $visibleItems = collect($items)->filter(fn ($item) =>
                    !isset($item['permission']) || auth()->user()->hasAdminPermission($item['permission'])
                );
            @endphp
            @continue($visibleItems->isEmpty())
            <div class="sidebar-group">
                <p class="sidebar-group__title">{{ $group }}</p>
                @foreach ($visibleItems as $item)
                    @php
                        $active = request()->routeIs($item['pattern']) && (!isset($item['exclude']) || !request()->routeIs($item['exclude']));
                        if (request()->routeIs('admin.users.*') && (request()->boolean('admin_only') || request('from') === 'permissions' || old('return_to', request('return_to')) === 'permissions')) {
                            $active = $item['route'] === 'admin.permissions.index';
                        }
                    @endphp
                    <a href="{{ route($item['route']) }}" class="sidebar-link {{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>
                        <span class="sidebar-link__icon">{!! view('admin.partials.icon', ['name' => $item['icon'], 'size' => 19, 'classes' => '']) !!}</span>
                        <span>{{ $item['label'] }}</span>
                        @if ($active)<span class="sidebar-link__dot"></span>@endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
    <div class="sidebar-user">
        <div class="sidebar-user__avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'A', 0, 1)) }}</div>
        <div class="sidebar-user__info"><strong>{{ auth()->user()->name ?? 'Quản trị viên' }}</strong><small>{{ auth()->user()->adminRoleLabel() }} · {{ auth()->user()->email }}</small></div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="sidebar-logout" type="submit" title="Đăng xuất" aria-label="Đăng xuất">{!! view('admin.partials.icon', ['name' => 'logout', 'size' => 18, 'classes' => '']) !!}</button>
        </form>
    </div>
</aside>
