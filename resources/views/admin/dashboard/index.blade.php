@extends('admin.layouts.app')
@php($showCinemaFilter = true)
@section('title', 'Bảng điều khiển')
@php
    $range = $range ?? 'month';
    $chartLabels = $chartLabels ?? [];
    $chartRevenue = $chartRevenue ?? [];
    $chartBookings = $chartBookings ?? [];
    $payMethods = $paymentBreakdown ?? collect();
@endphp

@section('content')

    <style>
        .ds-wrap {
            max-width: 1280px;
            margin: 0 auto;
            color: #dbeafe;
        }

        .ds-head h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 6px 0 0;
            color: #fff;
        }

        .ds-head p {
            margin: 0;
            color: #94a3b8;
            font-size: 14px;
        }

        .ds-grid {
            display: grid;
            gap: 18px;
        }

        .ds-stats {
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }

        .ds-card {
            background: #111a2f;
            border: 1px solid #1f2937;
            border-radius: 16px;
            padding: 20px;
        }

        .ds-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .ds-card h4 {
            margin: 0 0 6px;
            color: #cbd5e1;
            font-size: 13px;
            letter-spacing: .2px;
            text-transform: uppercase;
        }

        .ds-value {
            margin: 0 0 10px;
            font-size: 30px;
            font-weight: 700;
            color: #fff;
        }

        .ds-delta {
            margin: 0;
            font-size: 12px;
            color: #22c55e;
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .pill-group {
            display: inline-flex;
            background: #1f2937;
            border-radius: 10px;
            padding: 4px;
            gap: 4px;
        }

        .pill {
            padding: 6px 12px;
            border-radius: 8px;
            color: #cbd5e1;
            font-size: 12px;
            border: none;
            background: transparent;
            cursor: pointer;
        }

        .pill.active {
            background: #2563eb;
            color: #fff;
        }

        .chart-box {
            position: relative;
            height: 260px;
            border: 1px solid #1f2937;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.16) 0%, rgba(15, 23, 42, 0.6) 100%);
        }

        .chart-box.bar {
            background: #0f172a;
        }

        .chart-y {
            position: absolute;
            left: 12px;
            top: 12px;
            bottom: 40px;
            width: 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #94a3b8;
            font-size: 10px;
        }

        .chart-x {
            position: absolute;
            bottom: 10px;
            left: 64px;
            right: 16px;
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: 1fr;
            gap: 10px;
            font-size: 10px;
            color: #94a3b8;
            text-align: center;
        }

        .bars {
            position: absolute;
            bottom: 32px;
            left: 64px;
            right: 16px;
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: 1fr;
            gap: 12px;
            align-items: end;
        }

        .bars .bar {
            background: #2563eb;
            border-radius: 6px 6px 4px 4px;
            transition: height 0.2s ease;
            min-height: 4px;
        }
    </style>

    <div class="ds-wrap space-y-8">
        <div class="ds-head space-y-1">
            <p>Chào mừng trở lại, {{ auth()->user()->name ?? 'Admin' }}! Đây là tóm tắt hoạt động của rạp phim.</p>
            <h1>Bảng điều khiển</h1>
        </div>

        <div class="ds-grid ds-stats">
            <div class="ds-card">
                <h4>Tổng doanh thu</h4>
                <p class="ds-value">{{ number_format($stats['revenue'] ?? 0, 0, ',', '.') }} VND</p>
                <p class="ds-delta">
                    ▲ {{ isset($stats['revenue_change']) ? number_format($stats['revenue_change'] * 100, 1) . '%' : '0%' }}
                    so với tháng trước
                </p>
            </div>
            <div class="ds-card">
                <h4>Vé đã thanh toán</h4>
                <p class="ds-value">{{ number_format($stats['bookings'] ?? 0, 0, ',', '.') }}</p>
                <p class="ds-delta">
                    ▲
                    {{ isset($stats['bookings_change']) ? number_format($stats['bookings_change'] * 100, 1) . '%' : '0%' }}
                    so với tháng trước
                </p>
            </div>
            <div class="ds-card">
                <h4>Tỉ lệ thành công</h4>
                <p class="ds-value">{{ number_format(($stats['success_rate'] ?? 0) * 100, 1) }}%</p>
                <p class="ds-delta">
                    ▲ {{ isset($stats['success_rate_change']) ? number_format(($stats['success_rate_change'] ?? 0) * 100, 1) . '%' : '0%' }}
                    so với kỳ trước
                </p>
            </div>
            <div class="ds-card">
                <h4>Vé huỷ (tháng)</h4>
                <p class="ds-value">{{ number_format($stats['cancel'] ?? 0, 0, ',', '.') }}</p>
                <p class="ds-delta" style="color:#f97316;">
                    {{ isset($stats['cancel_change']) ? number_format(($stats['cancel_change'] ?? 0) * 100, 1) . '%' : '0%' }}
                    so với tháng trước
                </p>
            </div>
            <div class="ds-card">
                <h4>Người dùng hoạt động</h4>
                <p class="ds-value">{{ number_format($stats['active_users'] ?? 0, 0, ',', '.') }}</p>
                <p class="ds-delta">
                    ▲
                    {{ isset($stats['active_users_change']) ? number_format($stats['active_users_change'] * 100, 1) . '%' : '0%' }}
                    so với tháng trước
                </p>
            </div>
            <div class="ds-card" style="display:flex;flex-direction:column;gap:10px;">
                <div>
                    <h4>Xuất dữ liệu</h4>
                    <p class="ds-sub" style="margin:0;">Xuất báo cáo doanh thu và đặt vé ra file Excel.</p>
                </div>
                <form method="GET" action="{{ route('admin.export') }}" style="display:flex; flex-direction:column; gap:8px;">
                    <div class="grid grid-cols-3 gap-2">
                        <select name="month" class="bg-slate-800 border border-slate-600 rounded px-2 py-1 text-slate-100 text-sm">
                            @for($m=1;$m<=12;$m++)
                                <option value="{{ $m }}" {{ ($currentMonth ?? now()->month) == $m ? 'selected' : '' }}>Tháng {{ $m }}</option>
                            @endfor
                        </select>
                        <select name="year" class="bg-slate-800 border border-slate-600 rounded px-2 py-1 text-slate-100 text-sm">
                            @php $yNow = $currentYear ?? now()->year; @endphp
                            @for($y = $yNow-2; $y <= $yNow; $y++)
                                <option value="{{ $y }}" {{ $yNow == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                        <select name="cinema_id" class="bg-slate-800 border border-slate-600 rounded px-2 py-1 text-slate-100 text-sm">
                            <option value="">Tất cả rạp</option>
                            @foreach($cinemas as $cinema)
                                <option value="{{ $cinema->id }}">{{ $cinema->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                        class="inline-flex items-center justify-center bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold px-4 py-2 rounded-lg transition w-full">
                        Xuất file Excel
                    </button>
                </form>
            </div>
        </div>

        <div class="ds-grid lg:grid-cols-2">
            {{-- Chart Doanh thu --}}
            <div class="ds-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-lg font-semibold text-white mb-1">Xu hướng doanh thu</h3>
                        <p class="text-sm text-slate-400">Hiển thị dữ liệu theo bộ lọc đã chọn.</p>
                    </div>
                    <form method="GET" class="pill-group">
                        <button type="submit" name="range" value="day"
                            class="pill {{ $range === 'day' ? 'active' : '' }}">Ngày</button>
                        <button type="submit" name="range" value="month"
                            class="pill {{ $range === 'month' ? 'active' : '' }}">Tháng</button>
                        <button type="submit" name="range" value="year"
                            class="pill {{ $range === 'year' ? 'active' : '' }}">Năm</button>
                    </form>
                </div>
                @php
                    $revMax = count($chartRevenue) ? max($chartRevenue) : 0;
                    $revScale = $revMax > 0 ? 180 / $revMax : 0;
                    $revStep = $revMax > 0 ? max(1, ceil($revMax / 4 / 1000) * 1000) : 1;
                    $revTicks = [];
                    for ($v = 0; $v <= $revStep * 4; $v += $revStep) {
                        $revTicks[] = $v;
                    }
                @endphp
                <div class="chart-box">
                    <div class="chart-y">
                        @foreach (array_reverse($revTicks) as $tick)
                            <span>{{ number_format($tick, 0, ',', '.') }}</span>
                        @endforeach
                    </div>
                    <div class="chart-x">
                        @foreach ($chartLabels as $label)
                            <span>{{ $label }}</span>
                        @endforeach
                    </div>
                    <div class="bars">
                        @foreach ($chartRevenue as $value)
                            <div class="bar" style="height: {{ $value > 0 ? max(4, $value * $revScale) : 4 }}px">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Chart Đặt vé --}}
            <div class="ds-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-lg font-semibold text-white mb-1">Xu hướng đặt vé</h3>
                        <p class="text-sm text-slate-400">Hiển thị dữ liệu theo bộ lọc đã chọn.</p>
                    </div>
                    <form method="GET" class="pill-group">
                        <button type="submit" name="range" value="day"
                            class="pill {{ $range === 'day' ? 'active' : '' }}">Ngày</button>
                        <button type="submit" name="range" value="month"
                            class="pill {{ $range === 'month' ? 'active' : '' }}">Tháng</button>
                        <button type="submit" name="range" value="year"
                            class="pill {{ $range === 'year' ? 'active' : '' }}">Năm</button>
                    </form>
                </div>
                @php
                    $bookMax = count($chartBookings) ? max($chartBookings) : 0;
                    $bookScale = $bookMax > 0 ? 180 / $bookMax : 0;
                    $bookStep = $bookMax > 0 ? max(1, ceil($bookMax / 4)) : 1;
                    $bookTicks = [];
                    for ($v = 0; $v <= $bookStep * 4; $v += $bookStep) {
                        $bookTicks[] = $v;
                    }
                @endphp
                <div class="chart-box bar">
                    <div class="chart-y">
                        @foreach (array_reverse($bookTicks) as $tick)
                            <span>{{ $tick }}</span>
                        @endforeach
                    </div>
                    <div class="chart-x">
                        @foreach ($chartLabels as $label)
                            <span>{{ $label }}</span>
                        @endforeach
                    </div>
                    <div class="bars">
                        @foreach ($chartBookings as $value)
                            <div class="bar" style="height: {{ $value > 0 ? max(4, $value * $bookScale) : 4 }}px">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="ds-grid lg:grid-cols-2 gap-4">
            <div class="ds-card">
                <h3 class="text-lg font-semibold text-white mb-1">Phương thức thanh toán</h3>
                <p class="text-sm text-slate-400 mb-3">Chỉ tính giao dịch đã paid.</p>
                @php
                    $payMethods = ($payMethods ?? null) ?: ($paymentBreakdown ?? collect());
                @endphp
                @if($payMethods->count())
                    <div class="ds-mini">
                        @foreach($payMethods as $pm)
                            @php
                                $label = match($pm['method']) {
                                    'momo' => 'MoMo',
                                    'vnpay' => 'VNPAY',
                                    'cash' => 'Tiền mặt',
                                    default => strtoupper($pm['method']),
                                };
                            @endphp
                            <div class="bg-slate-900/60 border border-slate-800 rounded-lg px-3 py-2">
                                <div class="text-slate-400 text-xs uppercase">{{ $label }}</div>
                                <div class="text-white text-lg font-semibold">{{ number_format($pm['total'], 0, ',', '.') }} đ</div>
                                <div class="text-slate-400 text-xs">Số giao dịch: {{ $pm['count'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-slate-400 text-sm">Chưa có giao dịch paid.</p>
                @endif
            </div>
            <div class="ds-card">
                <h3 class="text-lg font-semibold text-white mb-1">Ghi chú số liệu</h3>
                <ul class="text-slate-400 text-sm space-y-1" style="padding-left:16px; margin:0;">
                    <li>Doanh thu và biểu đồ doanh thu chỉ tính payment status = paid.</li>
                    <li>Vé đã thanh toán/biểu đồ đặt vé lấy ticket status confirmed/used có payment paid.</li>
                    <li>Tỉ lệ thành công = Vé đã thanh toán / Tổng vé tạo trong tháng.</li>
                    <li>Vé huỷ đếm theo created_at trong tháng hiện tại.</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
