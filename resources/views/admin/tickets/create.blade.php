@extends('admin.layouts.app')
@section('title', 'Tạo vé thủ công')
@section('content')
<div class="text-slate-50 space-y-6 max-w-5xl mx-auto px-4">
    <div class="space-y-1">
        <p class="text-slate-400 text-sm">Tạo vé thủ công (offline)</p>
        <h1 class="text-3xl font-bold">Tạo vé</h1>
    </div>

    @if ($errors->any())
        <div class="bg-red-500/10 border border-red-600 text-red-200 px-4 py-3 rounded-lg space-y-1">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.tickets.store') }}" class="space-y-4 bg-[#111a2f] border border-slate-800 rounded-xl p-6 shadow">
        @csrf
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-300 mb-1">Suất chiếu</label>
                <select name="showtime_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="">Chọn suất chiếu</option>
                    @foreach ($showtimes as $st)
                        @php
                            $stStart = $st->start_time ? \Carbon\Carbon::parse($st->start_time) : null;
                            $stEnd   = $st->end_time ? \Carbon\Carbon::parse($st->end_time) : null;
                            $stLabelTime = $stStart ? $stStart->format('d/m H:i') : '';
                        @endphp
                        <option value="{{ $st->id }}" @selected(old('showtime_id', $prefillShowtime) == $st->id)>
                            {{ $st->movie->title ?? '' }} - {{ optional($st->room)->name }} ({{ optional(optional($st->room)->cinema)->name }}) {{ $stLabelTime }}
                        </option>
                    @endforeach
                </select>
                @error('showtime_id') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror

                @if($selectedShowtime)
                    @php
                        $stStart = \Carbon\Carbon::parse($selectedShowtime->start_time);
                        $stEnd   = $selectedShowtime->end_time ? \Carbon\Carbon::parse($selectedShowtime->end_time) : null;
                    @endphp
                    <div class="mt-2 text-sm text-slate-300 bg-slate-800/50 border border-slate-700 rounded-lg p-3">
                        <div class="font-semibold">{{ $selectedShowtime->movie->title ?? '' }}</div>
                        <div class="text-slate-400">
                            Rạp: {{ optional(optional($selectedShowtime->room)->cinema)->name }} |
                            Phòng: {{ optional($selectedShowtime->room)->name }} |
                            Thời gian: {{ $stStart->format('d/m/Y H:i') }} @if($stEnd) → {{ $stEnd->format('H:i') }} @endif
                        </div>
                    </div>
                @endif
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Ghế</label>
                <select name="seat_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="">Chọn ghế</option>
                    @forelse ($seats as $seat)
                        @php
                            $isBooked = $bookedSeatIds->contains($seat->id);
                        @endphp
                        <option value="{{ $seat->id }}"
                                @selected(old('seat_id', $prefillSeat) == $seat->id)
                                {{ $isBooked ? 'disabled' : '' }}>
                            {{ optional($seat->room)->name }} - {{ $seat->seat_row }}{{ $seat->seat_number }}
                            @if($isBooked) (Đã đặt) @endif
                        </option>
                    @empty
                        @if($selectedShowtime)
                            <option disabled>Không có ghế khả dụng</option>
                        @endif
                    @endforelse
                </select>
                @error('seat_id') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror

                @if(!$selectedShowtime)
                    <p class="text-xs text-slate-400 mt-2">Chọn suất chiếu để tải danh sách ghế của phòng đó.</p>
                @endif
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-300 mb-1">Khách hàng</label>
                <select name="user_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="">Chọn user</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Giá vé (tùy chỉnh)</label>
                <input type="number" step="0.01" name="price" value="{{ old('price') }}" placeholder="Nếu bỏ trống: base_price + phụ thu ghế"
                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
            </div>
        </div>

        <div class="flex gap-3">
            <button class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-4 py-2 rounded-lg" type="submit">Lưu vé</button>
            <a href="{{ route('admin.tickets.index') }}" class="text-slate-300 hover:text-white px-3 py-2">Hủy</a>
        </div>
    </form>
</div>
@endsection
