@extends('admin.layouts.app')
@section('title', 'Quản lý dịch vụ')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/foods.css') }}">
@endpush
@php use Illuminate\Support\Str; @endphp

@section('content')
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
                        $thumb = $item->imageUrl();
                    @endphp
                    @if($thumb)
                        <img src="{{ $thumb }}" alt="{{ $item->name }}" class="w-12 h-12 object-contain rounded-md border border-slate-700" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                        <span class="text-xs text-slate-400" hidden>Không tải được ảnh</span>
                    @else
                        <div class="w-12 h-12 rounded-md border border-dashed border-slate-700 text-slate-500 grid place-items-center text-xs text-center">Chưa có ảnh</div>
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
                    <a href="{{ route('admin.foods.edit', $item) }}" class="svc-btn" title="Chỉnh sửa">@include('admin.partials.icon', ['name'=>'pencil', 'size'=>18])</a>
                    <form method="POST" action="{{ route('admin.foods.destroy', $item) }}" data-confirm="Xóa mục này?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="svc-btn" title="Xóa">@include('admin.partials.icon', ['name'=>'trash-2', 'size'=>18])</button>
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



