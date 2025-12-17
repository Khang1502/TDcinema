@extends('admin.layouts.app')
@section('title', 'Thêm khuyến mãi')
@section('content')
<style>
    .promo-shell { color: #e2e8f0; font-family: 'Manrope','Inter',system-ui,sans-serif; }
    .promo-card { background: #0e1627; border: 1px solid #1f2937; border-radius: 14px; }
    .promo-input { background: #0b1221; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 14px; color: #e5e7eb; width: 100%; }
    .promo-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
    .promo-select { background: #0b1221; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 12px; color: #e5e7eb; min-width: 160px; }
    .promo-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
</style>

<div class="promo-shell max-w-4xl mx-auto px-4 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-slate-400">Tạo mã khuyến mãi mới.</p>
            <h1 class="text-2xl font-bold text-white mt-1">Thêm Khuyến Mãi</h1>
        </div>
        <a href="{{ route('admin.promotions.index') }}" class="text-sm text-blue-300 hover:text-blue-200">← Quay lại danh sách</a>
    </div>

    @if ($errors->any())
        <div class="bg-red-500/10 border border-red-600 text-red-200 px-4 py-3 rounded-lg space-y-1">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.promotions.store') }}" enctype="multipart/form-data" class="promo-card p-5 space-y-4">
        @csrf
        @include('admin.promotions._form')
        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500">Lưu</button>
            <a href="{{ route('admin.promotions.index') }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm hover:bg-slate-700">Hủy</a>
        </div>
    </form>
</div>
@endsection
