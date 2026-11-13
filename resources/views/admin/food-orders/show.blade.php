@extends('admin.layouts.app')
@section('title', 'Đơn bắp nước '.($order->pickup_code ?? '#'.$order->id))
@include('admin.partials.directory-assets')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/food-orders.css') }}?v={{ filemtime(public_path('assets/css/admin/food-orders.css')) }}">
@endpush
@section('content')
@php
    $channel = !$order->ticket_id ? 'Tại quầy · độc lập' : ($order->sold_by ? 'Tại quầy · kèm vé' : 'Online · kèm vé');
    $payment = $order->payment ?? $order->ticket?->payment;
    $paid = $order->isPaid();
    $ready = $order->isReadyForPickup();
    $fulfilledQuantity = $order->fulfilledQuantity();
    $totalQuantity = $order->totalQuantity();
    $remainingQuantity = max(0, $totalQuantity - $fulfilledQuantity);
    $state = $order->fulfilled_at ? 'Đã giao' : ($order->status === 'cancelled' ? 'Đã hủy' : ($paid ? ($fulfilledQuantity ? 'Đã giao '.$fulfilledQuantity.'/'.$totalQuantity.' phần' : 'Chờ check-in') : 'Chưa thanh toán'));
    $tone = $order->fulfilled_at ? 'success' : ($order->status === 'cancelled' ? 'danger' : ($ready ? 'warning' : ''));
    $showtime = $order->ticket?->showtime;
