@extends('admin.layouts.app')
@section('title', 'Quản lý dịch vụ')
@php use Illuminate\Support\Str; @endphp

@section('content')
<style>
    .svc-shell { color: #e2e8f0; font-family: 'Manrope','Inter',system-ui,sans-serif; }
    .svc-card { background: #0e1627; border: 1px solid #1f2937; border-radius: 14px; }
    .svc-input { background: #0b1221; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 14px; color: #e5e7eb; width: 100%; }
    .svc-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
    .svc-select { background: #0b1221; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 12px; color: #e5e7eb; min-width: 160px; }
    .svc-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
    .svc-row { display: grid; grid-template-columns: 32px 60px 2fr 2fr 1fr 1fr 100px; gap: 12px; align-items: center; padding: 12px 16px; border-bottom: 1px solid #1f2937; }
    .svc-row:last-child { border-bottom: none; }
    .badge { border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
    .badge-available { background: #0b3c2d; color: #22c55e; border: 1px solid #0f6142; }
    .badge-unavailable { background: #3b0d0d; color: #f87171; border: 1px solid #b91c1c; }
    .svc-actions { display: flex; gap: 8px; justify-content: flex-end; }
    .svc-btn { width: 32px; height: 32px; border-radius: 10px; border: 1px solid #1f2937; background: #0b1221; color: #e2e8f0; display: grid; place-items: center; }
    .svc-btn:hover { background: #132038; border-color: #334155; }
    @media (max-width: 1024px) {
        .svc-row { grid-template-columns: 1fr; align-items: start; }
        .svc-actions { justify-content: flex-start; }
    }
</style>

<div class="svc-shell max-w-7xl mx-auto px-4 space-y-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <p class="text-sm text-slate-400">Quản lý dịch vụ/đồ ăn (combo, snack, gửi xe...).</p>
            <h1 class="text-2xl font-bold text-white mt-1">Quản lý Dịch Vụ</h1>
        </div>
        <a href="{{ route('admin.foods.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500 self-start md:self-end md:ml-auto">
            + Thêm Dịch Vụ Mới
        </a>
    </div>

    <div class="svc-card p-4 space-y-3">
        <form method="GET" action="{{ route('admin.foods.index') }}" class="flex flex-col md:flex-row gap-3">
            <div class="flex-1">
                <input type="text" name="q" value="{{ $filters['q'] }}" class="svc-input" placeholder="Tìm kiếm dịch vụ...">
            </div>
            <div>
                <select name="status" class="svc-select">
                    <option value="">Trạng thái: Tất cả</option>
                    <option value="available" {{ $filters['status']==='available' ? 'selected' : '' }}>Hiện có</option>
                    <option value="unavailable" {{ $filters['status']==='unavailable' ? 'selected' : '' }}>Ngừng cung cấp</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500">Lọc</button>
                <a href="{{ route('admin.foods.index') }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm hover:bg-slate-700">Reset</a>
            </div>
        </form>
    </div>

    @if (session('success'))
        <div class="bg-emerald-500/10 border border-emerald-600 text-emerald-200 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="svc-card overflow-hidden">
        <div class="svc-row text-xs uppercase text-slate-400">
            <div></div>
            <div>Ảnh</div>
            <div>Tên dịch vụ</div>
            <div>Mô tả</div>
            <div>Giá</div>
            <div>Trạng thái</div>
            <div class="text-right">Hành động</div>
        </div>
        @forelse ($items as $item)
            <div class="svc-row hover:bg-[#0f1b2f] transition">
                <div>
                    <input type="checkbox" class="rounded border-slate-600 bg-slate-900">
                </div>
                <div>
                    @php
                        $thumb = $item->image ? asset('storage/'.$item->image) : null;
                    @endphp
                    @if($thumb)
                        <img src="{{ $thumb }}" alt="{{ $item->name }}" class="w-12 h-12 object-cover rounded-md border border-slate-700">
                    @else
                        <div class="w-12 h-12 rounded-md border border-dashed border-slate-700 text-slate-500 grid place-items-center text-xs">No img</div>
                    @endif
                </div>
                <div class="font-semibold text-white">{{ $item->name }}</div>
                <div class="text-slate-400 text-sm">{{ Str::limit($item->description ?? '—', 60) }}</div>
                <div class="text-slate-200 font-semibold">{{ number_format($item->price, 0, ',', '.') }} VND</div>
                <div>
                    @php
                        $badgeClass = $item->status === 'available' ? 'badge-available' : 'badge-unavailable';
                        $badgeText = $item->status === 'available' ? 'Hiện có' : 'Ngừng cung cấp';
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $badgeText }}</span>
                </div>
                <div class="svc-actions">
                    <a href="{{ route('admin.foods.edit', $item) }}" class="svc-btn" title="Chỉnh sửa">✏</a>
                    <form method="POST" action="{{ route('admin.foods.destroy', $item) }}" onsubmit="return confirm('Xóa mục này?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="svc-btn" title="Xóa">🗑</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="p-6 text-center text-slate-400">Chưa có dịch vụ nào.</div>
        @endforelse
    </div>

    <div class="flex items-center justify-between text-sm text-slate-400">
        <div>Hiển thị {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} của {{ $items->total() }}</div>
        <div>{{ $items->onEachSide(1)->links() }}</div>
    </div>
</div>
@endsection
