@extends('admin.layouts.app')
@section('title', 'Thêm dịch vụ')
@section('content')
<style>
    .svc-shell { color: #e2e8f0; font-family: 'Manrope','Inter',system-ui,sans-serif; }
    .svc-card { background: #0e1627; border: 1px solid #1f2937; border-radius: 14px; }
    .svc-input { background: #0b1221; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 14px; color: #e5e7eb; width: 100%; }
    .svc-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
    .svc-select { background: #0b1221; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 12px; color: #e5e7eb; min-width: 160px; }
    .svc-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
</style>

<div class="svc-shell max-w-4xl mx-auto px-4 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-slate-400">Thêm dịch vụ/đồ ăn (combo, snack...).</p>
            <h1 class="text-2xl font-bold text-white mt-1">Thêm Dịch Vụ</h1>
        </div>
        <a href="{{ route('admin.foods.index') }}" class="text-sm text-blue-300 hover:text-blue-200">← Quay lại danh sách</a>
    </div>

    @if ($errors->any())
        <div class="bg-red-500/10 border border-red-600 text-red-200 px-4 py-3 rounded-lg space-y-1">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.foods.store') }}" class="svc-card p-5 space-y-4">
        @csrf
        @include('admin.foods._form')
        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500">Lưu</button>
            <a href="{{ route('admin.foods.index') }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm hover:bg-slate-700">Hủy</a>
        </div>
    </form>
</div>
@endsection
