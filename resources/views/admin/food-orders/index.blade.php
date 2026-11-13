@extends('admin.layouts.app')
@section('title', 'Quản lý bắp nước')
@include('admin.partials.directory-assets')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/food-orders.css') }}?v={{ filemtime(public_path('assets/css/admin/food-orders.css')) }}">
@endpush
@section('content')
@php
    $query = array_filter([
        'q' => $filters['q'], 'channel' => $filters['channel'],
        'from' => $filters['from'], 'to' => $filters['to'],
    ], fn ($value) => $value !== '');
    $viewUrl = fn ($view, $changes = []) => route('admin.food-orders.index', array_filter(
        array_replace($query, ['view' => $view], $changes), fn ($value) => $value !== ''
    ));
@endphp
<div class="ad-page food-admin">
    <header class="ad-heading">
        <div>
            <span class="ad-kicker">TD CINEMA · VẬN HÀNH BẮP NƯỚC</span>
            <h1>Quản lý bắp nước</h1>
            <p>Quản lý đơn online và tại quầy, đối chiếu mã nhận đồ rồi check-in khi giao cho khách.</p>
        </div>
        <a class="ad-button primary" href="{{ route('admin.food-orders.create') }}">
            @include('admin.partials.icon', ['name' => 'plus', 'size' => 17]) Bán tại quầy
        </a>
    </header>

    @if($errors->any())
        <div class="ad-panel food-admin__alert" data-inline-notification role="alert">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <nav class="ad-stats" aria-label="Lọc nhanh đơn bắp nước">
        <a class="ad-stat {{ $filters['view'] === 'all' && !$filters['channel'] ? 'is-selected' : '' }}" href="{{ $viewUrl('all') }}">
            <span>@include('admin.partials.icon', ['name' => 'popcorn', 'size' => 19]) Tất cả đơn</span>
            <strong>{{ number_format($counts['all']) }}</strong>
            <small>Đơn online và tại quầy</small>
        </a>
        <a class="ad-stat success {{ $filters['view'] === 'ready' ? 'is-selected' : '' }}" href="{{ $viewUrl('ready') }}">
            <span>@include('admin.partials.icon', ['name' => 'square-check', 'size' => 19]) Chờ check-in</span>
            <strong>{{ number_format($counts['ready']) }}</strong>
            <small>Đã thanh toán, chưa giao đồ</small>
        </a>
        <a class="ad-stat {{ $filters['view'] === 'history' ? 'is-selected' : '' }}" href="{{ $viewUrl('history') }}">
            <span>@include('admin.partials.icon', ['name' => 'archive', 'size' => 19]) Lịch sử bắp nước</span>
            <strong>{{ number_format($counts['history']) }}</strong>
            <small>Đã giao hoặc đã hủy</small>
        </a>
        <a class="ad-stat {{ $filters['channel'] === 'online' ? 'is-selected' : '' }}" href="{{ $viewUrl('all', ['channel' => 'online']) }}">
            <span>@include('admin.partials.icon', ['name' => 'globe', 'size' => 19]) Đặt online</span>
            <strong>{{ number_format($counts['online']) }}</strong>
            <small>Bắp nước mua kèm vé phim</small>
        </a>
    </nav>

    <section class="ad-panel">
        <nav class="ad-tabs" aria-label="Trạng thái danh sách">
            <a class="{{ $filters['view'] === 'ready' ? 'is-selected' : '' }}" href="{{ $viewUrl('ready') }}">Chờ check-in <b>{{ number_format($counts['ready']) }}</b></a>
            <a class="{{ $filters['view'] === 'all' ? 'is-selected' : '' }}" href="{{ $viewUrl('all') }}">Tất cả đơn <b>{{ number_format($counts['all']) }}</b></a>
            <a class="{{ $filters['view'] === 'history' ? 'is-selected' : '' }}" href="{{ $viewUrl('history') }}">Lịch sử bắp nước <b>{{ number_format($counts['history']) }}</b></a>
        </nav>

        <form class="ad-filter food-admin__filter" method="GET" action="{{ route('admin.food-orders.index') }}">
            <input type="hidden" name="view" value="{{ $filters['view'] }}">
            <label class="ad-search">
                <span>Tìm đơn, khách, phim hoặc món</span>
                <div>@include('admin.partials.icon', ['name' => 'search', 'size' => 17])
                    <input type="search" name="q" maxlength="100" value="{{ $filters['q'] }}" placeholder="Mã nhận đồ, tên khách, phim...">
                </div>
            </label>
            <label><span>Kênh đặt</span>
                <select name="channel">
                    <option value="">Tất cả kênh</option>
                    <option value="online" @selected($filters['channel'] === 'online')>Online kèm vé</option>
                    <option value="counter_ticket" @selected($filters['channel'] === 'counter_ticket')>Tại quầy kèm vé</option>
                    <option value="counter_only" @selected($filters['channel'] === 'counter_only')>Tại quầy độc lập</option>
                </select>
            </label>
            <label><span>Từ ngày</span><input type="date" name="from" value="{{ $filters['from'] }}"></label>
            <label><span>Đến ngày</span><input type="date" name="to" value="{{ $filters['to'] }}"></label>
            <button class="ad-button primary" type="submit">@include('admin.partials.icon', ['name' => 'list-filter', 'size' => 16]) Lọc đơn</button>
            <div class="ad-filter-footer"><a class="ad-reset" href="{{ route('admin.food-orders.index', ['view' => $filters['view']]) }}">Xóa bộ lọc</a></div>
        </form>

        <div class="ad-results"><strong>{{ number_format($orders->total()) }} đơn phù hợp</strong><span>Mới nhất trước · Xác nhận giao đồ riêng với check-in vé phim</span></div>
        <div class="ad-list">
            @forelse($orders as $order)
                @php
                    $channel = !$order->ticket_id ? 'Tại quầy · độc lập' : ($order->sold_by ? 'Tại quầy · kèm vé' : 'Online · kèm vé');
                    $payment = $order->payment ?? $order->ticket?->payment;
                    $paid = $order->isPaid();
                    $ready = $order->isReadyForPickup();
                    $fulfilledQuantity = $order->fulfilledQuantity();
                    $totalQuantity = $order->totalQuantity();
                    $state = $order->fulfilled_at ? 'Đã giao' : ($order->status === 'cancelled' ? 'Đã hủy' : ($paid ? ($fulfilledQuantity ? 'Đã giao '.$fulfilledQuantity.'/'.$totalQuantity.' phần' : 'Chờ check-in') : 'Chưa thanh toán'));
                    $stateTone = $order->fulfilled_at ? 'success' : ($order->status === 'cancelled' ? 'danger' : ($ready ? 'warning' : ''));
                    $itemCount = $order->items->sum('quantity');
                @endphp
                <article class="ad-record food-admin__record" data-food-order-id="{{ $order->id }}">
                    <div class="ad-identity">
                        <span class="food-admin__icon"><img src="{{ $order->items->first()?->foodItem?->imageUrl() ?? asset('assets/images/ui/food-placeholder.svg') }}" alt="Ảnh bắp nước trong đơn" loading="lazy" width="42" height="42"></span>
                        <div>
                            <span class="ad-ticket-code">Mã nhận đồ · {{ $order->pickup_code ?? '#'.$order->id }}</span>
                            <h2>{{ $itemCount ? $itemCount.' phần bắp nước' : 'Đơn bắp nước #'.$order->id }}</h2>
                            <p>{{ $channel }}</p>
                            @if($order->ticket?->showtime)
                                <p class="food-admin__movie"><img src="{{ $order->ticket->showtime->movie?->posterUrl() ?? asset('assets/images/ui/movie-placeholder.svg') }}" alt="Poster {{ $order->ticket->showtime->movie?->title ?? 'phim' }}" loading="lazy" width="28" height="40"><span>{{ $order->ticket->showtime->movie?->title ?? 'Phim' }} · {{ $order->ticket->showtime->start_time?->format('H:i · d/m/Y') }}</span></p>
                            @endif
                            @if($order->ticket_id)<div class="ad-badges"><span class="ad-badge">Vé #{{ $order->ticket_id }}</span></div>@endif
                        </div>
                    </div>
                    <div class="ad-meta food-admin__products">
                        <div><span>Sản phẩm</span>
                            @if($order->items->isNotEmpty())
                                <ul>@foreach($order->items as $item)<li><img src="{{ $item->foodItem?->imageUrl() ?? asset('assets/images/ui/food-placeholder.svg') }}" alt="{{ $item->foodItem?->name ?? 'Bắp nước' }}" loading="lazy" width="34" height="34"><span>{{ $item->quantity }}× {{ $item->foodItem?->name ?? 'Sản phẩm đã xóa' }} <small>· Đã giao {{ $item->fulfilled_quantity }}/{{ $item->quantity }}</small></span></li>@endforeach</ul>
                            @else
                                <small>{{ !$order->sold_by && $order->ticket_id ? 'Đơn online cũ chưa lưu chi tiết món' : 'Chưa có chi tiết sản phẩm' }}</small>
                            @endif
                        </div>
                    </div>
                    <div class="ad-meta">
                        <div><span>Khách hàng</span><strong>{{ $order->user?->name ?? 'Khách vãng lai' }}</strong><small>{{ $order->user?->phone ?: $order->user?->email }}</small></div>
                        <div><span>Tạo đơn lúc</span><time>{{ $order->created_at?->format('H:i · d/m/Y') ?? '—' }}</time></div>
                    </div>
                    <div class="ad-meta food-admin__money">
                        <div><span>Tổng đơn</span><strong class="ad-money">{{ number_format($order->total_price, 0, ',', '.') }} đ</strong></div>
                        <div><span>Thanh toán</span><small>{{ $paid ? 'Đã thanh toán' : ($payment?->status === 'refunded' ? 'Đã hoàn tiền' : 'Chưa thanh toán') }}</small></div>
                    </div>
                    <div class="ad-record-actions">
                        <a class="ad-button" href="{{ route('admin.food-orders.show', $order) }}">@include('admin.partials.icon', ['name' => 'eye', 'size' => 15]) Chi tiết</a>
                        <span class="ad-badge {{ $stateTone }} food-admin__status">
                            @if($order->fulfilled_at)@include('admin.partials.icon', ['name' => 'circle-check', 'size' => 15])@endif
                            {{ $state }}
                        </span>
                        @if($ready)
                            <form method="POST" action="{{ route('admin.food-orders.fulfill', $order) }}">
                                @csrf
                                <button class="ad-button success" type="submit">@include('admin.partials.icon', ['name' => 'square-check', 'size' => 15]) Check-in toàn bộ</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="ad-empty">
                    @include('admin.partials.icon', ['name' => 'popcorn', 'size' => 34])
                    <h3>Không có đơn bắp nước phù hợp</h3>
                    <p>Thử đổi bộ lọc hoặc chuyển sang danh sách tất cả đơn.</p>
                    <a class="ad-button" href="{{ $viewUrl('all', ['q' => '', 'channel' => '', 'from' => '', 'to' => '']) }}">Xem tất cả đơn</a>
                </div>
            @endforelse
        </div>
        @if($orders->hasPages())
            <div class="ad-pagination"><span>Hiển thị {{ $orders->firstItem() }}–{{ $orders->lastItem() }} / {{ $orders->total() }} đơn</span>{{ $orders->onEachSide(1)->links() }}</div>
        @endif
    </section>
</div>
@endsection
