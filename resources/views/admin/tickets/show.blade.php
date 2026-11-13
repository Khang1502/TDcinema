@extends('admin.layouts.app')
@section('title', 'Chi tiết vé #'.$ticket->id)

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/ticket-detail.css') }}?v={{ filemtime(public_path('assets/css/admin/ticket-detail.css')) }}">
@endpush

@section('content')
@php
    $showtime = $ticket->showtime;
    $movie = $showtime?->movie;
    $room = $showtime?->room;
    $seat = $ticket->seat;
    $payment = $ticket->payment;
    $payStatus = $payment?->status ?? 'unpaid';
    $isExpired = $ticket->cancellation_reason === \App\Models\Ticket::EXPIRED_REASON;
    $ticketLabels = ['pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận', 'used' => 'Đã check-in', 'cancelled' => 'Đã hủy'];
    $paymentLabels = ['unpaid' => 'Chưa thanh toán', 'pending' => 'Chờ thanh toán', 'paid' => 'Đã thanh toán', 'refunded' => 'Đã hoàn tiền', 'failed' => 'Thanh toán thất bại'];
    $ticketTones = ['pending' => 'warning', 'confirmed' => 'info', 'used' => 'success', 'cancelled' => 'danger'];
    $paymentTones = ['unpaid' => 'warning', 'pending' => 'warning', 'paid' => 'success', 'refunded' => 'info', 'failed' => 'danger'];
    $seatTypes = ['normal' => 'Ghế thường', 'vip' => 'Ghế VIP', 'sweetbox' => 'Ghế đôi'];
    $paymentMethods = ['cash' => 'Tiền mặt', 'momo' => 'MoMo', 'vnpay' => 'VNPay'];
    $seatLabel = $seat ? trim((string) $seat->seat_row.(string) $seat->seat_number) : '—';
    $start = $showtime?->start_time ? \Carbon\Carbon::parse($showtime->start_time)->format('H:i · d/m/Y') : 'Chưa có lịch chiếu';
    $end = $showtime?->end_time ? \Carbon\Carbon::parse($showtime->end_time)->format('H:i') : null;
    $createdAt = $ticket->created_at ? \Carbon\Carbon::parse($ticket->created_at)->format('H:i · d/m/Y') : '—';
    $paidAt = $payment?->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('H:i · d/m/Y') : '—';
    $comboTotal = (float) $bookingFoodOrders->sum('total_price');
    $ticketTotal = (float) $bookingTickets->sum('price');
    $grossTotal = $ticketTotal + $comboTotal;
    $grandTotal = (float) ($bookingInvoice?->total_amount ?? $grossTotal);
    $discountTotal = max(0, $grossTotal - $grandTotal);
    $canCancel = !in_array($ticket->status, ['cancelled', 'used'], true);
    $bookingReady = $bookingTickets->filter(fn ($item) => $item->status === 'confirmed' && $item->payment?->status === 'paid')->count();
    $bookingSeats = $bookingTickets->map(fn ($item) => ($item->seat?->seat_row ?? '').($item->seat?->seat_number ?? ''))->implode(', ');
@endphp

