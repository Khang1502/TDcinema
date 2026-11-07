@extends('admin.layouts.app')

@section('title', 'Quản lý rạp phim')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/cinemas.css') }}?v={{ filemtime(public_path('assets/css/admin/cinemas.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/admin/cinemas.js') }}?v={{ filemtime(public_path('assets/js/admin/cinemas.js')) }}" defer></script>
@endpush

@section('content')
    <div class="page-shell max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-sm text-slate-400">Quản lý rạp phim · Thêm rạp mới</p>
                <h1 class="text-2xl font-bold text-white">Thêm Rạp Mới</h1>
            </div>
        </div>

        @if ($errors->any())
            <div data-inline-notification class="mb-4 bg-red-500/10 border border-red-400/60 text-red-200 rounded-lg p-3 font-semibold">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <form action="{{ route('admin.cinemas.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Tên rạp *</label>
                        <input name="name" value="{{ old('name') }}" class="input" required>
                    </div>
                    @include('admin.cinemas._location-fields')
                    <div class="md:col-span-2">
                        <label class="label">Địa chỉ</label>
                        <input name="address" value="{{ old('address') }}" class="input" placeholder="Số nhà, đường, quận/huyện...">
                    </div>
                    <div>
                        <label class="label">Trạng thái</label>
                        <select name="status" class="input">
                            <option value="active" {{ old('status','active')==='active' ? 'selected' : '' }}>Đang hoạt động</option>
                            <option value="inactive" {{ old('status')==='inactive' ? 'selected' : '' }}>Ngừng hoạt động</option>
                            <option value="maintenance" {{ old('status')==='maintenance' ? 'selected' : '' }}>Sửa chữa</option>
                        </select>
                    </div>
                </div>

                @include('admin.cinemas._image-field')

                <div class="flex items-center gap-3">
                    <button class="btn-primary" type="submit">Lưu rạp &amp; thêm phòng</button>
                    <a href="{{ route('admin.cinemas.index') }}" class="btn-ghost hover:text-white">Hủy</a>
                </div>
            </form>
        </div>
    </div>
@endsection
