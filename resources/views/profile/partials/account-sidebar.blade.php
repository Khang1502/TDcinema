<aside class="card profile-sidebar">
    <div class="side-header">
        <div class="avatar">
            @if(auth()->user()->avatar ?? false)
                <img src="{{ asset('storage/' . ltrim(auth()->user()->avatar, '/')) }}" alt="Ảnh đại diện">
            @else
                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
            @endif
        </div>
        <div>
            <p class="side-name">{{ auth()->user()->name ?? 'Người dùng' }}</p>
            <p class="side-tier">{{ auth()->user()->member_tier?->name ?? 'Thành viên TD' }} · {{ number_format(auth()->user()->points ?? 0,0,',','.') }} Stars</p>
        </div>
    </div>
    <nav class="side-menu" aria-label="Tài khoản">
        <a href="{{ route('profile.edit') }}" @class(['active' => ($active ?? '') === 'profile'])>Thông tin cá nhân</a>
        <a href="{{ route('customer.membership') }}" @class(['active' => ($active ?? '') === 'membership'])>Thành viên & quyền lợi</a>
        <a href="{{ route('customer.tickets') }}" @class(['active' => ($active ?? '') === 'tickets'])>Lịch sử đặt vé</a>
        <a href="{{ route('customer.notifications') }}" @class(['active' => ($active ?? '') === 'notifications'])>Thông báo</a>
        <a href="{{ route('customer.gifts') }}" @class(['active' => ($active ?? '') === 'gifts'])>Quà tặng & ưu đãi</a>
        <a href="{{ route('customer.policy') }}" @class(['active' => ($active ?? '') === 'policy'])>Chính sách</a>
    </nav>
    <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
        @csrf
        <button type="submit" class="logout-link">Đăng xuất</button>
    </form>
</aside>
