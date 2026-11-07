@extends('admin.layouts.app')
@section('title', 'Thêm ghế')
@push('scripts')
    <script src="{{ asset('assets/js/admin/seat-create.js') }}?v={{ filemtime(public_path('assets/js/admin/seat-create.js')) }}" defer></script>
@endpush
@section('content')
<div class="max-w-3xl mx-auto text-slate-50">
    <h1 class="text-2xl font-bold mb-4">Thêm ghế</h1>
    <form method="POST" action="{{ route('admin.seats.store') }}" class="space-y-4 bg-[#111a2f] border border-slate-800 rounded-xl p-6"
          data-seat-create data-room-options="{{ $rooms->map(fn ($room) => $room->only(['id', 'cinema_id', 'name', 'type']))->toJson() }}">
        @csrf
        <div>
            <h2 class="text-lg font-semibold">Vị trí ghế</h2>
            <p class="text-sm text-slate-400 mt-1">Chọn rạp, sau đó chọn phòng chiếu cần thêm ghế.</p>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="min-w-0">
                <label for="seat-cinema" class="block text-sm text-slate-300 mb-1">Rạp <span aria-hidden="true">*</span></label>
                <select id="seat-cinema" name="cinema_id" required aria-describedby="seat-location-status" @disabled($cinemas->isEmpty()) class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="">Chọn rạp</option>
                    @foreach ($cinemas as $cinema)
                        <option value="{{ $cinema->id }}" @selected($selectedCinemaId == $cinema->id)>{{ $cinema->name }}</option>
                    @endforeach
                </select>
                @error('cinema_id') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="min-w-0">
                <label for="seat-room" class="block text-sm text-slate-300 mb-1">Phòng chiếu <span aria-hidden="true">*</span></label>
                <select id="seat-room" name="room_id" required aria-describedby="seat-location-status" @disabled(!$selectedCinemaId || $availableRooms->isEmpty()) class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="">{{ !$selectedCinemaId ? 'Chọn rạp trước' : ($availableRooms->isEmpty() ? 'Rạp chưa có phòng chiếu' : 'Chọn phòng chiếu') }}</option>
                    @foreach ($availableRooms as $room)
                        <option value="{{ $room->id }}" @selected($selectedRoomId == $room->id)>{{ $room->name }}{{ $room->type ? ' · '.$room->type : '' }}</option>
                    @endforeach
                </select>
                @error('room_id') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <p id="seat-location-status" class="text-sm text-slate-400" role="status" aria-live="polite">
            @if($cinemas->isEmpty())
                Chưa có rạp. Hãy thêm rạp và phòng chiếu trước khi thêm ghế.
            @elseif(!$selectedCinemaId)
                Danh sách phòng chiếu sẽ hiển thị sau khi bạn chọn rạp.
            @elseif($availableRooms->isEmpty())
                Rạp này chưa có phòng chiếu. Hãy thêm phòng chiếu hoặc chọn rạp khác.
            @else
                {{ $availableRooms->count() }} phòng chiếu thuộc rạp đã chọn.
            @endif
        </p>
        @if($cinemas->isEmpty())
            <a href="{{ route('admin.cinemas.create') }}" class="text-blue-400">Thêm rạp →</a>
        @endif
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="seat-row" class="block text-sm text-slate-300 mb-1">Hàng ghế</label>
                <input id="seat-row" type="text" name="seat_row" value="{{ old('seat_row') }}" placeholder="VD: A" maxlength="2" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
                @error('seat_row') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="seat-number" class="block text-sm text-slate-300 mb-1">Số ghế</label>
                <input id="seat-number" type="number" name="seat_number" value="{{ old('seat_number') }}" placeholder="VD: 1" min="1" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
                @error('seat_number') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label for="seat-type" class="block text-sm text-slate-300 mb-1">Loại ghế</label>
                <select id="seat-type" name="seat_type" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="normal" @selected(old('seat_type') === 'normal')>Thường</option>
                    <option value="vip" @selected(old('seat_type') === 'vip')>VIP</option>
                    <option value="sweetbox" @selected(old('seat_type') === 'sweetbox')>Sweetbox</option>
                </select>
                @error('seat_type') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="seat-extra-price" class="block text-sm text-slate-300 mb-1">Phụ thu (đ)</label>
                <input id="seat-extra-price" type="number" name="extra_price" value="{{ old('extra_price', 0) }}" min="0" step="any" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
                @error('extra_price') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="seat-status" class="block text-sm text-slate-300 mb-1">Trạng thái</label>
                <select id="seat-status" name="status" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="1" @selected(old('status', '1') === '1')>Hoạt động</option>
                    <option value="0" @selected(old('status') === '0')>Khoá</option>
                </select>
                @error('status') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex gap-3">
            <button data-seat-save @disabled(!$selectedRoomId) class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-4 py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed" type="submit">Lưu ghế</button>
            <a href="{{ route('admin.seats.index', array_filter(['cinema_id'=>$selectedCinemaId, 'room_id'=>$selectedRoomId])) }}" data-seat-cancel class="text-slate-300 hover:text-white px-3 py-2">Hủy</a>
        </div>
    </form>
</div>
@endsection
