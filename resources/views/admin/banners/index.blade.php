@extends('admin.layouts.app')
@section('title', 'Quản lý banner')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/banners.css') }}?v={{ filemtime(public_path('assets/css/admin/banners.css')) }}">
@endpush

@section('content')
<div class="banner-admin">
    <div class="banner-heading">
        <div><p class="banner-eyebrow">QUẢN LÝ NỘI DUNG</p><h2>Quản lý banner</h2><p>Quản lý riêng banner trang chủ và banner Rạp/Giá vé: hình ảnh, liên kết, thứ tự và trạng thái.</p></div>
        <a class="banner-button banner-button--primary" href="{{ route('admin.banners.create', array_filter(['placement' => $placement])) }}">@include('admin.partials.icon',['name'=>'plus','size'=>16]) Thêm banner</a>
    </div>
    <nav class="banner-placement-tabs" aria-label="Lọc banner theo vị trí">
        <a href="{{ route('admin.banners.index') }}" class="{{ $placement === '' ? 'active' : '' }}">Tất cả</a>
        @foreach(\App\Models\Banner::PLACEMENTS as $key => $label)<a href="{{ route('admin.banners.index', ['placement' => $key]) }}" class="{{ $placement === $key ? 'active' : '' }}">{{ $label }} <span>{{ $placementCounts[$key] ?? 0 }}</span></a>@endforeach
    </nav>
    <div class="banner-toolbar">
        <div class="banner-counts"><span><strong>{{ $total }}</strong> banner</span><span class="banner-status is-active">{{ $active }} đang hiển thị</span><span>{{ $total - $active }} đang ẩn</span></div>
        @if($banners->isNotEmpty())
            <form id="banner-order-form" method="POST" action="{{ route('admin.banners.order') }}">
                @csrf @method('PATCH')
                @if($placement)<input type="hidden" name="placement" value="{{ $placement }}">@endif
                <button class="banner-button" type="submit">Lưu thứ tự</button>
            </form>
        @endif
    </div>
    @if($errors->any())
        <div data-inline-notification class="banner-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif
    <p class="banner-help">Thứ tự nhỏ hiển thị trước. Thay đổi số thứ tự rồi bấm “Lưu thứ tự”.</p>
    <div class="banner-grid">
        @forelse($banners as $banner)
            <article class="banner-card" data-banner-id="{{ $banner->id }}" data-banner-placement="{{ $banner->placement }}">
                <a class="banner-card-image" href="{{ route('admin.banners.edit', $banner) }}" aria-label="Chỉnh sửa banner {{ $banner->title }}">
                    <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" loading="lazy">
                </a>
                <div class="banner-card-content">
                    <div class="banner-card-title"><h3>{{ $banner->title }}</h3><span class="banner-status {{ $banner->is_active ? 'is-active' : '' }}">{{ $banner->is_active ? 'Đang hiển thị' : 'Đang ẩn' }}</span></div>
                    <span class="banner-placement-label">{{ $banner->placement_label }}</span>
                    <p class="banner-card-link">@if($banner->destinationUrl())<a href="{{ $banner->destinationUrl() }}" target="_blank" rel="noopener">{{ $banner->link_url ?: ($banner->cinema?->name ?: $banner->movie?->title) }} @include('admin.partials.icon',['name'=>'external-link','size'=>16])</a>@else Không gắn liên kết @endif</p>
                    <div class="banner-card-order">
                        <label for="banner-order-{{ $banner->id }}">Thứ tự</label>
                        <input id="banner-order-{{ $banner->id }}" type="number" form="banner-order-form" name="orders[{{ $banner->id }}]" value="{{ old('orders.'.$banner->id, $banner->sort_order) }}" min="0" max="9999" required>
                    </div>
                    <div class="banner-card-actions">
                        <a class="banner-button" href="{{ route('admin.banners.edit', $banner) }}">Chỉnh sửa</a>
                        <form method="POST" action="{{ route('admin.banners.visibility', $banner) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $banner->is_active ? '0' : '1' }}">
                            <button class="banner-button" type="submit">{{ $banner->is_active ? 'Ẩn banner' : 'Hiện banner' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" data-confirm="Bạn có chắc muốn xóa banner này?">
                            @csrf @method('DELETE')
                            <button class="banner-button banner-button--danger" type="submit">Xóa</button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="banner-empty"><h3>Chưa có banner nào</h3><p>Thêm banner đầu tiên cho vị trí hiển thị đã chọn.</p><a class="banner-button banner-button--primary" href="{{ route('admin.banners.create', array_filter(['placement' => $placement])) }}">Thêm banner</a></div>
        @endforelse
    </div>
    <div class="banner-pagination">{{ $banners->links() }}</div>
</div>
@endsection
