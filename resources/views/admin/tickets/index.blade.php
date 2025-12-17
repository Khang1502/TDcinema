@extends('admin.layouts.app')
@section('title', 'Quản lý vé')
@section('content')
<div class="text-slate-50 space-y-6 max-w-7xl mx-auto px-4">
    <style>
        .badge {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .badge-success { background: #0b3c2d; color: #1acf64; border: 1px solid #0f6142; }
        .badge-warning { background: #3b2a0a; color: #facc15; border: 1px solid #b45309; }
        .badge-danger { background: #3b0d0d; color: #f87171; border: 1px solid #b91c1c; }
        .badge-muted { background: #111827; color: #cbd5e1; border: 1px solid #1f2937; }
        .badge-info { background: #1e3a8a; color: #c7d2fe; border: 1px solid #2563eb; }
        .pagination { @apply flex items-center gap-2 mt-4 text-sm; }
        .pagination li a,
        .pagination li span {
            background: #0f172a;
            color: #dbeafe;
            border: 1px solid #1f2937;
            padding: 6px 10px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
        }
        .pagination li a:hover { background: #111a2f; color: #fff; }
        .pagination li.active span { background: #2563eb; color: #fff; border-color: #2563eb; }
        .pagination li.disabled span { opacity: .5; }
    </style>

    <div class="space-y-1">
        <p class="text-slate-400 text-sm">Danh sách vé</p>
        <h1 class="text-3xl font-bold">Quản lý vé</h1>
    </div>

    @if (session('success'))
        <div class="bg-emerald-500/10 border border-emerald-600 text-emerald-200 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-[#111a2f] border border-slate-800 rounded-xl p-4 space-y-4 shadow">
        <form method="GET" class="grid md:grid-cols-4 gap-3">
            <select name="movie_id" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" onchange="this.form.submit()">
                <option value="">Tất cả phim</option>
                @foreach ($movies as $movie)
                    <option value="{{ $movie->id }}" @selected(($filters['movie_id'] ?? null) == $movie->id)>{{ $movie->title }}</option>
                @endforeach
            </select>
            <select name="cinema_id" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" onchange="this.form.submit()">
                <option value="">Tất cả rạp</option>
                @foreach ($cinemas as $cinema)
                    <option value="{{ $cinema->id }}" @selected(($filters['cinema_id'] ?? null) == $cinema->id)>{{ $cinema->name }}</option>
                @endforeach
            </select>
            <select name="showtime_id" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" onchange="this.form.submit()">
                <option value="">Tất cả suất chiếu</option>
                @foreach ($showtimes as $st)
                    @php
                        $stLabelTime = $st->start_time ? \Carbon\Carbon::parse($st->start_time)->format('d/m H:i') : '';
                    @endphp
                    <option value="{{ $st->id }}" @selected(($filters['showtime_id'] ?? null) == $st->id)>
                        {{ $st->movie->title ?? '' }} - {{ optional($st->room)->name }} ({{ optional(optional($st->room)->cinema)->name }}) {{ $stLabelTime }}
                    </option>
                @endforeach
            </select>
            <select name="seat_id" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                <option value="">Tất cả ghế</option>
                @foreach ($seats as $seat)
                    <option value="{{ $seat->id }}" @selected(($filters['seat_id'] ?? null) == $seat->id)>
                        {{ optional($seat->room)->name }} - {{ $seat->seat_row }}{{ $seat->seat_number }}
                    </option>
                @endforeach
            </select>

            <input type="text" name="user" value="{{ $filters['user'] ?? '' }}" placeholder="Khách (tên/email)"
                   class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 col-span-2 md:col-span-1">

            <select name="status" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                <option value="">Trạng thái vé</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st }}" @selected(($filters['status'] ?? null) === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
            <select name="payment_status" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                <option value="">Thanh toán</option>
                @foreach ($payStatuses as $ps)
                    <option value="{{ $ps }}" @selected(($filters['payment_status'] ?? null) === $ps)>{{ ucfirst($ps) }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-2">
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 flex-1">
                <span class="text-slate-400 text-sm">→</span>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 flex-1">
            </div>

            <div class="flex items-center gap-3 md:col-span-4">
                <button class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-4 py-2 rounded-lg" type="submit">Lọc</button>
                <a href="{{ route('admin.tickets.index') }}" class="text-slate-300 hover:text-white text-sm">Xóa lọc</a>
                <a href="{{ route('admin.tickets.create') }}" class="ml-auto inline-flex bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-2 rounded-lg">+ Tạo vé thủ công</a>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.tickets.bulk-checkin') }}"
              onsubmit="return confirm('Check-in tất cả vé đang lọc? Chỉ áp dụng cho vé Confirmed + Paid.');"
              class="grid md:grid-cols-4 gap-3 items-center">
            @csrf
            @foreach ($filters as $key => $val)
                @if(!is_null($val) && $val !== '')
                    <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                @endif
            @endforeach
            <div class="md:col-span-4">
                <button type="submit" class="inline-flex bg-amber-600 hover:bg-amber-500 text-white font-semibold px-4 py-2 rounded-lg">
                    Check-in tất cả (đang lọc)
                </button>
                <p class="text-xs text-slate-400 mt-1">Chỉ ảnh hưởng vé trạng thái Confirmed và thanh toán Paid theo bộ lọc hiện tại.</p>
            </div>
        </form>
    </div>

    <div class="bg-[#111a2f] border border-slate-800 rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-slate-300 text-xs uppercase tracking-wide">
                <tr class="border-b border-slate-800/70">
                    <th class="px-4 py-3 text-left">Mã vé</th>
                    <th class="px-4 py-3 text-left">Phim / Suất / Phòng / Ghế</th>
                    <th class="px-4 py-3 text-left">Khách</th>
                    <th class="px-4 py-3 text-left">Giá vé</th>
                    <th class="px-4 py-3 text-left">Combo</th>
                    <th class="px-4 py-3 text-left">Tổng</th>
                    <th class="px-4 py-3 text-left">Trạng thái vé</th>
                    <th class="px-4 py-3 text-left">Thanh toán</th>
                    <th class="px-4 py-3 text-left">Đặt lúc</th>
                    <th class="px-4 py-3 text-left">Hành động</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800 text-slate-100">
                @forelse ($tickets as $ticket)
                    <tr>
                        <td class="px-4 py-3 font-semibold">#{{ $ticket->id }}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ optional($ticket->showtime->movie)->title }}</div>
                            <div class="text-slate-400 text-xs">
                                @php
                                    $st = optional($ticket->showtime);
                                    $start = $st->start_time ? \Carbon\Carbon::parse($st->start_time)->format('d/m H:i') : '';
                                @endphp
                                {{ $start }} -
                                {{ optional(optional($ticket->showtime)->room)->name }}
                                ({{ optional(optional(optional($ticket->showtime)->room)->cinema)->name }})
                                | Ghế: {{ optional($ticket->seat)->seat_row }}{{ optional($ticket->seat)->seat_number }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ optional($ticket->user)->name }}</div>
                            <div class="text-slate-400 text-xs">{{ optional($ticket->user)->email }}</div>
                        </td>
                        @php
                            $comboTotal = $ticket->foodOrders->sum('total_price');
                            $grandTotal = $ticket->price + $comboTotal;
                        @endphp
                         <td class="px-4 py-3">{{ number_format($ticket->price, 0, ',', '.') }} đ</td>
                         <td class="px-4 py-3">
                            @if($comboTotal > 0)
                                <span class="badge badge-info">{{ number_format($comboTotal, 0, ',', '.') }} đ</span>
                            @else
                                <span class="text-slate-400 text-xs">0 đ</span>
                            @endif
                         </td>
                         <td class="px-4 py-3">{{ number_format($grandTotal, 0, ',', '.') }} đ</td>
                        <td class="px-4 py-3">
                            @php
                                $badgeClass = match($ticket->status) {
                                    'confirmed' => 'badge badge-info',
                                    'pending' => 'badge badge-warning',
                                    'used' => 'badge badge-muted',
                                    'cancelled' => 'badge badge-danger',
                                    default => 'badge badge-muted'
                                };
                            @endphp
                            <span class="{{ $badgeClass }}">{{ ucfirst($ticket->status) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $payStatus = optional($ticket->payment)->status ?? 'unpaid';
                                $payClass = match($payStatus) {
                                    'paid' => 'badge badge-success',
                                    'pending' => 'badge badge-warning',
                                    'refunded' => 'badge badge-warning',
                                    'failed' => 'badge badge-danger',
                                    default => 'badge badge-muted'
                                };
                            @endphp
                            <span class="{{ $payClass }}">{{ ucfirst($payStatus) }}</span>
                        </td>
                        @php
                            $createdAt = $ticket->created_at ? \Carbon\Carbon::parse($ticket->created_at)->format('d/m/Y H:i') : '';
                        @endphp
                        <td class="px-4 py-3">{{ $createdAt }}</td>
                        <td class="px-4 py-3 space-x-3 whitespace-nowrap">
                            <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-blue-400 hover:text-blue-200">Xem</a>
                            @if($ticket->status !== 'used')
                                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="status" value="used">
                                    <button class="text-emerald-400 hover:text-emerald-200" type="submit">Check-in</button>
                                </form>
                            @endif
                            @if($ticket->status !== 'cancelled' && $ticket->status !== 'used')
                                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="status" value="cancelled">
                                    <button class="text-red-400 hover:text-red-200" type="submit">Hủy</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-400">Chưa có vé.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 pb-4">
            {{ $tickets->links() }}
        </div>
    </div>
</div>
@endsection
