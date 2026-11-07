@extends('admin.layouts.app')

@section('title', 'Quản lý phòng chiếu')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/rooms.css') }}">
@endpush

@section('content')
    <div class="page-shell max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-sm text-slate-400">Quản lý phòng chiếu · Thêm phòng</p>
                <h1 class="text-2xl font-bold text-white">Thêm phòng chiếu</h1>
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
                        <input name="name" value="{{ old('name') }}" class="input" required placeholder="Rạp 1, Rạp 2, Rạp 3...">
                    </div>
                    <div>
                        <label class="label" for="room-type">Loại phòng *</label>
                        <select id="room-type" name="type" class="input" required>
                            <option value="2D" {{ old('type', '2D') === '2D' ? 'selected' : '' }}>2D — Tiêu chuẩn</option>
                            <option value="3D" {{ old('type') === '3D' ? 'selected' : '' }}>3D — Không gian 3 chiều</option>
                            <option value="IMAX" {{ old('type') === 'IMAX' ? 'selected' : '' }}>IMAX — Màn hình IMAX</option>
                            <option value="VIP" {{ old('type') === 'VIP' ? 'selected' : '' }}>VIP — Phòng cao cấp</option>
                        </select>
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
