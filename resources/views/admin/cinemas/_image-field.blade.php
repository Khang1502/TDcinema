@php
    $hasCinemaImage = isset($cinema) && $cinema->hasImage();
    $cinemaImageUrl = isset($cinema) ? $cinema->imageUrl() : asset('assets/images/ui/cinema-placeholder.svg');
@endphp
<section class="cinema-image-editor" data-cinema-image-editor data-original-image="{{ $cinemaImageUrl }}" data-has-image="{{ $hasCinemaImage ? '1' : '0' }}" data-placeholder="{{ asset('assets/images/ui/cinema-placeholder.svg') }}" aria-labelledby="cinema-image-heading">
    <div class="cinema-image-preview">
        <img src="{{ $cinemaImageUrl }}" alt="Xem trước ảnh rạp" data-cinema-image-preview>
        <span class="cinema-image-preview-badge" data-cinema-image-badge>{{ $hasCinemaImage ? 'Ảnh hiện tại' : 'Chưa có ảnh rạp' }}</span>
    </div>
    <div class="cinema-image-controls">
        <h2 id="cinema-image-heading">Hình ảnh rạp</h2>
        <p>Ảnh đại diện hiển thị trong danh sách rạp trên web và trong admin.</p>
        @include('admin.partials.image-source', ['imagePath' => $cinema->image_path ?? null, 'imageUrl' => $hasCinemaImage ? $cinemaImageUrl : null, 'sourceKey' => 'cinema', 'sourceNote' => $hasCinemaImage ? null : 'Khung xem trước dùng ảnh mặc định khi chưa có ảnh rạp.'])
        <label class="cinema-image-upload" for="cinema-image">
            @include('admin.partials.icon', ['name'=>'upload', 'size'=>18])
            <span>{{ $hasCinemaImage ? 'Thay ảnh rạp' : 'Chọn ảnh rạp' }}</span>
        </label>
        <input id="cinema-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="cinema-image-help cinema-image-message" class="cinema-image-input">
        <p id="cinema-image-help" class="cinema-image-help">JPG, PNG hoặc WEBP · Tối đa 4 MB. Nên dùng ảnh ngang 16:9.</p>
        <p id="cinema-image-message" class="cinema-image-message" aria-live="polite" data-cinema-image-message>@error('image'){{ $message }}@enderror</p>
        <button type="button" class="cinema-image-reset" data-cinema-image-reset hidden>Bỏ ảnh vừa chọn</button>
        @if($hasCinemaImage)
            <label class="cinema-image-remove"><input type="checkbox" name="remove_image" value="1" @checked(old('remove_image')) data-cinema-image-remove> Gỡ ảnh hiện tại khi lưu</label>
        @endif
    </div>
</section>
