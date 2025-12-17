@extends('admin.layouts.app')
@section('title', 'Thêm suất chiếu')

@section('content')
@php
    $showCinemaFilter = false;
    // Bảo đảm luôn có biến mặc định (kể cả khi controller không truyền xuống)
    $prefillMovie = $prefillMovie ?? request('movie_id');
    $prefillRoom  = $prefillRoom ?? request('room_id');
    $prefillDate  = $prefillDate ?? request('date');
    $defaultStart = $defaultStart ?? ($prefillDate ? \Carbon\Carbon::parse($prefillDate . ' 09:00') : now());
    $defaultEnd   = $defaultEnd ?? $defaultStart->copy()->addHours(2);
@endphp

<div class="max-w-3xl mx-auto text-slate-50">
    <h1 class="text-2xl font-bold mb-4">Thêm suất chiếu mới</h1>
    <form method="POST" action="{{ route('admin.showtimes.store') }}" class="space-y-4 bg-[#111a2f] border border-slate-800 rounded-xl p-6">
        @csrf
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-300 mb-1">Phim</label>
                <select name="movie_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    @foreach ($movies as $movie)
                        <option value="{{ $movie->id }}" @selected((old('movie_id') ?? $prefillMovie) == $movie->id)>{{ $movie->title }}</option>
                    @endforeach
                </select>
                @error('movie_id') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Phòng chiếu</label>
                <select name="room_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected(old('room_id', $prefillRoom) == $room->id)>
                            {{ $room->name }} ({{ $room->cinema->name ?? 'N/A' }})
                        </option>
                    @endforeach
                </select>
                @error('room_id') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-300 mb-1">Thời gian bắt đầu</label>
                <input type="datetime-local" name="start_time" value="{{ old('start_time', $defaultStart->format('Y-m-d\\TH:i')) }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
                @error('start_time') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Thời gian kết thúc</label>
                <input type="datetime-local" name="end_time" value="{{ old('end_time', $defaultEnd->format('Y-m-d\\TH:i')) }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
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
                <input type="number" name="base_price" value="{{ old('base_price', 0) }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
                @error('base_price') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Trạng thái</label>
                <select name="status" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="">-- Chọn --</option>
                    <option value="1" @selected(old('status', '1') === '1')>Open</option>
                    <option value="0" @selected(old('status') === '0')>Closed</option>
                </select>
                @error('status') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex gap-3">
            <button class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-4 py-2 rounded-lg" type="submit">Lưu</button>
            <a href="{{ route('admin.showtimes.index') }}" class="text-slate-300 hover:text-white px-3 py-2">Hủy</a>
        </div>
    </form>
</div>

<script>
    (() => {
        const durationMap = @json($movies->pluck('duration', 'id'));
        const movieSelect = document.querySelector('select[name="movie_id"]');
        const startInput = document.querySelector('input[name="start_time"]');
        const endInput = document.querySelector('input[name="end_time"]');
        const bufferInput = document.querySelector('input[name="buffer_minutes"]');
        const toggle = document.querySelector('#auto-end-time');

        const pad = (v) => v.toString().padStart(2, '0');
        const formatDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;

        const recalc = () => {
            if (!toggle.checked) return;
            const duration = Number(durationMap[movieSelect?.value] || 0);
            const buffer = Number(bufferInput?.value || 0);
            if (!startInput?.value || !duration) return;
            const start = new Date(startInput.value);
            if (isNaN(start.getTime())) return;
            const end = new Date(start.getTime() + (duration + buffer) * 60000);
            endInput.value = formatDate(end);
        };

        movieSelect?.addEventListener('change', recalc);
        startInput?.addEventListener('change', recalc);
        bufferInput?.addEventListener('input', recalc);
        toggle?.addEventListener('change', recalc);

        // chạy 1 lần đầu để đồng bộ theo buffer mặc định
        recalc();
    })();
</script>
@endsection
