@extends('admin.layouts.app')
@section('title', 'Chi tiết vé #'.$ticket->id)
@section('content')
<div class="text-slate-50 space-y-6 max-w-5xl mx-auto px-4">
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
        .badge-danger  { background: #3b0d0d; color: #f87171; border: 1px solid #b91c1c; }
        .badge-muted   { background: #111827; color: #cbd5e1; border: 1px solid #1f2937; }
        .badge-info    { background: #1e3a8a; color: #c7d2fe; border: 1px solid #2563eb; }
    </style>

    <div class="flex items-center justify-between">
        <div>
            <p class="text-slate-400 text-sm">Mã vé #{{ $ticket->id }}</p>
            <h1 class="text-3xl font-bold">Chi tiết vé</h1>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-center">
                <img src="{{ $qrDataUri }}" alt="QR Code"
                     class="w-36 h-36 rounded-lg shadow {{ $ticket->status === 'used' ? 'opacity-60' : '' }}">
                <p class="text-slate-400 text-xs mt-2">Quét để check-in</p>
                <p class="text-slate-500 text-[11px]">Mã: {{ $qrText }}</p>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <div class="flex items-center gap-3">
            <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}">
                @csrf
                <input type="hidden" name="status" value="used">
                <button class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-4 py-2 rounded-lg" {{ $ticket->status === 'used' ? 'disabled' : '' }}>Đánh dấu đã dùng</button>
            </form>
            @if($ticket->status !== 'cancelled' && $ticket->status !== 'used')
                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}">
                    @csrf
                    <input type="hidden" name="status" value="cancelled">
                    <button class="bg-red-600 hover:bg-red-500 text-white font-semibold px-4 py-2 rounded-lg">Hủy vé</button>
                </form>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="bg-emerald-500/10 border border-emerald-600 text-emerald-200 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="bg-red-500/10 border border-red-600 text-red-200 px-4 py-3 rounded-lg space-y-1">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="bg-[#111a2f] border border-slate-800 rounded-xl p-6 space-y-4 shadow">
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-slate-400 uppercase">Phim / suất chiếu</p>
                <p class="font-semibold text-lg">{{ optional($ticket->showtime->movie)->title }}</p>
                <p class="text-slate-400 text-sm">
                    @php
                        $st = optional($ticket->showtime);
                        $start = $st->start_time ? \Carbon\Carbon::parse($st->start_time)->format('d/m/Y H:i') : '';
                        $end   = $st->end_time ? \Carbon\Carbon::parse($st->end_time)->format('H:i') : '';
                    @endphp
                    {{ $start }} - {{ $end }} |
                    Phòng: {{ optional($st->room)->name }} ({{ optional(optional($st->room)->cinema)->name }})
                </p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase">Ghế</p>
                <p class="font-semibold text-lg">{{ optional($ticket->seat)->seat_row }}{{ optional($ticket->seat)->seat_number }}</p>
                <p class="text-slate-400 text-sm">Loại: {{ optional($ticket->seat)->seat_type }} | Phụ thu: {{ number_format(optional($ticket->seat)->extra_price, 0, ',', '.') }}đ</p>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-slate-400 uppercase">Khách hàng</p>
                <p class="font-semibold text-lg">{{ optional($ticket->user)->name }}</p>
                <p class="text-slate-400 text-sm">{{ optional($ticket->user)->email }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase">Giá vé</p>
                <p class="font-semibold text-lg">{{ number_format($ticket->price, 0, ',', '.') }} đ</p>
                @php
                    $createdAt = $ticket->created_at ? \Carbon\Carbon::parse($ticket->created_at)->format('d/m/Y H:i') : '—';
                @endphp
                <p class="text-slate-400 text-sm">Đặt lúc: {{ $createdAt }}</p>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-slate-400 uppercase mb-1">Trạng thái vé</p>
                @php
                    $ticketBadge = match($ticket->status) {
                        'confirmed' => 'badge badge-info',
                        'pending' => 'badge badge-warning',
                        'used' => 'badge badge-muted',
                        'cancelled' => 'badge badge-danger',
                        default => 'badge badge-muted'
                    };
                @endphp
                <span class="{{ $ticketBadge }}">{{ ucfirst($ticket->status) }}</span>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase mb-1">Thanh toán</p>
                @php $payment = $ticket->payment; @endphp
                <div class="space-y-1">
                    @php
                        $payStatus = $payment->status ?? 'unpaid';
                        $payBadge = match($payStatus) {
                            'paid' => 'badge badge-success',
                            'pending' => 'badge badge-warning',
                            'refunded' => 'badge badge-warning',
                            'failed' => 'badge badge-danger',
                            default => 'badge badge-muted'
                        };
                    @endphp
                    <div>Trạng thái: <span class="{{ $payBadge }}">{{ ucfirst($payStatus) }}</span></div>
                    <div>Phương thức: {{ $payment->method ?? 'N/A' }}</div>
                    <div>Số tiền: {{ $payment ? number_format($payment->amount, 0, ',', '.') : 0 }} đ</div>
                    @php
                        $paidAt = ($payment && $payment->paid_at) ? \Carbon\Carbon::parse($payment->paid_at)->format('d/m/Y H:i') : '—';
                    @endphp
                    <div>Paid at: {{ $paidAt }}</div>
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-slate-400 uppercase mb-1">Combo / Đồ ăn</p>
                @php
                    $comboTotal = $ticket->foodOrders->sum('total_price');
                @endphp
                @if($ticket->foodOrders->isEmpty())
                    <p class="text-slate-400 text-sm">Không có combo.</p>
                @else
                    <div class="bg-slate-900/60 border border-slate-800 rounded-lg p-3 space-y-2">
                        @foreach($ticket->foodOrders as $fo)
                            <div class="text-sm text-slate-100">
                                <div class="font-semibold">Mã nhận: {{ $fo->pickup_code ?? '—' }} ({{ ucfirst($fo->status) }})</div>
                                <div class="text-slate-400 text-xs">Tổng: {{ number_format($fo->total_price, 0, ',', '.') }} đ</div>
                                @if($fo->items->isNotEmpty())
                                    <ul class="text-slate-300 text-xs mt-1 space-y-1 list-disc list-inside">
                                        @foreach($fo->items as $item)
                                            <li>
                                                {{ $item->foodItem->name ?? 'Món' }} x{{ $item->quantity }} -
                                                {{ number_format($item->price * $item->quantity, 0, ',', '.') }} đ
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase mb-1">Tổng tiền</p>
                @php
                    $grandTotal = ($ticket->price ?? 0) + $comboTotal;
                @endphp
                <div class="bg-slate-900/60 border border-slate-800 rounded-lg p-3 space-y-1">
                    <div class="flex justify-between text-sm text-slate-300">
                        <span>Vé</span><span>{{ number_format($ticket->price ?? 0, 0, ',', '.') }} đ</span>
                    </div>
                    <div class="flex justify-between text-sm text-slate-300">
                        <span>Combo</span><span>{{ number_format($comboTotal, 0, ',', '.') }} đ</span>
                    </div>
                    <div class="flex justify-between font-semibold text-white">
                        <span>Tổng</span><span>{{ number_format($grandTotal, 0, ',', '.') }} đ</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
