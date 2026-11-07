@extends('admin.layouts.app')
@php
    $showCinemaFilter = false;
@endphp
@section('title', 'Sửa suất chiếu')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/showtimes.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/admin/showtimes.js') }}" defer></script>
@endpush

@section('content')
<div class="max-w-3xl mx-auto text-slate-50">
    <h1 class="text-2xl font-bold mb-4">Sửa suất chiếu</h1>
    <form method="POST"
          action="{{ route('admin.showtimes.update', $showtime) }}"
          class="space-y-4 bg-[#111a2f] border border-slate-800 rounded-xl p-6"
          data-duration-map='@json($movies->pluck("duration", "id"))'>
        @csrf
        @method('PUT')
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-300 mb-1">Phim</label>
                <select name="movie_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    @foreach ($movies as $movie)
                        <option value="{{ $movie->id }}" @selected(old('movie_id', $showtime->movie_id) == $movie->id)>{{ $movie->title }}</option>
                    @endforeach
                </select>
                @error('movie_id') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Phòng chiếu</label>
                <select name="room_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected(old('room_id', $showtime->room_id) == $room->id)>
                            {{ $room->cinema->name ?? 'Chưa có rạp' }} · {{ $room->name }} · {{ $room->type }} · #{{ $room->id }}
                        </option>
                    @endforeach
                </select>
                @error('room_id') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-300 mb-1">Thời gian bắt đầu</label>
                <input type="datetime-local" name="start_time" value="{{ old('start_time', \Carbon\Carbon::parse($showtime->start_time)->format('Y-m-d\TH:i')) }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
                @error('start_time') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Thời gian kết thúc</label>
                <input type="datetime-local" name="end_time" value="{{ old('end_time', \Carbon\Carbon::parse($showtime->end_time)->format('Y-m-d\TH:i')) }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
                @error('end_time') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-300 mb-1">Buffer (phút)</label>
                <input type="number" name="buffer_minutes" value="{{ old('buffer_minutes', 15) }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" min="0" step="5" />
                <p class="text-xs text-slate-400 mt-1">Dùng để tự tính giờ kết thúc: thời lượng phim + buffer.</p>
            </div>
            <div class="flex items-end gap-2">
                <input type="checkbox" id="auto-end-time" class="w-4 h-4" checked />
                <label for="auto-end-time" class="text-sm text-slate-300">Tự tính giờ kết thúc khi đổi phim/giờ bắt đầu.</label>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-300 mb-1">Giá vé cơ bản</label>
                <input type="number" name="base_price" value="{{ old('base_price', $showtime->base_price) }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
                @error('base_price') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Trạng thái</label>
                <select name="status" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="active" @selected(old('status', $showtime->status) === 'active')>Đang mở bán</option>
                    <option value="cancelled" @selected(old('status', $showtime->status) === 'cancelled')>Đã hủy</option>
                </select>
                @error('status') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex gap-3">
            <button class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-4 py-2 rounded-lg" type="submit">Lưu thay đổi</button>
            <a href="{{ route('admin.showtimes.index') }}" class="text-slate-300 hover:text-white px-3 py-2">Hủy</a>
        </div>
    </form>
</div>

@endsection



