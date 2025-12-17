@extends('admin.layouts.app')
@section('title', 'Thêm ghế')
@section('content')
<div class="max-w-3xl mx-auto text-slate-50">
    <h1 class="text-2xl font-bold mb-4">Thêm ghế</h1>
    <form method="POST" action="{{ route('admin.seats.store') }}" class="space-y-4 bg-[#111a2f] border border-slate-800 rounded-xl p-6">
        @csrf
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-300 mb-1">Phòng</label>
                <select name="room_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>{{ $room->name }}</option>
                    @endforeach
                </select>
                @error('room_id') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Hàng</label>
                    <input type="text" name="seat_row" value="{{ old('seat_row') }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
                    @error('seat_row') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Số</label>
                    <input type="number" name="seat_number" value="{{ old('seat_number') }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
                    @error('seat_number') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm text-slate-300 mb-1">Loại ghế</label>
                <select name="seat_type" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="normal" @selected(old('seat_type') === 'normal')>Thường</option>
                    <option value="vip" @selected(old('seat_type') === 'vip')>VIP</option>
                    <option value="sweetbox" @selected(old('seat_type') === 'sweetbox')>Sweetbox</option>
                </select>
                @error('seat_type') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Phụ thu (đ)</label>
                <input type="number" name="extra_price" value="{{ old('extra_price', 0) }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
                @error('extra_price') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Trạng thái</label>
                <select name="status" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="1" @selected(old('status', '1') === '1')>Hoạt động</option>
                    <option value="0" @selected(old('status') === '0')>Khoá</option>
                </select>
                @error('status') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex gap-3">
            <button class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-4 py-2 rounded-lg" type="submit">Lưu</button>
            <a href="{{ route('admin.seats.index') }}" class="text-slate-300 hover:text-white px-3 py-2">Hủy</a>
        </div>
    </form>
</div>
@endsection
