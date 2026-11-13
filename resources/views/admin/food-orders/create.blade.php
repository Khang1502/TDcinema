@extends('admin.layouts.app')
@section('title', 'Bán bắp nước tại quầy')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/quantity-stepper.css') }}?v={{ filemtime(public_path('assets/css/admin/quantity-stepper.css')) }}">
@endpush
@section('content')
<div class="max-w-5xl mx-auto px-4 text-slate-100 space-y-5">
    <div class="flex justify-between items-center gap-3"><div><p class="text-sm text-slate-400">Bắp nước · bán tại quầy không cần vé hoặc tài khoản khách</p><h1 class="text-3xl font-bold">Bán bắp nước tại quầy</h1></div><a class="text-blue-300" href="{{ route('admin.food-orders.index', ['view' => 'all']) }}">← Quay về Quản lý bắp nước</a></div>
    @if($errors->any())<div class="rounded-lg border border-red-700 bg-red-950/60 p-4 text-red-200" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('admin.food-orders.store') }}" class="space-y-5" id="food-sale-form">
        @csrf
        <input type="hidden" name="sale_token" value="{{ $saleToken }}">
        <p class="text-sm text-slate-400">Chọn tối đa {{ config('booking_limits.quantity_per_food_item') }} phần cho mỗi loại, giống trang đặt vé online.</p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($products as $product)
                <div class="rounded-xl border border-slate-700 bg-[#111a2f] p-4 space-y-3">
                    <div class="flex gap-3 items-center">
                        @if($product->imageUrl())<img src="{{ $product->imageUrl() }}" alt="" class="h-16 w-16 rounded-lg object-cover">@endif
                        <div><h2 class="font-semibold">{{ $product->name }}</h2><p class="text-sm text-slate-400">{{ number_format($product->price, 0, ',', '.') }}đ / phần</p></div>
                    </div>
                    <label class="block text-sm" for="qty-{{ $product->id }}">Số lượng</label>
                    <div class="quantity-stepper" data-quantity-stepper>
                        <button type="button" data-quantity-adjust="-1" aria-label="Giảm số lượng {{ $product->name }}">−</button>
                        <input id="qty-{{ $product->id }}" type="number" inputmode="numeric" min="0" max="{{ config('booking_limits.quantity_per_food_item') }}" value="{{ old('items.'.$product->id, 0) }}" name="items[{{ $product->id }}]" data-price="{{ $product->price }}">
                        <button type="button" data-quantity-adjust="1" aria-label="Tăng số lượng {{ $product->name }}">+</button>
                    </div>
                </div>
            @empty
                <p class="text-slate-400">Chưa có sản phẩm đang bán.</p>
            @endforelse
        </div>
        <div class="sticky bottom-3 rounded-xl border border-slate-600 bg-slate-900 p-4 flex flex-wrap justify-between items-center gap-3 shadow-xl">
            <div><p class="text-sm text-slate-400">Tổng tạm tính</p><strong class="text-2xl" id="food-total">0đ</strong><p class="text-xs text-slate-400">Giá được xác minh lại khi thu tiền.</p></div>
            <button type="submit" id="food-submit" class="rounded-lg bg-blue-600 px-5 py-3 font-semibold hover:bg-blue-500 disabled:opacity-50">Xác nhận đã thu tiền mặt</button>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('food-sale-form');
    const update = () => { const total = [...form.querySelectorAll('[data-price]')].reduce((sum, input) => sum + Number(input.value || 0) * Number(input.dataset.price), 0); document.getElementById('food-total').textContent = new Intl.NumberFormat('vi-VN').format(total) + 'đ'; };
    form.addEventListener('input', update);
    form.addEventListener('submit', event => { if (![...form.querySelectorAll('[data-price]')].some(input => Number(input.value) > 0)) { event.preventDefault(); alert('Vui lòng chọn sản phẩm.'); return; } document.getElementById('food-submit').disabled = true; });
    update();
});
</script>
@endsection
@push('scripts')
    <script src="{{ asset('assets/js/admin/quantity-stepper.js') }}?v={{ filemtime(public_path('assets/js/admin/quantity-stepper.js')) }}" defer></script>
@endpush
