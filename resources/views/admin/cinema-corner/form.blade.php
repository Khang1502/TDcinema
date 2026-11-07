@extends('admin.layouts.app')
@section('title', $item->exists ? 'Chỉnh sửa Góc Điện Ảnh' : 'Thêm nội dung Góc Điện Ảnh')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/banners.css') }}?v={{ filemtime(public_path('assets/css/admin/banners.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin/cinema-corner.css') }}?v={{ filemtime(public_path('assets/css/admin/cinema-corner.css')) }}">
@endpush
@push('scripts')<script src="{{ asset('assets/js/admin/cinema-corner.js') }}?v={{ filemtime(public_path('assets/js/admin/cinema-corner.js')) }}" defer></script>@endpush
@section('content')
@php
    $source = old('image_source', $item->image_path ? 'upload' : 'movie');
    $category = old('category', $item->category ?? 'review');
    $featuredId = $category === 'blog' ? $settings->blog_featured_item_id : $settings->featured_item_id;
    $placement = old('placement', ($item->exists && $featuredId === $item->id) || (!$item->exists && !$featuredId) ? 'featured' : 'list');
    $editorItem = clone $item;
    $editorItem->setRelation('movie', $movies->firstWhere('id', (int) old('movie_id', $item->movie_id)));
    $editorItem->content = old('content', $item->content);
