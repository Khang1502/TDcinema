@extends('admin.layouts.app')
@section('title', $banner->exists ? 'Chỉnh sửa banner' : 'Thêm banner')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/banners.css') }}?v={{ filemtime(public_path('assets/css/admin/banners.css')) }}">
@endpush
@push('scripts')
    <script src="{{ asset('assets/js/admin/banners.js') }}?v={{ filemtime(public_path('assets/js/admin/banners.js')) }}" defer></script>
@endpush

@section('content')
@php
    $source = old('image_source', $banner->cinema && $banner->image_path === $banner->cinema->image_path ? 'cinema' : ($banner->movie && $banner->image_path === $banner->movie->banner_path ? 'movie' : (!$banner->exists && $banner->placement === 'cinemas' ? 'cinema' : 'upload')));
    $listUrl = route('admin.banners.index', ['placement' => old('placement', $banner->placement ?? 'home')]);
@endphp
<div class="banner-admin">
    <div class="banner-heading">
        <div><p class="banner-eyebrow">QUẢN LÝ NỘI DUNG</p><h2>{{ $banner->exists ? 'Chỉnh sửa banner' : 'Thêm banner mới' }}</h2><p>Chọn vị trí hiển thị và ảnh banner. Mỗi trang có danh sách banner riêng.</p></div>
        <a class="banner-button" href="{{ $listUrl }}">@include('admin.partials.icon',['name'=>'arrow-left','size'=>16]) Danh sách banner</a>
    </div>
    @if($errors->any())
        <div data-inline-notification class="banner-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif
    <form class="banner-editor" data-banner-editor method="POST" enctype="multipart/form-data" action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}">
        @csrf
        @if($banner->exists) @method('PUT') @endif
        <div class="banner-editor-fields banner-panel">
            <div class="banner-field"><label for="banner-title">Tên banner</label><input id="banner-title" name="title" value="{{ old('title', $banner->title) }}" maxlength="150" placeholder="Nhập tên phim hoặc rạp" required></div>
            <div class="banner-field"><label for="banner-placement">Vị trí hiển thị</label><select id="banner-placement" name="placement">@foreach(\App\Models\Banner::PLACEMENTS as $key => $label)<option value="{{ $key }}" @selected(old('placement', $banner->placement ?? 'home') === $key)>{{ $label }}</option>@endforeach</select><p class="banner-help">Banner chỉ hiển thị trên trang được chọn.</p></div>
            <div class="banner-field"><label for="banner-source">Nguồn ảnh</label><select id="banner-source" name="image_source"><option value="upload" @selected($source === 'upload')>Tải ảnh lên</option><option value="movie" @selected($source === 'movie')>Dùng ảnh banner của phim</option><option value="cinema" @selected($source === 'cinema')>Dùng ảnh của rạp</option></select></div>
            <div class="banner-field" data-banner-upload @if($source !== 'upload') hidden @endif>
                <label for="banner-image">Ảnh banner</label>
                <div class="banner-file-picker">
                    <span class="banner-file-button">Chọn tệp</span><span class="banner-file-name" data-banner-file-name>Chưa chọn tệp</span>
                    <input id="banner-image" type="file" name="image" class="banner-file-native" accept="image/jpeg,image/png,image/webp" aria-label="Chọn ảnh banner" @if(!$banner->exists && $source === 'upload') required @endif>
                </div>
                <p class="banner-help">JPG, PNG hoặc WebP, tối đa 4 MB. Nên dùng ảnh ngang, rộng từ 1200 px.</p>
                @if($banner->exists)<p class="banner-help">Không chọn ảnh mới sẽ giữ nguyên ảnh hiện tại.</p>@endif
            </div>
            <div class="banner-field" data-banner-movie @if($source === 'cinema') hidden @endif>
                <label for="banner-movie">Phim liên kết <span>(tùy chọn khi tải ảnh lên)</span></label>
                <select id="banner-movie" name="movie_id" @if($source === 'movie') required @endif>
                    <option value="">— Chọn phim —</option>
                    @foreach($movies as $movie)
                        <option value="{{ $movie->id }}" data-image="{{ \App\Support\ImageAsset::url($movie->banner_path) }}" data-image-path="{{ $movie->banner_path }}" @selected((string) old('movie_id', $banner->movie_id) === (string) $movie->id)>{{ $movie->title }}{{ $movie->banner_path ? '' : ' (chưa có ảnh banner)' }}</option>
                    @endforeach
                </select>
                <p class="banner-help">Khi bấm banner, khách sẽ đến trang của phim này.</p>
            </div>
            <div class="banner-field" data-banner-cinema @if($source !== 'cinema') hidden @endif>
                <label for="banner-cinema">Rạp liên kết</label>
                <select id="banner-cinema" name="cinema_id" @if($source === 'cinema') required @endif>
                    <option value="">— Chọn rạp —</option>
                    @foreach($cinemas as $cinema)
                        <option value="{{ $cinema->id }}" data-image="{{ $cinema->hasImage() ? $cinema->imageUrl() : '' }}" data-image-path="{{ $cinema->image_path }}" @selected((string) old('cinema_id', $banner->cinema_id) === (string) $cinema->id)>{{ $cinema->name }}{{ $cinema->hasImage() ? '' : ' (chưa có ảnh)' }}</option>
                    @endforeach
                </select>
                <p class="banner-help">Dùng ảnh rạp đã tải lên. Bấm banner sẽ chuyển đến rạp này trong danh sách.</p>
            </div>
            <div class="banner-field"><label for="banner-link">Liên kết khác <span>(tùy chọn)</span></label><input id="banner-link" name="link_url" value="{{ old('link_url', $banner->link_url) }}" maxlength="2048" placeholder="/rap-gia-ve hoặc https://..."><p class="banner-help">Nếu nhập liên kết này, banner sẽ mở liên kết đó thay cho phim hoặc rạp đã chọn.</p></div>
            <div class="banner-field-row">
                <div class="banner-field"><label for="banner-order">Thứ tự hiển thị</label><input id="banner-order" type="number" name="sort_order" value="{{ old('sort_order', $banner->sort_order) }}" min="0" max="9999" required><p class="banner-help">Số nhỏ hiển thị trước.</p></div>
                <div class="banner-field"><label for="banner-active">Trạng thái</label><select id="banner-active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $banner->is_active) === '1')>Hiển thị trên trang đã chọn</option><option value="0" @selected((string) old('is_active', (int) $banner->is_active) === '0')>Ẩn banner</option></select></div>
            </div>
            <div class="banner-form-actions"><button class="banner-button banner-button--primary" type="submit">{{ $banner->exists ? 'Lưu thay đổi' : 'Thêm banner' }}</button><a class="banner-button" href="{{ $listUrl }}">Hủy</a></div>
        </div>
        <aside class="banner-panel banner-preview-panel">
            <h3>Xem trước banner</h3><p class="banner-help">Khung ảnh theo vị trí đã chọn, hiển thị đầy đủ nội dung.</p>
            <div class="banner-image-preview"><img data-banner-image-preview @if($banner->exists) src="{{ $banner->imageUrl() }}" @endif data-current-image="{{ $banner->exists ? $banner->imageUrl() : '' }}" alt="Ảnh banner xem trước" @if(!$banner->exists) hidden @endif><span data-banner-placeholder @if($banner->exists) hidden @endif>Chọn ảnh, phim hoặc rạp để xem trước</span></div>
            <p class="banner-help" data-banner-image-info></p>
            @include('admin.partials.image-source', ['imagePath' => $banner->image_path, 'imageUrl' => $banner->exists ? $banner->imageUrl() : null, 'sourceKey' => 'banner-preview'])
            <strong data-banner-title-preview>{{ old('title', $banner->title) ?: 'Tên banner' }}</strong>
        </aside>
    </form>
</div>
@endsection
