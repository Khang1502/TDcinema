@extends('admin.layouts.app')

@section('title', 'Quản lý phòng chiếu')

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
                <p class="text-sm text-slate-400">Quản lý phòng chiếu · Thêm phòng</p>
                <h1 class="text-2xl font-bold text-white">Thêm phòng chiếu</h1>
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
            <form action="{{ route('admin.rooms.store') }}" method="POST" class="space-y-5">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="label">Rạp *</label>
                        <select name="cinema_id" class="input" required>
                            <option value="">-- Chọn rạp --</option>
                            @foreach($cinemas as $cinema)
                                <option value="{{ $cinema->id }}" {{ old('cinema_id')==$cinema->id ? 'selected' : '' }}>{{ $cinema->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Tên phòng *</label>
                        <input name="name" value="{{ old('name') }}" class="input" required>
                    </div>
                    <div>
                        <label class="label">Loại phòng</label>
                        <input name="type" value="{{ old('type') }}" class="input" placeholder="2D, 3D, IMAX...">
                    </div>
                    <div>
                        <label class="label">Số ghế</label>
                        <input type="number" name="total_seats" value="{{ old('total_seats') }}" class="input" min="0">
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
                    <button class="btn-primary" type="submit">Lưu phòng</button>
                    <a href="{{ route('admin.rooms.index') }}" class="btn-ghost hover:text-white">Hủy</a>
                </div>
            </form>
        </div>
    </div>
@endsection
