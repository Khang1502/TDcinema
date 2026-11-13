@extends('admin.layouts.app')
@section('title', 'Lịch sử thanh toán')
@include('admin.payments._assets')
@section('content')
@php
    $money = fn ($value) => number_format((float)$value, abs((float)$value - round((float)$value)) > .001 ? 2 : 0, ',', '.').' ₫';
    $date = fn ($value, $format = 'd/m/Y H:i') => $value ? \Carbon\Carbon::parse($value)->format($format) : '—';
    $params = array_filter($filters, fn ($value) => $value !== '' && $value !== null);
    $tones = ['paid'=>'success', 'refunded'=>'purple', 'failed'=>'danger'];
@endphp
<div class="ad-page payment-page">
    <header class="ad-heading">
        <div><span class="ad-kicker">TD CINEMA · GIAO DỊCH</span><h1>Lịch sử thanh toán</h1><p>Tra cứu khoản thu, theo dõi trạng thái và đối chiếu thanh toán của từng vé.</p></div>
        <div class="ad-actions">
            @if(auth()->user()->hasAdminPermission('payment-methods.manage'))
            <a class="ad-button" href="{{ route('admin.payment-methods.index') }}">@include('admin.partials.icon', ['name'=>'settings', 'size'=>17]) Phương thức thanh toán</a>
            @endif
            @if(auth()->user()->hasAdminPermission('reports.view'))
            <a class="ad-button" href="{{ route('admin.reports.index') }}">@include('admin.partials.icon', ['name'=>'chart-no-axes-combined', 'size'=>17]) Báo cáo doanh thu</a>
            @endif
            <a class="ad-button primary" href="{{ route('admin.payments.export', $params) }}">@include('admin.partials.icon', ['name'=>'download', 'size'=>17]) Xuất CSV</a>
        </div>
    </header>

    <section class="ad-stats" aria-label="Thống kê giao dịch theo bộ lọc">
        <article class="ad-stat"><span>@include('admin.partials.icon', ['name'=>'wallet', 'size'=>19]) Tổng giao dịch</span><strong>{{ number_format($summary->total) }}</strong><small>Theo bộ lọc đang áp dụng</small></article>
        <article class="ad-stat success"><span>@include('admin.partials.icon', ['name'=>'circle-check', 'size'=>19]) Đã thanh toán</span><strong class="pay-stat-money">{{ $money($summary->paid_amount) }}</strong><small>{{ number_format($summary->paid_count ?? 0) }} giao dịch thành công</small></article>
        <article class="ad-stat pay-stat-refund"><span>@include('admin.partials.icon', ['name'=>'refresh-cw', 'size'=>19]) Đã hoàn tiền</span><strong class="pay-stat-money">{{ $money($summary->refunded_amount) }}</strong><small>{{ number_format($summary->refunded_count ?? 0) }} giao dịch đã hoàn</small></article>
        <article class="ad-stat danger"><span>@include('admin.partials.icon', ['name'=>'circle-x', 'size'=>19]) Thất bại</span><strong>{{ number_format($summary->failed_count ?? 0) }}</strong><small>Không tính vào tiền đã thanh toán</small></article>
    </section>

    <section class="ad-panel">
        @if($errors->any())
        <div class="pay-errors" role="alert" data-inline-notification>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif
        <form method="GET" action="{{ route('admin.payments.index') }}" class="pay-filters">
            @if($filters['ticket_id'])
            <div class="pay-context">@include('admin.partials.icon', ['name'=>'ticket', 'size'=>17]) Đang xem các giao dịch của vé #{{ $filters['ticket_id'] }}<input type="hidden" name="ticket_id" value="{{ $filters['ticket_id'] }}"><a href="{{ route('admin.payments.index', collect($params)->except('ticket_id')->all()) }}">Bỏ lọc vé</a></div>
            @endif
            <div class="pay-search-grid">
                <label class="pay-field pay-search"><span>Tìm giao dịch</span><div>@include('admin.partials.icon', ['name'=>'search', 'size'=>18])<input name="q" type="search" value="{{ $filters['q'] }}" maxlength="150" placeholder="Mã giao dịch, vé, hóa đơn, tên, email, số điện thoại, phim…"></div></label>
                <label class="pay-field"><span>Trạng thái</span><select name="status"><option value="">Tất cả trạng thái</option>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="pay-field"><span>Phương thức</span><select name="method"><option value="">Tất cả phương thức</option>@foreach($methods as $value=>$label)<option value="{{ $value }}" @selected($filters['method'] === $value)>{{ $label }}</option>@endforeach</select></label>
            </div>
            <div class="pay-filter-grid">
                <label class="pay-field"><span>Rạp</span><select name="cinema_id"><option value="">Tất cả rạp</option>@foreach($cinemas as $cinema)<option value="{{ $cinema->id }}" @selected((string)$filters['cinema_id'] === (string)$cinema->id)>{{ $cinema->name }}</option>@endforeach</select></label>
                <label class="pay-field"><span>Phim</span><select name="movie_id"><option value="">Tất cả phim</option>@foreach($movies as $movie)<option value="{{ $movie->id }}" @selected((string)$filters['movie_id'] === (string)$movie->id)>{{ $movie->title }}{{ $movie->status === 'inactive' ? ' · Lưu trữ' : '' }}</option>@endforeach</select></label>
                <label class="pay-field"><span>Từ ngày ghi nhận</span><input type="date" name="from" value="{{ $filters['from'] }}"></label>
                <label class="pay-field"><span>Đến ngày ghi nhận</span><input type="date" name="to" value="{{ $filters['to'] }}" @if($filters['from']) min="{{ $filters['from'] }}" @endif></label>
                <label class="pay-field"><span>Sắp xếp</span><select name="sort">@foreach(['latest'=>'Mới nhất', 'oldest'=>'Cũ nhất', 'amount_desc'=>'Số tiền giảm dần', 'amount_asc'=>'Số tiền tăng dần'] as $value=>$label)<option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>@endforeach</select></label>
            </div>
            <div class="pay-filter-footer"><p>Ngày ghi nhận là ngày thanh toán; nếu chưa có, dùng ngày tạo giao dịch.</p><div class="ad-actions"><a class="ad-reset" href="{{ route('admin.payments.index') }}">Xóa bộ lọc</a><button class="ad-button primary" type="submit">@include('admin.partials.icon', ['name'=>'list-filter', 'size'=>17]) Áp dụng</button></div></div>
        </form>

        <div class="ad-results"><h2>Danh sách thanh toán <span class="pay-count">{{ number_format($payments->total()) }}</span></h2><span>CSV xuất toàn bộ kết quả theo bộ lọc</span></div>
        @if($payments->isEmpty())
        <div class="ad-empty">@include('admin.partials.icon', ['name'=>'wallet', 'size'=>34])<h3>Không có giao dịch phù hợp</h3><p>Thử thay đổi từ khóa, trạng thái hoặc khoảng ngày.</p><a class="ad-button" href="{{ route('admin.payments.index') }}">Xem tất cả giao dịch</a></div>
        @else
        <div class="pay-table-wrap" tabindex="0" role="region" aria-label="Danh sách giao dịch thanh toán">
            <table class="pay-table">
                <thead><tr><th>Giao dịch</th><th>Khách hàng</th><th>Vé / phim</th><th>Rạp / phòng</th><th>Thanh toán</th><th class="pay-number">Số tiền</th><th class="pay-action-cell">Chi tiết</th></tr></thead>
                <tbody>
                @foreach($payments as $payment)
                @php($ticket = $payment->ticket)
                <tr>
                    <td data-label="Giao dịch"><a class="pay-code" href="{{ route('admin.payments.show', array_merge($params, ['payment'=>$payment->id])) }}">#{{ $payment->id }}</a><small>{{ $date($payment->paid_at ?? $payment->created_at) }}</small>@if($ticket?->invoice)<small class="pay-invoice">{{ $ticket->invoice->invoice_code }}</small>@endif</td>
                    <td data-label="Khách hàng"><strong>{{ $ticket?->user?->name ?? 'Không còn thông tin' }}</strong><small>{{ $ticket?->user?->email ?? '—' }}</small></td>
                    <td data-label="Vé / phim"><strong>{{ $ticket?->showtime?->movie?->title ?? 'Không còn thông tin' }}</strong><small>Vé #{{ $payment->ticket_id }}@if($ticket?->seat) · Ghế {{ $ticket->seat->seat_row }}{{ $ticket->seat->seat_number }}@endif</small></td>
                    <td data-label="Rạp / phòng"><strong>{{ $ticket?->showtime?->room?->cinema?->name ?? 'Không còn thông tin' }}</strong><small>{{ $ticket?->showtime?->room?->name ?? '—' }}</small></td>
                    <td data-label="Thanh toán"><span class="ad-badge {{ $tones[$payment->status] ?? '' }}">{{ $statuses[$payment->status] ?? $payment->status }}</span><small>{{ $methods[$payment->method] ?? $payment->method }}</small></td>
                    <td class="pay-number" data-label="Số tiền"><strong class="pay-amount">{{ $money($payment->amount) }}</strong></td>
                    <td class="pay-action-cell"><a class="ad-button" href="{{ route('admin.payments.show', array_merge($params, ['payment'=>$payment->id])) }}" aria-label="Xem giao dịch #{{ $payment->id }}">@include('admin.partials.icon', ['name'=>'eye', 'size'=>16]) Xem</a></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="ad-pagination"><span>Hiển thị {{ $payments->firstItem() }}–{{ $payments->lastItem() }} / {{ number_format($payments->total()) }} giao dịch</span>{{ $payments->onEachSide(1)->links() }}</div>
        @endif
    </section>
</div>
@endsection
