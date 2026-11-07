<div class="admin-image-upload" data-image-upload>
    <input type="file" name="{{ $fieldName }}" accept="image/jpeg,image/png,image/webp,image/gif" class="admin-file-input" aria-label="{{ $fieldLabel }}">
    <p class="hint">{{ $uploadHint }} Không chọn tệp mới sẽ giữ ảnh đã lưu.</p>
    @include('admin.partials.image-source', ['imagePath' => $storedPath, 'imageUrl' => filled($storedPath) ? $storedUrl : null, 'sourceKey' => '', 'sourceLabel' => 'Đường dẫn ảnh đã lưu', 'sourceNote' => null])
    <div class="admin-image-upload__preview {{ $previewClass ?? '' }}">
        <img data-image-upload-preview data-original-url="{{ filled($storedPath) ? $storedUrl : '' }}" @if(filled($storedPath) && filled($storedUrl)) src="{{ $storedUrl }}" @else hidden @endif alt="{{ $fieldLabel }}">
        <span data-image-upload-empty @if(filled($storedPath) && filled($storedUrl)) hidden @endif>{{ filled($storedPath) ? 'Không tìm thấy ảnh đã lưu' : 'Chưa có ảnh' }}</span>
    </div>
    <p class="admin-image-upload__status" data-image-upload-status aria-live="polite">{{ filled($storedPath) ? (filled($storedUrl) ? 'Ảnh đã lưu' : 'Không tìm thấy tệp tại đường dẫn đã lưu. Chọn ảnh mới để thay thế.') : 'Chọn tệp để xem trước.' }}</p>
</div>
