@extends('admin.layouts.app')
@section('title', 'Quản lý khuyến mãi')
@php use Carbon\Carbon; @endphp

@section('content')
<style>
    .promo-shell { color: #e2e8f0; font-family: 'Manrope','Inter',system-ui,sans-serif; }
    .promo-card { background: #0e1627; border: 1px solid #1f2937; border-radius: 14px; }
    .promo-input { background: #0b1221; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 14px; color: #e5e7eb; width: 100%; }
    .promo-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
    .promo-select { background: #0b1221; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 12px; color: #e5e7eb; min-width: 160px; }
    .promo-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
    .promo-row { display: grid; grid-template-columns: 1fr 140px 120px 140px 140px 80px 140px 100px; gap: 12px; align-items: center; padding: 12px 16px; border-bottom: 1px solid #1f2937; }
    .promo-row:last-child { border-bottom: none; }
    .badge { border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
    .badge-active { background: #0b3c2d; color: #22c55e; border: 1px solid #0f6142; }
    .badge-expired { background: #3b0d0d; color: #f87171; border: 1px solid #b91c1c; }
    .badge-upcoming { background: #3b2a0a; color: #facc15; border: 1px solid #b45309; }
    .action-btn { width: 32px; height: 32px; border-radius: 10px; border: 1px solid #1f2937; background: #0b1221; color: #e2e8f0; display: grid; place-items: center; }
    .action-btn:hover { background: #132038; border-color: #334155; }
    @media (max-width: 1200px) {
        .promo-row { grid-template-columns: 1fr; align-items: start; }
    }
</style>

<div class="promo-shell max-w-7xl mx-auto px-4 space-y-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <p class="text-sm text-slate-400">Quản lý mã giảm giá, thời gian áp dụng và trạng thái.</p>
            <h1 class="text-2xl font-bold text-white mt-1">Quản lý Khuyến Mãi</h1>
        </div>
        <a href="{{ route('admin.promotions.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500 self-start md:self-end md:ml-auto">
            + Thêm Khuyến Mãi Mới
        </a>
    </div>

    <div class="promo-card p-4 space-y-3">
        <form method="GET" action="{{ route('admin.promotions.index') }}" class="flex flex-col md:flex-row gap-3">
            <div class="flex-1">
                <input type="text" name="q" value="{{ $filters['q'] }}" class="promo-input" placeholder="Tìm theo tên, mã khuyến mãi...">
            </div>
            <div>
                <select name="type" class="promo-select">
                    <option value="">Loại khuyến mãi</option>
                    <option value="percent" {{ $filters['type']==='percent' ? 'selected' : '' }}>Giảm giá %</option>
                    <option value="fixed" {{ $filters['type']==='fixed' ? 'selected' : '' }}>Giảm giá trực tiếp</option>
                </select>
            </div>
            <div>
                <select name="status" class="promo-select">
                    <option value="">Trạng thái</option>
                    <option value="active" {{ $filters['status']==='active' ? 'selected' : '' }}>Đang hoạt động</option>
                    <option value="upcoming" {{ $filters['status']==='upcoming' ? 'selected' : '' }}>Sắp diễn ra</option>
                    <option value="expired" {{ $filters['status']==='expired' ? 'selected' : '' }}>Hết hạn</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500">Lọc</button>
                <a href="{{ route('admin.promotions.index') }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm hover:bg-slate-700">Reset</a>
            </div>
        </form>
    </div>

    @if (session('success'))
        <div class="bg-emerald-500/10 border border-emerald-600 text-emerald-200 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="promo-card overflow-hidden">
        <div class="promo-row text-xs uppercase text-slate-400">
            <div>Tên khuyến mãi</div>
            <div>Loại</div>
            <div>Mã</div>
            <div>Ngày bắt đầu</div>
            <div>Ngày kết thúc</div>
            <div class="text-center">Giới hạn</div>
            <div>Trạng thái</div>
            <div class="text-right">Hành động</div>
        </div>
        @php $today = Carbon::today(); @endphp
        @forelse ($promotions as $promotion)
            @php
                $statusBadge = 'badge-active';
                $statusText = 'Đang hoạt động';
                if ($promotion->end_date < $today || $promotion->status === 'expired') {
                    $statusBadge = 'badge-expired';
                    $statusText = 'Hết hạn';
                } elseif ($promotion->start_date > $today) {
                    $statusBadge = 'badge-upcoming';
                    $statusText = 'Sắp diễn ra';
                }
                $typeText = $promotion->type === 'percent' ? 'Giảm giá %' : 'Giảm giá trực tiếp';
            @endphp
            <div class="promo-row hover:bg-[#0f1b2f] transition">
                <div class="font-semibold text-white">{{ $promotion->name }}</div>
                <div class="text-slate-300">{{ $typeText }}</div>
                <div class="text-slate-300">{{ $promotion->code }}</div>
                <div class="text-slate-300">{{ $promotion->start_date?->format('d/m/Y') }}</div>
                <div class="text-slate-300">{{ $promotion->end_date?->format('d/m/Y') }}</div>
                <div class="text-center text-slate-300">{{ $promotion->usage_limit ?? '—' }}</div>
                <div><span class="badge {{ $statusBadge }}">{{ $statusText }}</span></div>
                <div class="flex justify-end gap-2">
                    <a href="{{ route('admin.promotions.edit', $promotion) }}" class="action-btn" title="Chỉnh sửa">✏</a>
                    <form method="POST" action="{{ route('admin.promotions.destroy', $promotion) }}" onsubmit="return confirm('Xóa khuyến mãi này?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="action-btn" title="Xóa">🗑</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="p-6 text-center text-slate-400">Chưa có khuyến mãi nào.</div>
        @endforelse
    </div>

    <div class="flex items-center justify-between text-sm text-slate-400">
        <div>Hiển thị {{ $promotions->firstItem() ?? 0 }}–{{ $promotions->lastItem() ?? 0 }} của {{ $promotions->total() }}</div>
        <div>{{ $promotions->onEachSide(1)->links() }}</div>
    </div>
</div>
@endsection
