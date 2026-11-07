@extends('admin.layouts.app')
@php $giftAdmin=request()->routeIs('admin.gifts.*'); $prefix=$giftAdmin ? 'admin.gifts' : 'admin.promotions'; @endphp
@section('title', $giftAdmin ? 'Chỉnh sửa quà tặng thành viên' : 'Chỉnh sửa khuyến mãi')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/promotions.css') }}?v={{ filemtime(public_path('assets/css/admin/promotions.css')) }}">
    @if($giftAdmin)<link rel="stylesheet" href="{{ asset('assets/css/admin/gifts.css') }}?v={{ filemtime(public_path('assets/css/admin/gifts.css')) }}">@endif
@endpush
@section('content')
<div class="promo-shell max-w-4xl mx-auto px-4 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-slate-400">{{ $giftAdmin ? 'Điều chỉnh quà tặng và điều kiện nhận voucher.' : 'Cập nhật mã giảm giá, banner và thời gian áp dụng.' }}</p>
            <h1 class="text-2xl font-bold text-white mt-1">{{ $giftAdmin ? 'Chỉnh sửa quà tặng thành viên' : 'Chỉnh sửa khuyến mãi' }}</h1>
        </div>
        <a href="{{ route($prefix.'.index') }}" class="text-sm text-blue-300 hover:text-blue-200">@include('admin.partials.icon',['name'=>'arrow-left','size'=>16]) Quay lại danh sách</a>
    </div>

    @if ($errors->any())
        <div data-inline-notification class="bg-red-500/10 border border-red-600 text-red-200 px-4 py-3 rounded-lg space-y-1">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route($prefix.'.update', $promotion) }}" enctype="multipart/form-data" class="promo-card p-5 space-y-4">
        @csrf
        @method('PUT')
        @include('admin.promotions._form')
        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500">Cập nhật</button>
            <a href="{{ route($prefix.'.index') }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm hover:bg-slate-700">Hủy</a>
        </div>
    </form>
</div>
@endsection
