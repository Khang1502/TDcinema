@extends('admin.layouts.app')
@section('title', 'Quản lý Góc Điện Ảnh')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/banners.css') }}?v={{ filemtime(public_path('assets/css/admin/banners.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin/cinema-corner.css') }}?v={{ filemtime(public_path('assets/css/admin/cinema-corner.css')) }}">
@endpush
@section('content')
<div class="banner-admin corner-admin">
    <div class="banner-heading">
        <div><p class="banner-eyebrow">BÌNH LUẬN & BLOG ĐIỆN ẢNH</p><h2>Góc Điện Ảnh</h2><p>Quản lý bài viết và bài nổi bật riêng cho từng chuyên mục.</p></div>
        <div class="corner-heading-actions"><a class="banner-button" href="{{ route('home') }}#cinema-corner" target="_blank" rel="noopener">Xem trên trang chủ @include('admin.partials.icon',['name'=>'external-link','size'=>16])</a><a class="banner-button banner-button--primary" href="{{ route('admin.cinema-corner.create') }}">@include('admin.partials.icon',['name'=>'plus','size'=>16]) Thêm nội dung</a></div>
    </div>
    @if($errors->any())<div data-inline-notification class="banner-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <details class="banner-panel corner-settings" @if($errors->any()) open @endif>
        <summary><span><strong>Tiêu đề & bố cục khu vực</strong><small>Chỉnh lời giới thiệu, liên kết và số thẻ phim hiển thị.</small></span><span class="banner-status {{ $settings->is_active ? 'is-active' : '' }}">{{ $settings->is_active ? 'Đang hiển thị' : 'Đang ẩn' }}</span></summary>
        <form action="{{ route('admin.cinema-corner.settings') }}" method="POST" class="corner-settings-grid">
            @csrf @method('PUT')
            <div class="banner-field"><label for="corner-section-title">Tiêu đề khu vực</label><input id="corner-section-title" name="title" value="{{ old('title', $settings->title) }}" maxlength="120" required></div>
            <div class="banner-field"><label for="corner-eyebrow">Dòng giới thiệu phía trên</label><input id="corner-eyebrow" name="eyebrow" value="{{ old('eyebrow', $settings->eyebrow) }}" maxlength="120"></div>
            <div class="banner-field corner-field-wide"><label for="corner-description">Lời giới thiệu</label><textarea id="corner-description" name="description" rows="2" maxlength="300">{{ old('description', $settings->description) }}</textarea></div>
            <div class="banner-field"><label for="corner-label">Nhãn của bài nổi bật</label><input id="corner-label" name="featured_label" value="{{ old('featured_label', $settings->featured_label) }}" maxlength="60" required></div>
            <div class="banner-field"><label for="corner-link-label">Tên liên kết khám phá</label><input id="corner-link-label" name="link_label" value="{{ old('link_label', $settings->link_label) }}" maxlength="60"></div>
            <div class="banner-field corner-field-wide"><label for="corner-link">Liên kết khám phá</label><input id="corner-link" name="link_url" value="{{ old('link_url', $settings->link_url) }}" maxlength="2048" placeholder="/movies?status=all"><p class="banner-help">Để trống để ẩn liên kết. Có thể dùng đường dẫn trong web hoặc https://...</p></div>
            <div class="banner-field"><label for="corner-limit">Số bài bên cạnh trong mỗi tab</label><select id="corner-limit" name="list_limit">@for($i=1;$i<=6;$i++)<option value="{{ $i }}" @selected((int) old('list_limit', $settings->list_limit) === $i)>{{ $i }} bài</option>@endfor</select></div>
            <div class="banner-field"><label for="corner-section-active">Trạng thái khu vực</label><select id="corner-section-active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $settings->is_active) === '1')>Hiển thị trên trang chủ</option><option value="0" @selected((string) old('is_active', (int) $settings->is_active) === '0')>Ẩn toàn bộ Góc Điện Ảnh</option></select></div>
            <div class="corner-field-wide"><button class="banner-button banner-button--primary" type="submit">Lưu bố cục & tiêu đề</button></div>
        </form>
    </details>
    <div class="banner-toolbar">
        <div class="banner-counts"><span><strong>{{ $total }}</strong> nội dung</span><span><strong>{{ $active }}</strong> đang bật</span></div>
        <nav class="corner-category-filter" aria-label="Chuyên mục bài viết"><a href="{{ route('admin.cinema-corner.index') }}" @class(['is-active' => !$category])>Tất cả</a>@foreach(\App\Models\CinemaCornerItem::CATEGORIES as $key => $label)<a href="{{ route('admin.cinema-corner.index', ['category' => $key]) }}" @class(['is-active' => $category === $key])>{{ $label }}</a>@endforeach</nav>
        <form id="corner-order-form" action="{{ route('admin.cinema-corner.order') }}" method="POST">@csrf @method('PATCH')<button class="banner-button" type="submit" @disabled($items->isEmpty())>Lưu thứ tự</button></form>
    </div>
    <p class="banner-help">Chọn “Bài nổi bật” khi chỉnh sửa để đưa nội dung vào ô lớn của chuyên mục. Các bài bên cạnh hiển thị theo thứ tự từ nhỏ đến lớn.</p>
    <div class="corner-item-grid">
        @forelse($items as $item)
            <article class="banner-panel corner-item-card" data-corner-admin-item="{{ $item->id }}">
                <div class="corner-item-main">
                    <img class="corner-item-image" src="{{ $item->imageUrl() }}" alt="{{ $item->displayTitle() }}" loading="lazy">
                    <div class="corner-item-copy">
                        <div class="corner-item-badges"><span class="corner-featured-badge">{{ $item->categoryLabel() }}</span><span class="banner-status {{ $item->is_active ? 'is-active' : '' }}">{{ !$item->movie && !$item->content ? 'Phim đã xóa' : ($item->category === 'review' && $item->movie?->status === 'inactive' ? 'Phim ngừng chiếu' : ($item->is_active ? 'Đang bật' : 'Đang ẩn')) }}</span>@if(($item->category === 'blog' ? $settings->blog_featured_item_id : $settings->featured_item_id) === $item->id)<span class="corner-featured-badge">Bài nổi bật</span>@endif</div>
                        <h3>{{ $item->displayTitle() }}</h3>
                        @if($item->title && $item->movie)<p class="banner-help">Phim: {{ $item->movie->title }}</p>@endif
                        <p class="corner-item-excerpt">{{ $item->displayExcerpt(150) }}</p>
                        <p class="banner-help">{{ number_format($item->views_count) }} lượt xem · {{ $item->likes_count }} lượt thích</p>
                    </div>
                </div>
                <div class="corner-item-footer">
                    <label class="corner-order">Thứ tự <input type="number" name="orders[{{ $item->id }}]" value="{{ old('orders.'.$item->id, $item->sort_order) }}" form="corner-order-form" min="0" max="9999" aria-label="Thứ tự {{ $item->displayTitle() }}" required></label>
                    <div class="banner-card-actions">
                        <a class="banner-button" href="{{ route('cinema-corner.show', $item) }}" target="_blank" rel="noopener">Đọc bài @include('admin.partials.icon',['name'=>'external-link','size'=>16])</a>
                        <a class="banner-button" href="{{ route('admin.cinema-corner.edit', $item) }}">Chỉnh sửa</a>
                        <form action="{{ route('admin.cinema-corner.visibility', $item) }}" method="POST">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $item->is_active ? 0 : 1 }}"><button class="banner-button" type="submit">{{ $item->is_active ? 'Ẩn' : 'Bật' }}</button></form>
                        <form action="{{ route('admin.cinema-corner.destroy', $item) }}" method="POST" data-confirm="Xóa nội dung này khỏi Góc Điện Ảnh?">@csrf @method('DELETE')<button class="banner-button banner-button--danger" type="submit">Xóa</button></form>
                    </div>
                </div>
            </article>
        @empty
            <div class="banner-empty"><h3>Chưa có nội dung</h3><p>Thêm phim đầu tiên để hiển thị Góc Điện Ảnh trên trang chủ.</p><a class="banner-button banner-button--primary" href="{{ route('admin.cinema-corner.create') }}">@include('admin.partials.icon',['name'=>'plus','size'=>16]) Thêm nội dung</a></div>
        @endforelse
    </div>
    <div class="banner-pagination">{{ $items->links() }}</div>
</div>
@endsection
