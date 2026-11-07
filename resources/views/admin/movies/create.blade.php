@extends('admin.layouts.app')

@section('title', 'Quản lý phim')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/movies.css') }}">
@endpush

@section('content')
    <div class="page-shell max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <p class="text-sm text-slate-400">Quản lý phim · Thêm phim mới</p>
                <h1 class="text-2xl font-bold text-white">Thêm phim mới</h1>
            </div>
        </div>

        @if ($errors->any())
            <div data-inline-notification class="mb-4 bg-red-500/10 border border-red-500/40 text-red-200 rounded-lg p-3">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <form action="{{ route('admin.movies.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Tên phim *</label>
                        <input name="title" value="{{ old('title') }}" class="input" required>
                    </div>
                    <div>
                        <label class="label">Thời lượng (phút) *</label>
                        <input type="number" name="duration" value="{{ old('duration') }}" class="input" required>
                    </div>
                    @include('admin.movies._genre-picker')
                    <div>
                        <label class="label" for="age-rating">Độ tuổi *</label>
                        <select id="age-rating" name="age_rating" class="input" required>
                            <option value="P" {{ old('age_rating') === 'P' ? 'selected' : '' }}>P — Phổ biến cho mọi độ tuổi</option>
                            <option value="K" {{ old('age_rating') === 'K' ? 'selected' : '' }}>K — Dưới 13 tuổi cần người giám hộ</option>
                            <option value="T13" {{ old('age_rating', 'T13') === 'T13' ? 'selected' : '' }}>T13 — Từ đủ 13 tuổi</option>
                            <option value="T16" {{ old('age_rating') === 'T16' ? 'selected' : '' }}>T16 — Từ đủ 16 tuổi</option>
                            <option value="T18" {{ old('age_rating') === 'T18' ? 'selected' : '' }}>T18 — Từ đủ 18 tuổi</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Ngày phát hành</label>
                        <input type="date" name="release_date" value="{{ old('release_date') }}" class="input cursor-pointer" onfocus="this.showPicker ? this.showPicker() : null">
                    </div>
                    <div>
                        <label class="label">Trạng thái</label>
                        <select name="status" class="input" required>
                            <option value="now_showing" {{ old('status')==='now_showing' ? 'selected' : '' }}>Đang chiếu</option>
                            <option value="upcoming" {{ old('status','upcoming')==='upcoming' ? 'selected' : '' }}>Sắp chiếu</option>
                            <option value="inactive" {{ old('status')==='inactive' ? 'selected' : '' }}>Ngừng</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Đạo diễn</label>
                        <input name="director" value="{{ old('director') }}" class="input">
                    </div>
                    <div>
                        <label class="label">Diễn viên</label>
                        <input name="actors" value="{{ old('actors') }}" class="input" placeholder="Tên diễn viên, cách nhau bởi dấu phẩy">
                    </div>
                    <div>
                        <label class="label">Trailer URL hoặc tệp video (tùy chọn)</label>
                        <div class="trailer-source-row">
                            <input name="trailer_url" value="{{ old('trailer_url') }}" class="input trailer-url-input" placeholder="https://youtube.com/..." aria-label="Đường dẫn trailer">
                            <input type="file" name="trailer" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov" class="admin-file-input" aria-label="Chọn tệp trailer">
                        </div>
                        <p class="hint">Hỗ trợ MP4, WebM hoặc MOV, tối đa 50MB. Tệp được ưu tiên nếu đồng thời nhập URL.</p>
                    </div>
                    <div>
                        <label class="label">Điểm đánh giá (0-5)</label>
                        <input type="number" step="0.1" min="0" max="5" name="rating_avg" value="{{ old('rating_avg') }}" class="input">
                    </div>
                </div>

                <div>
                    <label class="label">Mô tả</label>
                    <textarea name="description" rows="4" class="input">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Poster</label>
                        @include('admin.partials.image-upload', ['fieldName' => 'poster', 'fieldLabel' => 'Ảnh poster', 'storedPath' => null, 'storedUrl' => null, 'uploadHint' => 'Khuyến nghị 2:3, tối đa 2MB.', 'previewClass' => 'is-poster'])
                    </div>
                    <div>
                        <label class="label">Banner</label>
                        @include('admin.partials.image-upload', ['fieldName' => 'banner', 'fieldLabel' => 'Ảnh banner', 'storedPath' => null, 'storedUrl' => null, 'uploadHint' => 'Khuyến nghị 16:9, tối đa 4MB.', 'previewClass' => 'is-banner'])
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button class="btn-primary" type="submit">Lưu phim</button>
                    <a href="{{ route('admin.movies.index') }}" class="btn-ghost hover:text-white">Hủy</a>
                </div>
            </form>
        </div>
    </div>
@endsection
