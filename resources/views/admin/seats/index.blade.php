@extends('admin.layouts.app')
@section('title', 'Quản lý ghế')
@section('content')
<div class="max-w-5xl mx-auto px-4 text-slate-50 space-y-4">
    <style>
        .seat-grid { display:inline-block; user-select:none; }
        .seat-row { display:flex; align-items:center; margin-bottom:6px; }
        .seat-row-label { width:32px; text-align:right; margin-right:8px; font-weight:700; color:#94a3b8; }
        .seat {
            width:26px; height:26px; border-radius:6px; margin:2px;
            font-size:12px; display:flex; align-items:center; justify-content:center;
            cursor:default; color:#0f172a; font-weight:700;
        }
        .seat-gap { display:inline-block; height:26px; margin:2px; }
        .seat-normal  { background:#22c55e; }
        .seat-vip     { background:#ef4444; color:#fff; }
        .seat-couple  { background:#a855f7; color:#fff; }
        .seat-inactive{ background:#4b5563; color:#e5e7eb; }
        .seat-selected { outline:2px solid #facc15; box-shadow:0 0 0 2px rgba(250,204,21,.5); }
        .legend-dot { width:14px; height:14px; border-radius:50%; display:inline-block; margin-right:6px; vertical-align:middle; }
    </style>

    <div>
        <p class="text-slate-400 text-sm">Sơ đồ ghế theo phòng chiếu. Chọn phòng để chỉnh sửa nhanh.</p>
        <h1 class="text-2xl font-bold">Quản lý ghế</h1>
    </div>

    @if (session('success'))
        <div class="bg-emerald-900/40 border border-emerald-700 text-emerald-100 px-3 py-2 rounded">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="bg-red-900/40 border border-red-700 text-red-100 px-3 py-2 rounded">
            {{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="bg-red-900/40 border border-red-700 text-red-100 px-3 py-2 rounded">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="bg-[#111a2f] border border-slate-800 rounded-xl p-4 shadow-lg shadow-black/20 space-y-4">
        <form method="GET" action="{{ route('admin.seats.index') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="text-sm text-slate-300 block mb-1">Rạp</label>
                <select name="cinema_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" onchange="this.form.submit()">
                    @foreach ($cinemas as $cinema)
                        <option value="{{ $cinema->id }}" @selected($cinema->id == $defaultCinemaId)>{{ $cinema->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-300 block mb-1">Phòng</label>
                <select name="room_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" onchange="this.form.submit()">
                    @forelse ($rooms as $room)
                        <option value="{{ $room->id }}" @selected($room->id == $defaultRoomId)>{{ $room->name }}</option>
                    @empty
                        <option value="" disabled selected>Chưa có phòng cho rạp này</option>
                    @endforelse
                </select>
            </div>
        </form>

        @if ($defaultRoomId)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="bg-slate-900 border border-slate-800 rounded-lg p-3 space-y-2">
                    <h3 class="font-semibold text-slate-100 text-sm">Tạo sơ đồ ghế nhanh</h3>
                    <form method="POST" action="{{ route('admin.seats.generate', $defaultRoomId) }}" class="space-y-2">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs text-slate-300">Số hàng
                                <input type="number" name="rows_count" class="w-full bg-slate-800 border border-slate-700 rounded px-2 py-1 text-slate-100" min="1" max="52" required>
                            </label>
                            <label class="text-xs text-slate-300">Ghế mỗi hàng
                                <input type="number" name="seats_per_row" class="w-full bg-slate-800 border border-slate-700 rounded px-2 py-1 text-slate-100" min="1" max="100" required>
                            </label>
                        </div>
                        <label class="text-xs text-slate-300">Lối đi (cột, ngăn cách bởi dấu phẩy)
                            <input type="text" name="aisles" class="w-full bg-slate-800 border border-slate-700 rounded px-2 py-1 text-slate-100" placeholder="8,9">
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs text-slate-300">Loại mặc định
                                <select name="default_type" class="w-full bg-slate-800 border border-slate-700 rounded px-2 py-1 text-slate-100">
                                    <option value="normal">Thường</option>
                                    <option value="vip">VIP</option>
                                    <option value="sweetbox">Đôi</option>
                                </select>
                            </label>
                            <label class="text-xs text-slate-300">Phụ thu mặc định
                                <input type="number" name="default_fee" min="0" step="1000" class="w-full bg-slate-800 border border-slate-700 rounded px-2 py-1 text-slate-100" value="0">
                            </label>
                        </div>
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded px-3 py-2">Tạo sơ đồ</button>
                    </form>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-lg p-3 space-y-2">
                    <h3 class="font-semibold text-slate-100 text-sm">Copy sơ đồ từ phòng khác</h3>
                    <form method="POST" action="{{ route('admin.seats.copy', $defaultRoomId) }}" class="space-y-2">
                        @csrf
                        <label class="text-xs text-slate-300">Chọn phòng nguồn
                            <select name="source_room_id" class="w-full bg-slate-800 border border-slate-700 rounded px-2 py-1 text-slate-100">
                                @foreach ($allRooms as $r)
                                    @if ($r->id !== $defaultRoomId)
                                        <option value="{{ $r->id }}">
                                            [{{ $r->cinema->name ?? 'N/A' }}] {{ $r->name }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded px-3 py-2">Copy sơ đồ</button>
                    </form>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-lg p-3 space-y-2">
                    <h3 class="font-semibold text-slate-100 text-sm">Thống kê & đồng bộ</h3>
                    <div class="text-sm text-slate-200 space-y-1">
                        <div>Tổng ghế thực tế: <strong>{{ $seatStats['total'] }}</strong></div>
                        <div>Đang hoạt động: {{ $seatStats['active'] }} • Bảo trì: {{ $seatStats['inactive'] }}</div>
                        <div>Theo loại: Thường {{ $seatStats['by_type']['normal'] }}, VIP {{ $seatStats['by_type']['vip'] }}, Đôi {{ $seatStats['by_type']['sweetbox'] }}</div>
                        <div>rooms.total_seats: <strong>{{ $seatStats['room_total'] }}</strong>
                            @if ($seatStats['diff'] !== 0)
                                <span class="text-amber-400">(lệch {{ $seatStats['diff'] > 0 ? '+' : '' }}{{ $seatStats['diff'] }})</span>
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.seats.sync-total', $defaultRoomId) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-full bg-slate-700 hover:bg-slate-600 text-white text-sm font-semibold rounded px-3 py-2">Đồng bộ tổng ghế</button>
                    </form>
                </div>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-200">
            <span><span class="legend-dot" style="background:#22c55e;"></span>Thường</span>
            <span><span class="legend-dot" style="background:#ef4444;"></span>VIP</span>
            <span><span class="legend-dot" style="background:#a855f7;"></span>Đôi</span>
            <span><span class="legend-dot" style="background:#4b5563;"></span>Bảo trì</span>
        </div>

        @if ($defaultRoomId)
        <div class="flex flex-wrap gap-2 text-xs">
            <button id="btnSetNormal" class="px-3 py-1.5 rounded-lg font-semibold bg-emerald-600 hover:bg-emerald-500 text-white disabled:opacity-50" disabled>Đặt ghế Thường</button>
            <button id="btnSetVip" class="px-3 py-1.5 rounded-lg font-semibold bg-red-600 hover:bg-red-500 text-white disabled:opacity-50" disabled>Đặt ghế VIP</button>
            <button id="btnSetCouple" class="px-3 py-1.5 rounded-lg font-semibold bg-violet-600 hover:bg-violet-500 text-white disabled:opacity-50" disabled>Đặt ghế Đôi</button>
            <button id="btnSetInactive" class="px-3 py-1.5 rounded-lg font-semibold bg-slate-600 hover:bg-slate-500 text-white disabled:opacity-50" disabled>Khóa/Bảo trì</button>
        </div>
        @endif

        @if ($seatRows->isEmpty())
            <p class="text-slate-400 text-sm">Chưa có ghế cho phòng này.</p>
        @else
            <div class="flex justify-center">
                <div class="seat-grid bg-slate-900 border border-slate-800 rounded-lg p-4 shadow-inner">
                    @foreach ($seatRows as $rowLabel => $seats)
                        <div class="seat-row">
                            <div class="seat-row-label">{{ $rowLabel }}</div>
                            @php $prev = 0; $display = 1; @endphp
                            @foreach ($seats as $seat)
                                @php
                                    $gap = $seat->seat_number - $prev - 1;
                                    $typeClass = match ($seat->seat_type) {
                                        'vip' => 'seat-vip',
                                        'sweetbox' => 'seat-couple',
                                        default => 'seat-normal',
                                    };
                                    $classes = 'seat ' . $typeClass;
                                    if (! $seat->status) $classes .= ' seat-inactive';
                                @endphp
                                @if ($gap > 0)
                                    <div class="seat-gap" style="width: {{ $gap * 30 }}px;"></div>
                                @endif
                                <div class="{{ $classes }}"
                                     data-seat-id="{{ $seat->id }}"
                                     title="{{ $seat->seat_row . $seat->seat_number }}&#10;Phụ thu: {{ number_format($seat->extra_price) }}đ">
                                    {{ $display }}
                                </div>
                                @php $prev = $seat->seat_number; $display++; @endphp
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if ($defaultRoomId)
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const seats = document.querySelectorAll('.seat-grid .seat');
        const btnNormal   = document.getElementById('btnSetNormal');
        const btnVip      = document.getElementById('btnSetVip');
        const btnCouple   = document.getElementById('btnSetCouple');
        const btnInactive = document.getElementById('btnSetInactive');
        const selectedIds = new Set();
        // Dùng route đặt tên để tránh sai path (404)
        const bulkUpdateUrl = "{{ route('admin.seats.bulk-update') }}";
        const csrf = document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || '';

        function updateButtons() {
            const hasSel = selectedIds.size > 0;
            [btnNormal, btnVip, btnCouple, btnInactive].forEach(btn => {
                if (btn) btn.disabled = !hasSel;
            });
        }

        function toggleSeat(el) {
            const id = el.dataset.seatId;
            if (!id) return;
            if (selectedIds.has(id)) {
                selectedIds.delete(id);
                el.classList.remove('seat-selected');
            } else {
                selectedIds.add(id);
                el.classList.add('seat-selected');
            }
            updateButtons();
        }

        seats.forEach(el => {
            el.style.cursor = 'pointer';
            el.addEventListener('click', (e) => {
                toggleSeat(el);
                e.stopPropagation();
            });
        });

        async function applyBulk(type = null, status = null) {
            if (!selectedIds.size) {
                alert('Hãy chọn ít nhất 1 ghế.');
                return;
            }
            const seatsPayload = Array.from(selectedIds).map(id => {
                const item = { id };
                if (type) item.seat_type = type;
                if (status !== null) item.status = status;
                return item;
            });
            try {
                const res = await fetch(bulkUpdateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ seats: seatsPayload })
                });
                if (!res.ok) throw new Error();
                location.reload();
            } catch (err) {
                alert('Có lỗi khi cập nhật ghế. Vui lòng thử lại.');
            }
        }

        btnNormal?.addEventListener('click', () => applyBulk('normal', 1));
        btnVip?.addEventListener('click',     () => applyBulk('vip', 1));
        btnCouple?.addEventListener('click',  () => applyBulk('sweetbox', 1));
        btnInactive?.addEventListener('click',() => applyBulk(null, 0));

        updateButtons();
    });
</script>
@endif
@endpush
