@extends('admin.layouts.app')

@section('title', 'Quản lý rạp phim')

@section('content')
    <style>
        .page-shell { color: #e5e7eb; font-family: 'Manrope','Inter',system-ui,sans-serif; }
        .card { background: #0f172a; border: 1px solid #1f2937; border-radius: 16px; padding: 18px; }
        .label { color: #cbd5e1; font-size: 13px; margin-bottom: 6px; display: block; font-weight: 600; }
        .input { width: 100%; background: #0b1224; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 14px; color: #e5e7eb; }
        .input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
        .btn-primary { background: #2563eb; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 700; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-ghost { color: #cbd5e1; }
    </style>

    <div class="page-shell max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-sm text-slate-400">Quản lý rạp phim · Thêm rạp mới</p>
                <h1 class="text-2xl font-bold text-white">Thêm Rạp Mới</h1>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-4 bg-red-500/10 border border-red-400/60 text-red-200 rounded-lg p-3 font-semibold">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <form action="{{ route('admin.cinemas.store') }}" method="POST" class="space-y-5">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Tên rạp *</label>
                        <input name="name" value="{{ old('name') }}" class="input" required>
                    </div>
                    <div>
                        <label class="label">Tỉnh/Thành phố</label>
                        <input name="city" value="{{ old('city') }}" class="input" placeholder="TP.HCM, Hà Nội,...">
                    </div>
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

                <div class="flex items-center gap-3">
                    <button class="btn-primary" type="submit">Lưu rạp &amp; thêm phòng</button>
                    <a href="{{ route('admin.cinemas.index') }}" class="btn-ghost hover:text-white">Hủy</a>
                </div>
            </form>
        </div>
    </div>
@endsection
