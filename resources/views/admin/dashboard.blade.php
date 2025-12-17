@extends('admin.layout')

@section('title', 'Admin Dashboard')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold text-white">Bảng điều khiển</h1>
        <p class="text-slate-400 text-sm mt-1">Chào mừng trở lại, {{ auth()->user()->name ?? 'Admin' }}! Đây là tóm tắt hoạt động của rạp phim.</p>
    </div>
    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#13263f] text-slate-200 text-sm border border-slate-700 hover:bg-[#173155] transition">
        Tất cả các rạp
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
</div>

<div class="grid lg:grid-cols-4 sm:grid-cols-2 gap-4 mb-6">
    @php
        $cards = [
            ['title' => 'Tổng doanh thu', 'value' => '199.999.000 VND', 'delta' => '+2.5% so với tháng trước', 'color' => 'text-emerald-400', 'arrow' => true],
            ['title' => 'Tổng lượt đặt vé', 'value' => '8,950', 'delta' => '+10% so với tháng trước', 'color' => 'text-emerald-400', 'arrow' => true],
            ['title' => 'Người dùng hoạt động', 'value' => '4,567', 'delta' => '+0.5% so với tháng trước', 'color' => 'text-emerald-400', 'arrow' => true],
            ['title' => 'Xuất dữ liệu', 'value' => 'Xuất báo cáo doanh thu và đặt vé ra file Excel.', 'action' => 'Xuất file Excel'],
        ];
    @endphp
    @foreach ($cards as $card)
        <div class="bg-[#0f1f36] border border-slate-800/70 rounded-xl p-4 shadow-lg shadow-black/30">
            <div class="text-slate-300 text-sm mb-2">{{ $card['title'] }}</div>
            <div class="text-2xl font-semibold text-white mb-3">{{ $card['value'] }}</div>
            @if (isset($card['action']))
                <button class="mt-1 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-500 transition shadow shadow-emerald-900/40">
                    {!! view('admin.partials.icon', ['name' => 'download', 'size' => 16, 'classes' => 'text-white']) !!}
                    {{ $card['action'] }}
                </button>
            @else
                <div class="flex items-center gap-2 text-sm {{ $card['color'] }}">
                    @if(!empty($card['arrow']))
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M5 15l7-7 7 7"/>
                    </svg>
                    @endif
                    <span>{{ $card['delta'] }}</span>
                </div>
            @endif
        </div>
    @endforeach
</div>

<div class="grid lg:grid-cols-2 gap-4">
    <div class="bg-[#0f1f36] border border-slate-800/70 rounded-xl p-4 shadow-lg shadow-black/30">
        <div class="flex items-center justify-between mb-3">
            <div>
                <div class="text-white font-semibold text-lg">Xu hướng doanh thu</div>
                <p class="text-slate-400 text-sm">Hiển thị dữ liệu theo bộ lọc đã chọn.</p>
            </div>
            <div class="flex bg-[#13263f] rounded-lg p-1 text-sm text-slate-300">
                <button class="px-3 py-1 rounded-lg hover:bg-[#173155]">Ngày</button>
                <button class="px-3 py-1 rounded-lg bg-blue-600 text-white">Tháng</button>
                <button class="px-3 py-1 rounded-lg hover:bg-[#173155]">Năm</button>
            </div>
        </div>
        <div class="h-64 bg-gradient-to-b from-blue-900/40 to-[#0b1629] rounded-lg flex items-end px-4 pb-4">
            <div class="w-full h-full border border-dashed border-slate-800/70 rounded-lg bg-[#0d1b30] grid place-items-center text-slate-500 text-sm">
                Placeholder biểu đồ (gắn chart thực tế sau)
            </div>
        </div>
    </div>

    <div class="bg-[#0f1f36] border border-slate-800/70 rounded-xl p-4 shadow-lg shadow-black/30">
        <div class="flex items-center justify-between mb-3">
            <div>
                <div class="text-white font-semibold text-lg">Xu hướng đặt vé</div>
                <p class="text-slate-400 text-sm">Hiển thị dữ liệu theo bộ lọc đã chọn.</p>
            </div>
            <div class="flex bg-[#13263f] rounded-lg p-1 text-sm text-slate-300">
                <button class="px-3 py-1 rounded-lg hover:bg-[#173155]">Ngày</button>
                <button class="px-3 py-1 rounded-lg bg-blue-600 text-white">Tháng</button>
                <button class="px-3 py-1 rounded-lg hover:bg-[#173155]">Năm</button>
            </div>
        </div>
        <div class="h-64 bg-gradient-to-b from-blue-900/40 to-[#0b1629] rounded-lg flex items-end px-4 pb-4">
            <div class="w-full h-full border border-dashed border-slate-800/70 rounded-lg bg-[#0d1b30] grid place-items-center text-slate-500 text-sm">
                Placeholder biểu đồ (gắn chart thực tế sau)
            </div>
        </div>
    </div>
</div>
@endsection
