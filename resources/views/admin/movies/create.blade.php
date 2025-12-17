@extends('admin.layouts.app')

@section('title', 'Quản lý phim')

@section('content')
    <style>
        .page-shell { color: #e5e7eb; font-family: 'Manrope', 'Inter', system-ui, sans-serif; }
        .card { background: #0f172a; border: 1px solid #1f2937; border-radius: 16px; padding: 22px; box-shadow: 0 12px 30px rgba(0,0,0,0.25); }
        .label { color: #cbd5e1; font-size: 13px; margin-bottom: 6px; display: block; font-weight: 600; }
        .input { width: 100%; background: #0b1224; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 14px; color: #e5e7eb; }
        .input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
        .hint { color: #94a3b8; font-size: 12px; margin-top: 4px; }
        .btn-primary { background: #2563eb; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 700; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-ghost { color: #cbd5e1; }
    </style>

    <div class="page-shell max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <p class="text-sm text-slate-400">Quản lý phim · Thêm phim mới</p>
                <h1 class="text-2xl font-bold text-white">Thêm phim mới</h1>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-4 bg-red-500/10 border border-red-500/40 text-red-200 rounded-lg p-3">
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
                    <div>
                        <label class="label">Thể loại</label>
                        <input name="genre" value="{{ old('genre') }}" class="input" placeholder="Hành động, Khoa học viễn tưởng...">
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
                        <label class="label">Trailer URL</label>
                        <input name="trailer_url" value="{{ old('trailer_url') }}" class="input" placeholder="https://youtube.com/...">
                        <p class="hint">Link Youtube/Vimeo, không cần upload video.</p>
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
                        <div class="rounded-lg border border-slate-700 bg-[#0b1224] p-3">
                            <input type="file" name="poster" accept="image/*" class="w-full text-sm text-slate-100">
                            <p class="hint">Khuyến nghị 2:3, dung lượng &lt; 2MB.</p>
                        </div>
                    </div>
                    <div>
                        <label class="label">Banner</label>
                        <div class="rounded-lg border border-slate-700 bg-[#0b1224] p-3">
                            <input type="file" name="banner" accept="image/*" class="w-full text-sm text-slate-100">
                            <p class="hint">Khuyến nghị 16:9, dung lượng &lt; 4MB.</p>
                        </div>
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
