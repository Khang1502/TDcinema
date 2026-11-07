@extends('admin.layouts.app')
@section('title', 'Phương thức thanh toán')
@include('admin.payment-methods._assets')
@section('content')
<div class="ad-page payment-page">
    <header class="ad-heading"><div><span class="ad-kicker">TD CINEMA · CẤU HÌNH THANH TOÁN</span><h1>Phương thức thanh toán</h1><p>Điều chỉnh nội dung và những lựa chọn thanh toán khách hàng có thể sử dụng.</p></div>@if(auth()->user()->hasAdminPermission('payments.manage'))<a class="ad-button" href="{{ route('admin.payments.index') }}">@include('admin.partials.icon', ['name'=>'wallet', 'size'=>17]) Giao dịch thanh toán</a>@endif</header>
    <div class="pm-overview"><span><i class="pm-dot"></i><strong>{{ $methods->where('is_online', true)->where('is_enabled', true)->count() }}</strong> phương thức trực tuyến đang bật</span><span>{{ $methods->where('is_online', true)->where('is_enabled', false)->count() }} đang tạm dừng</span><span>{{ $methods->where('is_online', false)->count() }} phương thức tại quầy</span></div>
    <aside class="pm-notice">@include('admin.partials.icon', ['name'=>'info', 'size'=>19])<p>Thứ tự nhỏ hiển thị trước tại bước thanh toán. Tạm dừng sẽ ngừng nhận lượt thanh toán mới; lịch sử giao dịch vẫn được giữ.</p></aside>

    @foreach([true=>'Thanh toán trực tuyến', false=>'Thanh toán tại quầy'] as $online=>$heading)
    <section class="pm-section" aria-label="{{ $heading }}">
        <header class="pm-section-heading"><h2>{{ $heading }}</h2><span>{{ $methods->where('is_online', (bool)$online)->count() }} phương thức</span></header>
        <div class="pm-grid">
            @foreach($methods->where('is_online', (bool)$online) as $method)
            <article class="pm-card {{ !$method->is_enabled ? 'is-paused' : '' }}">
                <div class="pm-card-heading"><span class="pm-brand" data-method="{{ $method->code }}" aria-hidden="true">{{ $method->monogram() }}</span><div><h3>{{ $method->name }}</h3><code>{{ $method->code }}</code></div><span class="ad-badge {{ $method->is_enabled ? 'success' : 'warning' }}">{{ $method->is_enabled ? 'Đang bật' : 'Tạm dừng' }}</span></div>
                <p class="pm-description">{{ $method->description ?: 'Chưa có mô tả. Bạn có thể bổ sung khi chỉnh sửa.' }}</p>
                <div class="pm-meta"><span class="ad-badge {{ $method->isDemo() ? 'purple' : '' }}">{{ !$method->is_online ? 'Tại quầy' : ($method->isDemo() ? 'Mô phỏng' : 'Cổng MoMo') }}</span>@if($method->badge)<span class="ad-badge">{{ $method->badge }}</span>@endif<span class="pm-order">Thứ tự {{ $method->sort_order }}</span></div>
                @if($method->instructions)<p class="pm-instructions">{{ \Illuminate\Support\Str::limit($method->instructions, 140) }}</p>@endif
                <footer class="pm-card-actions"><a class="ad-button primary" href="{{ route('admin.payment-methods.edit', $method) }}" aria-label="Chỉnh sửa {{ $method->name }}">@include('admin.partials.icon', ['name'=>'pencil', 'size'=>16]) Chỉnh sửa</a><form method="POST" action="{{ route('admin.payment-methods.visibility', $method) }}" @if($method->is_enabled) data-confirm="Tạm dừng {{ $method->name }} cho các lượt thanh toán mới?" data-confirm-danger="false" @endif>@csrf @method('PATCH')<input type="hidden" name="is_enabled" value="{{ $method->is_enabled ? '0' : '1' }}"><button class="ad-button {{ $method->is_enabled ? '' : 'success' }}" type="submit">{{ $method->is_enabled ? 'Tạm dừng' : 'Bật phương thức' }}</button></form></footer>
            </article>
            @endforeach
        </div>
    </section>
    @endforeach
</div>
@endsection