@endphp
<div class="ad-page food-detail">
    <nav class="food-detail__nav" aria-label="Điều hướng bắp nước">
        <a class="ad-button" href="{{ route('admin.food-orders.index') }}">
            @include('admin.partials.icon', ['name' => 'arrow-left', 'size' => 16]) Quay về Quản lý bắp nước
        </a>
        <span>Đơn #{{ $order->id }}</span>
    </nav>
    <header class="ad-heading">
        <div>
            <span class="ad-kicker">TD CINEMA · CHI TIẾT ĐƠN BẮP NƯỚC</span>
            <h1>{{ $order->pickup_code ?? 'Đơn #'.$order->id }}</h1>
            <p>{{ $channel }} · Tạo lúc {{ $order->created_at?->format('H:i · d/m/Y') ?? '—' }}</p>
            <div class="ad-badges"><span class="ad-badge {{ $tone }}">{{ $state }}</span><span class="ad-badge {{ $paid ? 'success' : 'warning' }}">{{ $paid ? 'Đã thanh toán' : 'Chưa thanh toán' }}</span></div>
        </div>
        @if($ready)
            <form method="POST" action="{{ route('admin.food-orders.fulfill', $order) }}">
                @csrf
                <button class="ad-button success" type="submit">@include('admin.partials.icon', ['name' => 'square-check', 'size' => 17]) {{ $totalQuantity ? 'Check-in toàn bộ '.$remainingQuantity.' phần còn lại' : 'Xác nhận đã giao bắp nước' }}</button>
            </form>
        @endif
    </header>
    @if($errors->any())
        <div class="ad-panel food-admin__alert" data-inline-notification role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif
    <div class="food-detail__layout">
        <div class="food-detail__main">
            <section class="ad-panel" aria-labelledby="food-items-title">
                <div class="food-detail__section-title">
                    <span class="food-detail__section-icon">@include('admin.partials.icon', ['name' => 'popcorn', 'size' => 19])</span>
                    <div><span class="ad-kicker">ĐƠN HÀNG</span><h2 id="food-items-title">Bắp nước cần giao</h2></div>
                </div>
                @if($totalQuantity)
                    <p class="food-detail__progress">Lượt đặt gồm {{ $totalQuantity }} phần · Đã giao {{ $fulfilledQuantity }} · Còn {{ $remainingQuantity }} phần</p>
                @endif
                @if($order->items->isEmpty())
                    <p class="food-detail__legacy">{{ !$order->sold_by && $order->ticket_id ? 'Đơn online cũ chưa lưu chi tiết từng món.' : 'Đơn này chưa có chi tiết sản phẩm.' }} Đối chiếu tổng tiền và mã nhận đồ với khách trước khi giao.</p>
                @else
                    <ul class="food-detail__items">
                        @foreach($order->items as $item)
                            <li>
                                <img class="food-detail__thumb" src="{{ $item->foodItem?->imageUrl() ?? asset('assets/images/ui/food-placeholder.svg') }}" alt="{{ $item->foodItem?->name ?? 'Bắp nước' }}" loading="lazy" width="48" height="48">
                                <span class="food-detail__qty">{{ $item->quantity }}×</span>
                                <div>
                                    <strong>{{ $item->foodItem?->name ?? 'Sản phẩm đã xóa' }}</strong>
                                    <small>{{ number_format($item->price, 0, ',', '.') }} đ / phần · Đã giao {{ $item->fulfilled_quantity }}/{{ $item->quantity }}</small>
                                    @if($item->fulfilled_at)<small>Hoàn tất lúc {{ $item->fulfilled_at->format('H:i · d/m/Y') }}</small>@endif
                                </div>
                                <b>{{ number_format($item->price * $item->quantity, 0, ',', '.') }} đ</b>
                                @if($ready && $item->remainingQuantity() > 0)
                                    <form class="food-detail__item-actions" method="POST" action="{{ route('admin.food-orders.items.fulfill', [$order, $item]) }}">
                                        @csrf
                                        <button class="ad-button success" type="submit" name="quantity" value="1">Check-in 1 phần</button>
                                        @if($item->remainingQuantity() > 1)
                                            <button class="ad-button" type="submit" name="quantity" value="{{ $item->remainingQuantity() }}">Cả {{ $item->remainingQuantity() }} phần</button>
                                        @endif
                                    </form>
                                @elseif($item->remainingQuantity() === 0)
                                    <span class="ad-badge success">Đã giao đủ</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if($order->ticket_id)
                <section class="ad-panel" aria-labelledby="food-ticket-title">
                    <div class="food-detail__section-title">
                        <span class="food-detail__section-icon food-detail__section-icon--blue">@include('admin.partials.icon', ['name' => 'ticket-check', 'size' => 19])</span>
                        <div><span class="ad-kicker">MUA KÈM VÉ PHIM</span><h2 id="food-ticket-title">Suất chiếu liên quan</h2></div>
                    </div>
                    <div class="food-detail__movie">
                        <img src="{{ $showtime?->movie?->posterUrl() ?? asset('assets/images/ui/movie-placeholder.svg') }}" alt="Poster {{ $showtime?->movie?->title ?? 'phim' }}" loading="lazy" width="56" height="80">
                        <strong>{{ $showtime?->movie?->title ?? 'Phim' }}</strong>
                    </div>
                    <dl class="food-detail__facts">
                        <div><dt>Phim</dt><dd>{{ $showtime?->movie?->title ?? '—' }}</dd></div>
                        <div><dt>Suất chiếu</dt><dd>{{ $showtime?->start_time?->format('H:i · d/m/Y') ?? '—' }}</dd></div>
                        <div><dt>Rạp / phòng</dt><dd>{{ $showtime?->room?->cinema?->name ?? '—' }} · {{ $showtime?->room?->name ?? '—' }}</dd></div>
                        <div><dt>Vé liên kết</dt><dd>#{{ $order->ticket_id }} @if(auth()->user()->hasAdminPermission('tickets.manage'))<a href="{{ route('admin.tickets.show', $order->ticket_id) }}">Xem vé @include('admin.partials.icon', ['name' => 'arrow-right', 'size' => 13])</a>@endif</dd></div>
                    </dl>
                </section>
            @endif
        </div>
        <aside class="food-detail__side" aria-label="Thông tin nhận đồ">
            <section class="food-detail__pickup">
                <span class="ad-kicker">MÃ NHẬN BẮP NƯỚC</span>
                <strong>{{ $order->pickup_code ?? '#'.$order->id }}</strong>
                <p>Đối chiếu mã này với khách trước khi xác nhận giao đồ.</p>
                <span class="ad-badge {{ $tone }}">{{ $state }}</span>
            </section>
            <section class="ad-panel" aria-labelledby="food-customer-title">
                <div class="food-detail__section-title">
                    <span class="food-detail__section-icon food-detail__section-icon--purple">@include('admin.partials.icon', ['name' => 'users', 'size' => 19])</span>
                    <div><span class="ad-kicker">NGƯỜI MUA</span><h2 id="food-customer-title">Khách hàng</h2></div>
                </div>
                <dl class="food-detail__facts">
                    <div><dt>Tên khách</dt><dd>{{ $order->user?->name ?? 'Khách vãng lai' }}</dd></div>
                    @if($order->user?->phone)<div><dt>Số điện thoại</dt><dd>{{ $order->user->phone }}</dd></div>@endif
                    @if($order->user?->email)<div><dt>Email</dt><dd class="food-detail__wrap">{{ $order->user->email }}</dd></div>@endif
                    @if($order->sold_by)<div><dt>Nhân viên bán</dt><dd>{{ $order->seller?->name ?? 'Nhân viên' }}</dd></div>@endif
                </dl>
            </section>
            <section class="ad-panel" aria-labelledby="food-payment-title">
                <div class="food-detail__section-title">
                    <span class="food-detail__section-icon food-detail__section-icon--green">@include('admin.partials.icon', ['name' => 'wallet', 'size' => 19])</span>
                    <div><span class="ad-kicker">THANH TOÁN & GIAO ĐỒ</span><h2 id="food-payment-title">Tổng quan đơn</h2></div>
                </div>
                <dl class="food-detail__facts">
                    <div><dt>Giá trị bắp nước</dt><dd>{{ number_format($order->total_price, 0, ',', '.') }} đ</dd></div>
                    <div><dt>Thanh toán</dt><dd>{{ $paid ? 'Đã thanh toán' : ($payment?->status === 'refunded' ? 'Đã hoàn tiền' : 'Chưa thanh toán') }}</dd></div>
                    <div><dt>Phương thức</dt><dd>{{ ['cash' => 'Tiền mặt', 'momo' => 'MoMo', 'vnpay' => 'VNPay'][$payment?->method ?? ''] ?? ($payment?->method ?: '—') }}</dd></div>
                    <div><dt>Nhận đồ lúc</dt><dd>{{ $order->fulfilled_at?->format('H:i · d/m/Y') ?? 'Chưa giao' }}</dd></div>
                </dl>
                @if(!$order->payment && $order->ticket_id)<p class="food-detail__note">Đơn bắp nước được thanh toán chung với vé phim.</p>@endif
            </section>
        </aside>
    </div>
</div>
@endsection