@endphp
<div class="banner-admin corner-admin">
    <div class="banner-heading"><div><p class="banner-eyebrow">GÓC ĐIỆN ẢNH</p><h2>{{ $item->exists ? 'Chỉnh sửa nội dung' : 'Thêm nội dung mới' }}</h2><p>Dùng thông tin có sẵn của phim hoặc viết tiêu đề, mô tả và chọn ảnh riêng.</p></div><a class="banner-button" href="{{ route('admin.cinema-corner.index') }}">@include('admin.partials.icon',['name'=>'arrow-left','size'=>16]) Danh sách nội dung</a></div>
    @if($errors->any())<div data-inline-notification class="banner-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form class="banner-editor" data-corner-editor action="{{ $item->exists ? route('admin.cinema-corner.update', $item) : route('admin.cinema-corner.store') }}" method="POST" enctype="multipart/form-data">
        @csrf @if($item->exists) @method('PUT') @endif
        <div class="banner-panel banner-editor-fields">
            <div class="banner-field"><label for="corner-category">Chuyên mục</label><select id="corner-category" name="category">@foreach(\App\Models\CinemaCornerItem::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="banner-field"><label for="corner-movie">Phim liên kết <span>(tùy chọn)</span></label><select id="corner-movie" name="movie_id"><option value="">— Bài viết không liên kết phim —</option>@foreach($movies as $movie)
                @php $sample = new \App\Models\CinemaCornerItem(); $sample->setRelation('movie', $movie); @endphp
                <option value="{{ $movie->id }}" data-title="{{ $movie->title }}" data-description="{{ $sample->editorExcerpt() }}" data-content="{{ $sample->editorContent() }}" data-poster="{{ $sample->imageUrl() }}" data-banner="{{ $sample->imagePath(true) ? $sample->imageUrl(true) : '' }}" data-image-path="{{ $sample->imagePath(true) }}" data-status="{{ $movie->status }}" data-duration="{{ $movie->duration }}" data-trailer="{{ $movie->trailer_source }}" @selected((string) old('movie_id', $item->movie_id) === (string) $movie->id)>{{ $movie->title }}{{ $movie->status === 'inactive' ? ' (ngừng chiếu)' : '' }}</option>
            @endforeach</select><p class="banner-help">Liên kết phim để dùng ảnh, trailer và nút đặt vé. Bài viết độc lập cần tiêu đề, nội dung và ảnh riêng.</p></div>
            <div class="banner-field"><label for="corner-placement">Vị trí hiển thị</label><select id="corner-placement" name="placement"><option value="featured" @selected($placement === 'featured')>Bài nổi bật — ô lớn</option><option value="list" @selected($placement === 'list')>Bài viết bên cạnh</option></select><p class="banner-help">Mỗi chuyên mục có một bài nổi bật riêng. Chọn ô lớn sẽ thay bài nổi bật của chuyên mục đó.</p></div>
            <div class="banner-field"><label for="corner-item-title">Tiêu đề bài viết</label><input id="corner-item-title" name="title" value="{{ old('title', $item->title) }}" maxlength="191" placeholder="Bắt buộc cho bài độc lập; có thể để trống để dùng tên phim"></div>
            <div class="banner-field"><label for="corner-excerpt">Mô tả <span>(tùy chọn)</span></label><textarea id="corner-excerpt" name="excerpt" data-inherited="{{ blank(old('excerpt', $item->excerpt)) ? '1' : '0' }}" maxlength="2000" rows="5" placeholder="Nhập mô tả ngắn hoặc chọn phim để điền nội dung có sẵn">{{ old('excerpt', $editorItem->editorExcerpt()) }}</textarea><p class="banner-help">Hiển thị sẵn mô tả đang dùng trên trang chủ. Bạn có thể chỉnh sửa tại đây; không thay đổi mô tả ở trang chi tiết phim.</p></div>
            <div class="banner-field"><label for="corner-content">Nội dung bài viết</label><textarea id="corner-content" name="content" data-inherited="{{ blank(old('content', $item->content)) ? '1' : '0' }}" maxlength="15000" rows="12" placeholder="Nhập nội dung bài viết hoặc chọn phim để điền mô tả có sẵn">{{ old('content', $editorItem->editorContent()) }}</textarea><p class="banner-help">Hiển thị sẵn nội dung đang dùng trong trang đọc bài. Xuống dòng để chia đoạn; tối đa 15.000 ký tự.</p></div>
            <div class="banner-field"><label for="corner-image-source">Ảnh hiển thị</label><select id="corner-image-source" name="image_source"><option value="movie" @selected($source === 'movie')>Dùng ảnh có sẵn của phim</option><option value="upload" @selected($source === 'upload')>Tải ảnh riêng</option></select></div>
            <div class="banner-field" data-corner-upload @if($source !== 'upload') hidden @endif><label for="corner-image">Chọn ảnh riêng</label><div class="banner-file-picker"><span class="banner-file-button">Chọn tệp</span><span class="banner-file-name" data-corner-file-name>Chưa chọn tệp</span><input id="corner-image" type="file" name="image" class="banner-file-native" accept="image/jpeg,image/png,image/webp" aria-label="Chọn ảnh Góc Điện Ảnh"></div><p class="banner-help">JPG, PNG hoặc WebP, tối đa 4MB. Ưu tiên ảnh ngang 16:9 cho cả bài nổi bật và bài bên cạnh. Không chọn ảnh mới sẽ giữ ảnh riêng hiện tại.</p></div>
            <div class="banner-field"><label for="corner-trailer">Trailer riêng <span>(tùy chọn)</span></label><input id="corner-trailer" name="trailer_url" value="{{ old('trailer_url', $item->trailer_url) }}" maxlength="2048" placeholder="https://youtube.com/... hoặc /storage/..."><p class="banner-help">Để trống để dùng trailer có sẵn của phim.</p></div>
            <div class="banner-field-row"><div class="banner-field"><label for="corner-sort-order">Thứ tự hiển thị</label><input id="corner-sort-order" type="number" name="sort_order" value="{{ old('sort_order', $item->sort_order) }}" min="0" max="9999" required></div><div class="banner-field"><label for="corner-item-active">Trạng thái</label><select id="corner-item-active" name="is_active"><option value="1" @selected((string) old('is_active', (int) $item->is_active) === '1')>Hiển thị</option><option value="0" @selected((string) old('is_active', (int) $item->is_active) === '0')>Ẩn nội dung</option></select></div></div>
            <div class="banner-form-actions"><button type="submit" class="banner-button banner-button--primary">{{ $item->exists ? 'Lưu thay đổi' : 'Thêm nội dung' }}</button><a href="{{ route('admin.cinema-corner.index') }}" class="banner-button">Hủy</a></div>
        </div>
        <aside class="banner-panel banner-preview-panel">
            <h3>Xem trước nội dung</h3><p class="banner-help">Bản xem trước cập nhật khi bạn chọn phim hoặc nhập nội dung.</p>
            <article class="corner-preview" data-corner-preview>
                <div class="corner-preview-media"><img data-corner-preview-image data-current-image="{{ $item->image_path ? $item->imageUrl(true) : '' }}" alt="Ảnh xem trước" hidden><span data-corner-preview-placeholder>Chọn phim để xem trước</span><span class="corner-preview-label" data-corner-preview-label>{{ $settings->featured_label }}</span></div>
                <div class="corner-preview-copy"><p class="corner-preview-meta" data-corner-preview-meta></p><h4 data-corner-preview-title>Tiêu đề nội dung</h4><p data-corner-preview-excerpt>Mô tả ngắn của phim sẽ xuất hiện tại đây.</p><div class="corner-preview-actions"><span><b data-corner-action-label>Đọc bài viết</b> @include('admin.partials.icon',['name'=>'arrow-right','size'=>14])</span><span>@include('admin.partials.icon',['name'=>'circle-play','size'=>14]) Trailer</span></div></div>
            </article>
            <p class="banner-help corner-preview-help" data-corner-preview-help></p>
            @include('admin.partials.image-source', ['imagePath' => $editorItem->imagePath(true), 'imageUrl' => $editorItem->imagePath(true) ? $editorItem->imageUrl(true) : null, 'sourceKey' => 'corner-preview', 'sourceLabel' => $item->image_path ? 'Đường dẫn ảnh riêng đã lưu' : 'Đường dẫn ảnh từ phim'])
        </aside>
    </form>
</div>
@endsection
