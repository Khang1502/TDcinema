@extends('admin.layouts.app')
@php $giftAdmin=request()->routeIs('admin.gifts.*'); $prefix=$giftAdmin ? 'admin.gifts' : 'admin.promotions'; @endphp
@section('title', $giftAdmin ? 'Quản lý quà tặng thành viên' : 'Quản lý khuyến mãi')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin/promotions.css') }}?v={{ filemtime(public_path('assets/css/admin/promotions.css')) }}">
@endpush
@section('content')
<div class="promo-shell promo-admin">
<div class="promo-admin-heading"><div><p class="promo-admin-kicker">{{ $giftAdmin ? 'TD STARS · QUYỀN LỢI RIÊNG' : 'ƯU ĐÃI & THANH TOÁN' }}</p><h1>{{ $giftAdmin ? 'Quà tặng thành viên' : 'Quản lý khuyến mãi' }}</h1><p>{{ $giftAdmin ? 'Voucher theo điểm Stars. Hiển thị riêng trong tài khoản thành viên đủ điều kiện.' : 'Quản lý các chương trình chung, banner và mã giảm giá cho khách đặt vé.' }}</p></div><div class="promo-admin-actions">@if(!$giftAdmin)<a class="promo-admin-button" href="{{ route('admin.promotions.display') }}">Hiển thị trang chủ</a>@endif<a class="promo-admin-button primary" href="{{ route($prefix.'.create') }}">+ {{ $giftAdmin ? 'Thêm quà tặng' : 'Thêm khuyến mãi' }}</a></div></div>
<div class="promo-admin-stats"><div><span>{{ $giftAdmin ? 'Tổng quà thành viên' : 'Tổng khuyến mãi' }}</span><strong>{{ $counts['total'] }}</strong></div><div><span>{{ $giftAdmin ? 'Voucher đang áp dụng' : 'Dùng được khi thanh toán' }}</span><strong>{{ $counts['active'] }}</strong></div><div><span>Sắp diễn ra</span><strong>{{ $counts['upcoming'] }}</strong></div></div>
<form class="promo-admin-filters" method="GET" action="{{ route($prefix.'.index') }}">
<label><span>{{ $giftAdmin ? 'Tên quà hoặc mã voucher' : 'Tên hoặc mã khuyến mãi' }}</span><input name="q" value="{{ $filters['q'] }}" class="promo-input" placeholder="{{ $giftAdmin ? 'Tìm quà thành viên...' : 'Tìm khuyến mãi...' }}"></label>
<label><span>Loại giảm giá</span><select name="type" class="promo-select"><option value="">Tất cả loại</option><option value="percent" @selected($filters['type'] === 'percent')>Theo phần trăm</option><option value="fixed" @selected($filters['type'] === 'fixed')>Theo số tiền</option></select></label>
<label><span>Trạng thái</span><select name="status" class="promo-select"><option value="">Tất cả trạng thái</option>@foreach(['active'=>'Đang áp dụng','upcoming'=>'Sắp diễn ra','expired'=>'Hết hạn / đã tắt','exhausted'=>'Hết lượt'] as $key=>$label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select></label>
<div><button class="promo-admin-button primary" type="submit">Tìm kiếm</button><a class="promo-admin-button" href="{{ route($prefix.'.index') }}">Đặt lại</a></div>
</form>
@if($giftAdmin)
<div class="member-gift-admin-grid">
@forelse($promotions as $promotion)
@php $state = $promotion->display_status; @endphp
<article class="member-gift-admin-card" data-promotion-id="{{ $promotion->id }}">
    <div class="member-gift-admin-top"><span>@include('admin.partials.icon',['name'=>'gift','size'=>16]) TD STARS</span><span class="promo-admin-status {{ $state }}">{{ ['active'=>'Đang áp dụng','upcoming'=>'Sắp diễn ra','expired'=>'Hết hạn / đã tắt','exhausted'=>'Hết lượt'][$state] }}</span></div>
    @if($image=$promotion->imageUrl())<img src="{{ $image }}" alt="{{ $promotion->name }}" loading="lazy">@endif
    <strong class="member-gift-admin-value">Giảm {{ $promotion->discount_label }}</strong><h2>{{ $promotion->name }}</h2><code>{{ $promotion->code }}</code>
    <div class="member-gift-admin-condition">{{ $promotion->min_member_points ? 'Từ '.number_format($promotion->min_member_points,0,',','.').' Stars' : 'Dành cho mọi thành viên' }}</div>
    <p>{{ $promotion->start_date->format('d/m/Y') }} – {{ $promotion->end_date->format('d/m/Y') }}</p><p>{{ $promotion->usage_limit === null ? 'Không giới hạn lượt' : 'Còn '.number_format($promotion->usage_limit).' lượt' }}</p>
    <div class="promo-admin-actions"><a class="promo-admin-button" href="{{ route($prefix.'.edit', $promotion) }}">Chỉnh sửa quà</a><form method="POST" action="{{ route($prefix.'.destroy', $promotion) }}" data-confirm="Xóa quà tặng thành viên này?">@csrf @method('DELETE')<button class="promo-admin-button danger" type="submit">Xóa</button></form></div>
</article>
@empty<div class="promo-admin-empty">Chưa có quà thành viên phù hợp. Thêm voucher mới hoặc thử bộ lọc khác.</div>@endforelse
</div>
@else
<div class="promo-admin-list">
<div class="promo-admin-row promo-admin-row--header"><span>Khuyến mãi</span><span>Mức giảm</span><span>Thời gian áp dụng</span><span>Lượt còn lại</span><span>Hành động</span></div>
@forelse($promotions as $promotion)
@php $state = $promotion->display_status; @endphp
<article class="promo-admin-row" data-promotion-id="{{ $promotion->id }}">
<div class="promo-admin-identity">@if($image=$promotion->imageUrl())<img src="{{ $image }}" alt="{{ $promotion->name }}" loading="lazy">@else<span class="promo-admin-image-placeholder">%</span>@endif<div><h2>{{ $promotion->name }}</h2><code>{{ $promotion->code }}</code><span class="promo-admin-status {{ $state }}">{{ ['active'=>'Đang áp dụng','upcoming'=>'Sắp diễn ra','expired'=>'Hết hạn / đã tắt','exhausted'=>'Hết lượt'][$state] }}</span></div></div>
<div class="promo-admin-amount"><small>Mức giảm</small><strong>{{ $promotion->discount_label }}</strong></div>
<div class="promo-admin-dates"><small>Thời gian</small><span>{{ $promotion->start_date->format('d/m/Y') }}</span><span>đến {{ $promotion->end_date->format('d/m/Y') }}</span></div>
<div class="promo-admin-limit"><small>Lượt còn lại</small><strong>{{ $promotion->usage_limit === null ? 'Không giới hạn' : number_format($promotion->usage_limit) }}</strong></div>
<div class="promo-admin-actions"><a class="promo-admin-button" href="{{ route($prefix.'.edit', $promotion) }}">Chỉnh sửa</a><form method="POST" action="{{ route($prefix.'.destroy', $promotion) }}" data-confirm="Xóa khuyến mãi này?">@csrf @method('DELETE')<button class="promo-admin-button danger" type="submit">Xóa</button></form></div>
</article>
@empty<div class="promo-admin-empty">Chưa có khuyến mãi phù hợp. Thêm mã mới hoặc thử bộ lọc khác.</div>@endforelse
</div>
@endif
<div class="promo-admin-pagination"><span>Hiển thị {{ $promotions->firstItem() ?? 0 }}–{{ $promotions->lastItem() ?? 0 }} / {{ $promotions->total() }}</span>{{ $promotions->onEachSide(1)->links() }}</div>
</div>
@endsection
