@extends('admin.layouts.app')
@section('title', 'Giao dịch #'.$payment->id)
@include('admin.payments._assets')
@section('content')
@php
    $ticket = $payment->ticket;
    $customer = $ticket?->user;
    $showtime = $ticket?->showtime;
    $money = fn ($value) => number_format((float)$value, abs((float)$value - round((float)$value)) > .001 ? 2 : 0, ',', '.').' ₫';
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i') : 'Chưa ghi nhận';
    $tones = ['paid'=>'success', 'refunded'=>'purple', 'failed'=>'danger'];
    $ticketLabels = ['pending'=>'Chờ xác nhận', 'confirmed'=>'Đã xác nhận', 'used'=>'Đã check-in', 'cancelled'=>'Đã hủy'];
    $params = array_filter($filters, fn ($value) => $value !== '' && $value !== null);
@endphp
<div class="ad-page payment-page">
    <header class="ad-heading"><div><span class="ad-kicker">TD CINEMA · CHI TIẾT THANH TOÁN</span><h1>Giao dịch #{{ $payment->id }}</h1><p>Thông tin thanh toán và vé liên quan.</p></div><a class="ad-button" href="{{ route('admin.payments.index', $params) }}">@include('admin.partials.icon', ['name'=>'arrow-left', 'size'=>17]) Lịch sử thanh toán</a></header>

    <div class="pay-detail-grid">
        <div class="pay-detail-main">
            <section class="ad-panel pay-detail-card">
                <div class="pay-card-heading"><h2>@include('admin.partials.icon', ['name'=>'ticket', 'size'=>19]) Thông tin vé</h2>@if($ticket && auth()->user()->hasAdminPermission('tickets.manage'))<a class="pay-text-link" href="{{ route('admin.tickets.show', $ticket) }}">Xem vé @include('admin.partials.icon', ['name'=>'external-link', 'size'=>15])</a>@endif</div>
                <h3 class="pay-movie-title">{{ $showtime?->movie?->title ?? 'Không còn thông tin phim' }}</h3>
                <dl class="pay-detail-list">
                    <div><dt>Mã vé</dt><dd>#{{ $payment->ticket_id }}</dd></div>
                    <div><dt>Trạng thái vé</dt><dd>{{ $ticketLabels[$ticket?->status] ?? 'Không còn thông tin' }}</dd></div>
                    <div><dt>Rạp</dt><dd>{{ $showtime?->room?->cinema?->name ?? 'Không còn thông tin' }}</dd></div>
                    <div><dt>Phòng / ghế</dt><dd>{{ $showtime?->room?->name ?? '—' }} · {{ $ticket?->seat ? $ticket->seat->seat_row.$ticket->seat->seat_number : '—' }}</dd></div>
                    <div><dt>Suất chiếu</dt><dd>{{ $date($showtime?->start_time) }}</dd></div>
                    <div><dt>Ngày đặt vé</dt><dd>{{ $date($ticket?->booked_at ?? $ticket?->created_at) }}</dd></div>
                </dl>
            </section>
            <section class="ad-panel pay-detail-card">
                <div class="pay-card-heading"><h2>@include('admin.partials.icon', ['name'=>'users', 'size'=>19]) Khách hàng</h2>@if($customer && !$customer->trashed() && auth()->user()->hasAdminPermission('users.view'))<a class="pay-text-link" href="{{ route('admin.users.show', $customer) }}">Hồ sơ @include('admin.partials.icon', ['name'=>'external-link', 'size'=>15])</a>@endif</div>
                <dl class="pay-detail-list"><div><dt>Họ tên</dt><dd>{{ $customer?->name ?? 'Không còn thông tin' }}</dd></div><div><dt>Email</dt><dd>{{ $customer?->email ?? '—' }}</dd></div><div><dt>Số điện thoại</dt><dd>{{ $customer?->phone ?? '—' }}</dd></div><div><dt>Hóa đơn liên quan</dt><dd>{{ $ticket?->invoice?->invoice_code ?? 'Chưa có hóa đơn liên kết với vé này' }}</dd></div></dl>
            </section>
        </div>
        <aside class="ad-panel pay-detail-summary">
            <div class="pay-summary-icon">@include('admin.partials.icon', ['name'=>'wallet', 'size'=>27])</div>
            <p>Số tiền giao dịch</p><strong class="pay-summary-amount">{{ $money($payment->amount) }}</strong>
            <span class="ad-badge {{ $tones[$payment->status] ?? '' }}">{{ $statuses[$payment->status] ?? $payment->status }}</span>
            <dl class="pay-summary-list">
                <div><dt>Phương thức</dt><dd>{{ $methods[$payment->method] ?? $payment->method }}</dd></div>
                <div><dt>Ngày ghi nhận</dt><dd>{{ $date($payment->paid_at ?? $payment->created_at) }}</dd></div>
                <div><dt>Ngày thanh toán</dt><dd>{{ $date($payment->paid_at) }}</dd></div>
                <div><dt>Ngày tạo giao dịch</dt><dd>{{ $date($payment->created_at) }}</dd></div>
                <div><dt>Cập nhật lần cuối</dt><dd>{{ $date($payment->updated_at) }}</dd></div>
            </dl>
            @if($ticket)<a class="ad-button" href="{{ route('admin.payments.export', ['ticket_id'=>$ticket->id]) }}">@include('admin.partials.icon', ['name'=>'download', 'size'=>17]) Xuất giao dịch của vé</a>@endif
        </aside>
    </div>

    @if($ticket)
    <section class="ad-panel pay-history">
        <div class="pay-card-heading"><div><h2>Lịch sử thanh toán của vé #{{ $ticket->id }}</h2><p>Tối đa 10 giao dịch gần nhất. Mỗi dòng là một giao dịch riêng.</p></div><a class="pay-text-link" href="{{ route('admin.payments.index', ['ticket_id'=>$ticket->id]) }}">Xem tất cả @include('admin.partials.icon', ['name'=>'arrow-right', 'size'=>15])</a></div>
        <div class="pay-table-wrap" tabindex="0" role="region" aria-label="Lịch sử thanh toán của vé">
            <table class="pay-table pay-history-table"><thead><tr><th>Giao dịch</th><th>Ngày ghi nhận</th><th>Phương thức</th><th>Trạng thái</th><th class="pay-number">Số tiền</th></tr></thead><tbody>
            @foreach($relatedPayments as $row)
            <tr @class(['pay-current'=>$row->is($payment)])><td data-label="Giao dịch"><a class="pay-code" href="{{ route('admin.payments.show', $row) }}">#{{ $row->id }}</a>@if($row->is($payment))<small>Đang xem</small>@endif</td><td data-label="Ngày ghi nhận">{{ $date($row->paid_at ?? $row->created_at) }}</td><td data-label="Phương thức">{{ $methods[$row->method] ?? $row->method }}</td><td data-label="Trạng thái"><span class="ad-badge {{ $tones[$row->status] ?? '' }}">{{ $statuses[$row->status] ?? $row->status }}</span></td><td class="pay-number" data-label="Số tiền"><strong class="pay-amount">{{ $money($row->amount) }}</strong></td></tr>
            @endforeach
            </tbody></table>
        </div>
    </section>
    @endif
</div>
@endsection