<div class="ticket-detail">
    <nav class="ticket-detail__topbar" aria-label="Điều hướng vé">
        <a class="ticket-detail__back" href="{{ route('admin.tickets.index') }}">
            @include('admin.partials.icon', ['name' => 'arrow-left', 'size' => 17])
            Quay về Quản lý vé
        </a>
        <span aria-current="page">Chi tiết vé #{{ $ticket->id }}</span>
    </nav>

    <header class="ticket-detail__header">
        <div class="ticket-detail__heading">
            <span class="ticket-detail__eyebrow">TD CINEMA · CHI TIẾT VÉ</span>
            <h1>Vé #{{ $ticket->id }}</h1>
            <p>{{ $movie?->title ?? 'Chưa có thông tin phim' }}</p>
            <div class="ticket-detail__statuses" aria-label="Trạng thái vé và thanh toán">
                <span class="ticket-detail__badge ticket-detail__badge--{{ $ticketTones[$ticket->status] ?? 'neutral' }}">
                    @include('admin.partials.icon', ['name' => 'ticket-check', 'size' => 15])
                    {{ $isExpired ? 'Quá hạn' : ($ticketLabels[$ticket->status] ?? $ticket->status) }}
                </span>
                <span class="ticket-detail__badge ticket-detail__badge--{{ $paymentTones[$payStatus] ?? 'neutral' }}">
                    @include('admin.partials.icon', ['name' => 'wallet', 'size' => 15])
                    {{ $paymentLabels[$payStatus] ?? $payStatus }}
                </span>
            </div>
            @if($isExpired)
                <p class="ticket-detail__expiry-note">Ghi chú hủy vé: Vé quá hạn. Suất chiếu đã kết thúc lúc {{ $showtime?->end_time?->format('H:i · d/m/Y') ?? '—' }}.</p>
            @endif
        </div>

        <div class="ticket-detail__actions" aria-label="Thao tác với vé">
            @if($bookingReady)
                <form method="POST" action="{{ route('admin.tickets.booking-checkin', $ticket) }}" data-confirm="Check-in toàn bộ {{ $bookingReady }} ghế còn lại đã thanh toán trong lượt đặt này?">
                    @csrf
                    <button type="submit" class="ticket-detail__button ticket-detail__button--primary">
                        @include('admin.partials.icon', ['name' => 'ticket-check', 'size' => 17])
                        Check-in cả lượt ({{ $bookingReady }} ghế)
                    </button>
                </form>
            @endif
            @if(auth()->user()->hasAdminPermission('payments.manage'))
                <a class="ticket-detail__button" href="{{ route('admin.payments.index', ['ticket_id' => $ticket->id]) }}">
                    @include('admin.partials.icon', ['name' => 'wallet', 'size' => 17])
                    Xem thanh toán
                </a>
            @endif
            @if($canCancel)
                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" data-confirm="Hủy vé #{{ $ticket->id }}?">
                    @csrf
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="ticket-detail__button ticket-detail__button--danger">
                        @include('admin.partials.icon', ['name' => 'circle-x', 'size' => 17])
                        Hủy vé
                    </button>
                </form>
            @endif
        </div>
    </header>

    @if($errors->any())
        <div class="ticket-detail__errors" data-inline-notification role="alert">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="ticket-detail__layout">
        <div class="ticket-detail__main">
            <section class="ticket-detail__card ticket-detail__screening" aria-labelledby="ticket-screening-title">
                <div class="ticket-detail__section-head">
                    <span class="ticket-detail__section-icon ticket-detail__section-icon--orange">
                        @include('admin.partials.icon', ['name' => 'clapperboard', 'size' => 19])
                    </span>
                    <div>
                        <span class="ticket-detail__section-kicker">SUẤT CHIẾU</span>
                        <h2 id="ticket-screening-title">Thông tin phim & lịch chiếu</h2>
                    </div>
                </div>
                <div class="ticket-detail__film">
                    <img src="{{ $movie?->posterUrl() ?? asset('assets/images/ui/movie-placeholder.svg') }}" alt="Poster {{ $movie?->title ?? 'phim' }}" loading="lazy" width="75" height="110">
                    <div><span class="ticket-detail__film-label">PHIM ĐÃ ĐẶT</span><strong>{{ $movie?->title ?? 'Chưa có thông tin phim' }}</strong></div>
                </div>
                <dl class="ticket-detail__facts">
                    <div>
                        <dt>Ngày & giờ chiếu</dt>
                        <dd>{{ $start }}@if($end) <span class="ticket-detail__subtle">→ {{ $end }}</span>@endif</dd>
                    </div>
                    <div>
                        <dt>Rạp chiếu</dt>
                        <dd>{{ $room?->cinema?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Phòng chiếu</dt>
                        <dd>{{ $room?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Kênh bán</dt>
                        <dd>{{ $ticket->sales_channel === 'counter' ? 'Tại quầy' : 'Trực tuyến' }}</dd>
                    </div>
                </dl>
            </section>

            <div class="ticket-detail__pair">
                <section class="ticket-detail__card" aria-labelledby="ticket-seat-title">
                    <div class="ticket-detail__section-head">
                        <span class="ticket-detail__section-icon ticket-detail__section-icon--blue">
                            @include('admin.partials.icon', ['name' => 'armchair', 'size' => 19])
                        </span>
                        <div>
                            <span class="ticket-detail__section-kicker">CHỖ NGỒI</span>
                            <h2 id="ticket-seat-title">Ghế đã chọn</h2>
                        </div>
                    </div>
                    <div class="ticket-detail__seat">
                        <strong>{{ $seatLabel }}</strong>
                        <span>{{ $seatTypes[strtolower($seat?->seat_type ?? '')] ?? ($seat?->seat_type ?: 'Chưa rõ loại ghế') }}</span>
                    </div>
                    <dl class="ticket-detail__facts ticket-detail__facts--single">
                        <div><dt>Phụ thu ghế</dt><dd>{{ number_format($seat?->extra_price ?? 0, 0, ',', '.') }} đ</dd></div>
                        <div><dt>Giá vé này</dt><dd>{{ number_format($ticket->price, 0, ',', '.') }} đ</dd></div>
                    </dl>
                </section>

                <section class="ticket-detail__card" aria-labelledby="ticket-customer-title">
                    <div class="ticket-detail__section-head">
                        <span class="ticket-detail__section-icon ticket-detail__section-icon--purple">
                            @include('admin.partials.icon', ['name' => 'users', 'size' => 19])
                        </span>
                        <div>
                            <span class="ticket-detail__section-kicker">KHÁCH HÀNG</span>
                            <h2 id="ticket-customer-title">Người mua vé</h2>
                        </div>
                    </div>
                    <div class="ticket-detail__customer">
                        <strong>{{ $ticket->user?->name ?? 'Khách vãng lai' }}</strong>
                        <span>{{ $ticket->user ? 'Thành viên' : 'Mua không cần tài khoản' }}</span>
                    </div>
                    <dl class="ticket-detail__facts ticket-detail__facts--single">
                        @if($ticket->user?->email)
                            <div><dt>Email</dt><dd class="ticket-detail__wrap">{{ $ticket->user->email }}</dd></div>
                        @endif
                        @if($ticket->user?->phone)
                            <div><dt>Số điện thoại</dt><dd>{{ $ticket->user->phone }}</dd></div>
                        @endif
                        @if($ticket->sales_channel === 'counter')
                            <div><dt>Nhân viên bán</dt><dd>{{ $ticket->seller?->name ?? 'Nhân viên' }}</dd></div>
                        @endif
                    </dl>
                </section>
            </div>

            <section class="ticket-detail__card" aria-labelledby="ticket-combo-title">
                <div class="ticket-detail__section-head ticket-detail__section-head--spread">
                    <div class="ticket-detail__section-head">
                        <span class="ticket-detail__section-icon ticket-detail__section-icon--yellow">
                            @include('admin.partials.icon', ['name' => 'popcorn', 'size' => 19])
                        </span>
                        <div>
                            <span class="ticket-detail__section-kicker">ĐỒ ĂN & THỨC UỐNG</span>
                            <h2 id="ticket-combo-title">Combo đi kèm lượt đặt</h2>
                        </div>
                    </div>
                    @if($bookingFoodOrders->isNotEmpty())
                        <strong class="ticket-detail__section-total">{{ number_format($comboTotal, 0, ',', '.') }} đ</strong>
                    @endif
                </div>
                @if($bookingFoodOrders->isEmpty())
                    <p class="ticket-detail__empty">Lượt đặt này không có combo đi kèm.</p>
                @else
                    <div class="ticket-detail__orders">
                        @foreach($bookingFoodOrders as $foodOrder)
                            <div class="ticket-detail__order {{ $foodOrder->fulfilled_at ? 'is-complete' : '' }}">
                                <div class="ticket-detail__order-head">
                                    <div>
                                        <span>Mã nhận đồ</span>
                                        <strong>{{ $foodOrder->pickup_code ?? '—' }}</strong>
                                    </div>
                                    <span class="ticket-detail__order-status {{ $foodOrder->fulfilled_at ? 'is-complete' : '' }}">
                                        @if($foodOrder->fulfilled_at)
                                            @include('admin.partials.icon', ['name' => 'circle-check', 'size' => 16])
                                        @endif
                                        {{ $foodOrder->fulfilled_at ? 'Đã nhận đủ' : ($foodOrder->status === 'cancelled' ? 'Đã hủy' : ($foodOrder->fulfilledQuantity() ? 'Đã nhận '.$foodOrder->fulfilledQuantity().'/'.$foodOrder->totalQuantity().' phần' : 'Chờ nhận đồ')) }}
                                    </span>
                                </div>
                                @if($foodOrder->items->isNotEmpty())
                                    <ul class="ticket-detail__order-items">
                                        @foreach($foodOrder->items as $item)
                                            <li>
                                                <img class="ticket-detail__food-thumb" src="{{ $item->foodItem?->imageUrl() ?? asset('assets/images/ui/food-placeholder.svg') }}" alt="{{ $item->foodItem?->name ?? 'Bắp nước' }}" loading="lazy" width="44" height="44">
                                                <span><b>{{ $item->quantity }}×</b> {{ $item->foodItem?->name ?? 'Món ăn' }} <small>· Đã giao {{ $item->fulfilled_quantity }}/{{ $item->quantity }}</small></span>
                                                <strong>{{ number_format($item->price * $item->quantity, 0, ',', '.') }} đ</strong>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if(auth()->user()->hasAdminPermission('food-sales.manage'))
                                    <div class="ticket-detail__order-controls">
                                        <a class="ticket-detail__button" href="{{ route('admin.food-orders.show', $foodOrder) }}">Chi tiết combo @include('admin.partials.icon', ['name' => 'arrow-right', 'size' => 14])</a>
                                        @if($foodOrder->isReadyForPickup())
                                            <form method="POST" action="{{ route('admin.food-orders.fulfill', $foodOrder) }}" data-confirm="Check-in toàn bộ bắp nước còn lại của đơn {{ $foodOrder->pickup_code ?? '#'.$foodOrder->id }}?">
                                                @csrf
                                                <button type="submit" class="ticket-detail__button ticket-detail__button--primary">Check-in combo</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                                <div class="ticket-detail__order-total"><span>Tổng đơn</span><strong>{{ number_format($foodOrder->total_price, 0, ',', '.') }} đ</strong></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="ticket-detail__card" aria-labelledby="ticket-payment-title">
                <div class="ticket-detail__section-head">
                    <span class="ticket-detail__section-icon ticket-detail__section-icon--green">
                        @include('admin.partials.icon', ['name' => 'wallet', 'size' => 19])
                    </span>
                    <div>
                        <span class="ticket-detail__section-kicker">GIAO DỊCH</span>
                        <h2 id="ticket-payment-title">Thông tin thanh toán</h2>
                    </div>
                </div>
                <dl class="ticket-detail__facts">
                    <div><dt>Trạng thái</dt><dd>{{ $paymentLabels[$payStatus] ?? $payStatus }}</dd></div>
                    <div><dt>Phương thức</dt><dd>{{ $paymentMethods[strtolower($payment?->method ?? '')] ?? ($payment?->method ?: '—') }}</dd></div>
                    <div><dt>Số tiền thanh toán</dt><dd>{{ number_format($payment?->amount ?? 0, 0, ',', '.') }} đ</dd></div>
                    <div><dt>Thanh toán lúc</dt><dd>{{ $paidAt }}</dd></div>
                    <div><dt>Tạo vé lúc</dt><dd>{{ $createdAt }}</dd></div>
                    @if($bookingInvoice?->invoice_code)
                        <div><dt>Mã hóa đơn</dt><dd class="ticket-detail__wrap">{{ $bookingInvoice->invoice_code }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>

        <aside class="ticket-detail__side" aria-label="Mã vé và tổng thanh toán">
            <section class="ticket-detail__pass" aria-labelledby="ticket-qr-title">
                <div class="ticket-detail__pass-top">
                    <span>TD CINEMA</span>
                    @include('admin.partials.icon', ['name' => 'ticket-check', 'size' => 22])
                </div>
                <div class="ticket-detail__pass-body">
                    <span class="ticket-detail__section-kicker">LƯỢT ĐẶT · {{ $bookingTickets->count() }} GHẾ</span>
                    <h2 id="ticket-qr-title">Mã check-in chung</h2>
                    <div class="ticket-detail__qr {{ !$bookingReady ? 'ticket-detail__qr--inactive' : '' }}">
                        <img src="{{ $qrDataUri }}" alt="Mã QR check-in lượt đặt {{ $qrText }}">
                    </div>
                    <p>{{ $bookingReady ? 'Mã này áp dụng cho các ghế còn chờ check-in trong lượt đặt.' : 'Không còn ghế đã thanh toán nào chờ check-in.' }}</p>
                    <span class="ticket-detail__qr-code">{{ $qrText }}</span>
                </div>
                <div class="ticket-detail__pass-foot">
                    <span>SUẤT CHIẾU <strong>{{ $start }}</strong></span>
                    <span>GHẾ <strong>{{ $bookingSeats }}</strong></span>
                </div>
            </section>

            <section class="ticket-detail__card ticket-detail__summary" aria-labelledby="ticket-summary-title">
                <div class="ticket-detail__section-head">
                    <span class="ticket-detail__section-icon ticket-detail__section-icon--orange">
                        @include('admin.partials.icon', ['name' => 'wallet', 'size' => 19])
                    </span>
                    <div>
                        <span class="ticket-detail__section-kicker">TỔNG QUAN</span>
                        <h2 id="ticket-summary-title">Tổng thanh toán</h2>
                    </div>
                </div>
                <dl class="ticket-detail__summary-lines">
                    <div>
                        <dt>Vé trong lượt đặt</dt>
                        <dd>{{ number_format($ticketTotal, 0, ',', '.') }} đ</dd>
                    </div>
                    <div><dt>Combo đi kèm</dt><dd>{{ number_format($comboTotal, 0, ',', '.') }} đ</dd></div>
                    @if($discountTotal > 0)<div><dt>Ưu đãi</dt><dd>-{{ number_format($discountTotal, 0, ',', '.') }} đ</dd></div>@endif
                </dl>
                @if($bookingInvoice?->promotion)
                    <p class="ticket-detail__discount">Đã áp dụng mã {{ $bookingInvoice->promotion->code }} · {{ $bookingInvoice->promotion->name }}</p>
                @elseif(($bookingInvoice?->membership_discount ?? 0) > 0)
                    <p class="ticket-detail__discount">Đã áp dụng ưu đãi hạng {{ $bookingInvoice->membership_level_name }}</p>
                @endif
                <div class="ticket-detail__grand-total"><span>Tổng cộng</span><strong>{{ number_format($grandTotal, 0, ',', '.') }} đ</strong></div>
                @if($bookingTickets->count() > 1)
                    <p class="ticket-detail__summary-note">Tổng của cả lượt đặt {{ $bookingTickets->count() }} ghế.</p>
                @endif
            </section>
        </aside>
    </div>

</div>
@endsection
