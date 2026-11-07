<div class="admin-image-source" data-image-source="{{ $sourceKey ?? '' }}">
    <span class="admin-image-source__label" data-image-source-label>{{ $sourceLabel ?? 'Đường dẫn ảnh đã lưu' }}</span>
    <div class="admin-image-source__row">
        <input type="text" readonly value="{{ $imagePath ?? '' }}" placeholder="Chưa có ảnh đã lưu" aria-label="{{ $sourceLabel ?? 'Đường dẫn ảnh đã lưu' }}" data-image-source-path>
        <a href="{{ $imageUrl ?? '' }}" target="_blank" rel="noopener" data-image-source-link @if(empty($imageUrl)) hidden @endif>Mở ảnh @include('admin.partials.icon',['name'=>'external-link','size'=>16])</a>
    </div>
    @if(!empty($sourceNote))<small>{{ $sourceNote }}</small>@endif
</div>
