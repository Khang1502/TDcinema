<header class="admin-topbar">
    @php $adminBrand = $adminBrand ?? \App\Models\HeaderSetting::current(); @endphp
    <div class="admin-topbar__left">
        <a class="admin-mobile-brand" href="{{ route('admin.dashboard') }}" aria-label="Bảng điều khiển {{ $adminBrand->brand_name }}"><img src="{{ $adminBrand->logoUrl() }}" width="38" height="38" alt="Logo {{ $adminBrand->brand_name }}"></a>
        <button class="mobile-menu-button" type="button" data-sidebar-open aria-label="Mở menu">@include('admin.partials.icon', ['name'=>'menu', 'size'=>18])</button>
        <div>
            <p class="admin-breadcrumb"><span>TD Cinema</span><b>/</b>@yield('title', 'Bảng điều khiển')</p>
            <h1>@yield('title', 'Bảng điều khiển')</h1>
        </div>
    </div>
    <div class="admin-topbar__right">
        <span class="admin-role-badge">{{ auth()->user()->adminRoleLabel() }}</span>
        <div class="admin-date"><span class="admin-date__dot"></span><span>{{ now()->format('d/m/Y') }}</span></div>
        <a href="{{ route('home') }}" class="admin-view-site" aria-label="Về trang khách">Về trang khách <span aria-hidden="true">@include('admin.partials.icon', ['name'=>'arrow-right', 'size'=>18])</span></a>
        <a href="{{ route('admin.website.index') }}" class="admin-view-site" aria-label="Quản lý website">@include('admin.partials.icon', ['name'=>'globe', 'size'=>18]) Quản lý website</a>
    </div>
</header>
