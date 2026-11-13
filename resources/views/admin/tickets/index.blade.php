@extends('admin.layouts.app')
@section('title','Quản lý vé')
@include('admin.partials.directory-assets')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/ticket-list.css') }}?v={{ filemtime(public_path('assets/css/admin/ticket-list.css')) }}">
@endpush
@section('content')
@php
    $ticketLabels=['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','used'=>'Đã check-in','cancelled'=>'Đã hủy'];
    $paymentLabels=['unpaid'=>'Chưa thanh toán','pending'=>'Chờ thanh toán','paid'=>'Đã thanh toán','refunded'=>'Đã hoàn tiền','failed'=>'Thanh toán thất bại'];
    $ticketTones=['pending'=>'warning','confirmed'=>'','used'=>'success','cancelled'=>'danger'];
    $paymentTones=['unpaid'=>'warning','pending'=>'warning','paid'=>'success','refunded'=>'purple','failed'=>'danger'];
    $ticketParams=array_filter($filters,fn ($value)=>$value !== '' && $value !== null);
    $ticketsUrl=fn ($changes=[])=>route('admin.tickets.index',array_filter(array_replace($ticketParams,$changes),fn ($value)=>$value !== '' && $value !== null));
@endphp
<div class="ad-page">
<header class="ad-heading"><div><span class="ad-kicker">TD CINEMA · VÉ & CHECK-IN</span><h1>Quản lý vé</h1><p>Tra cứu vé, kiểm tra thanh toán và check-in cho khách.</p></div><a class="ad-button primary" href="{{ route('admin.tickets.create') }}">@include('admin.partials.icon',['name'=>'plus','size'=>18]) Bán vé tại quầy</a></header>
@if($errors->any())<div class="ad-panel" data-inline-notification role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<nav class="ad-stats" aria-label="Lọc nhanh trạng thái vé">@foreach([['total','ticket-check','Tất cả vé','','',''],['ready','square-check','Sẵn sàng check-in','confirmed','paid','success'],['used','circle-check','Đã check-in','used','','success'],['cancelled','circle-x','Đã hủy / quá hạn','cancelled','','danger']] as [$key,$icon,$label,$status,$payStatus,$tone])<a class="ad-stat {{ $tone }} {{ $filters['status'] === $status && $filters['payment_status'] === $payStatus ? 'is-selected' : '' }}" href="{{ $ticketsUrl(['status'=>$status,'payment_status'=>$payStatus]) }}"><span>@include('admin.partials.icon',['name'=>$icon,'size'=>20]) {{ $label }}</span><strong>{{ number_format($counts[$key]) }}</strong><small>Theo phim, rạp, khách và ngày đang lọc</small></a>@endforeach</nav>
<section class="ad-panel">
<form class="ad-filter" method="GET" action="{{ route('admin.tickets.index') }}" data-ticket-filter>
    <label class="ad-search"><span>Tìm vé, lượt đặt hoặc khách hàng</span><div>@include('admin.partials.icon',['name'=>'search','size'=>18])<input type="search" name="q" maxlength="150" value="{{ $filters['q'] }}" placeholder="Mã lượt đặt, mã vé #97, tên hoặc SĐT..."></div></label><label><span>Phim</span><select name="movie_id"><option value="">Tất cả phim</option>@foreach($movies as $movie)<option value="{{ $movie->id }}" @selected((string)$filters['movie_id'] === (string)$movie->id)>{{ $movie->title }}</option>@endforeach</select></label><label><span>Rạp chiếu</span><select name="cinema_id"><option value="">Tất cả rạp</option>@foreach($cinemas as $cinema)<option value="{{ $cinema->id }}" @selected((string)$filters['cinema_id'] === (string)$cinema->id)>{{ $cinema->name }}</option>@endforeach</select></label><button class="ad-button primary" type="submit">@include('admin.partials.icon',['name'=>'list-filter','size'=>16]) Lọc vé</button>
    <details class="ad-advanced" @if($filters['showtime_id'] || $filters['seat_id'] || $filters['user'] || $filters['status'] || $filters['payment_status'] || $filters['from'] || $filters['to']) open @endif><summary>Lọc thêm theo suất, ghế, thanh toán & ngày @include('admin.partials.icon',['name'=>'chevron-down','size'=>14])</summary><div>
        <label class="wide"><span>Suất chiếu</span><select name="showtime_id"><option value="">Tất cả suất chiếu</option>@foreach($showtimes as $show)<option value="{{ $show->id }}" @selected((string)$filters['showtime_id'] === (string)$show->id)>{{ $show->movie?->title }} · {{ $show->start_time?->format('d/m/Y H:i') }} · {{ $show->room?->cinema?->name }} / {{ $show->room?->name }}</option>@endforeach</select></label>
        <label><span>Ghế</span><select name="seat_id" @disabled($seats->isEmpty())><option value="">{{ $seats->isEmpty() ? 'Lọc theo suất để chọn ghế' : 'Tất cả ghế' }}</option>@foreach($seats as $seat)<option value="{{ $seat->id }}" @selected((string)$filters['seat_id'] === (string)$seat->id)>{{ $seat->seat_row }}{{ $seat->seat_number }}</option>@endforeach</select></label>
        <label><span>Trạng thái vé</span><select name="status"><option value="">Tất cả trạng thái</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $ticketLabels[$status] }}</option>@endforeach</select></label><label><span>Thanh toán</span><select name="payment_status"><option value="">Tất cả thanh toán</option>@foreach($payStatuses as $state)<option value="{{ $state }}" @selected($filters['payment_status'] === $state)>{{ $paymentLabels[$state] }}</option>@endforeach</select></label>
        <label><span>Khách hàng cụ thể</span><input type="search" name="user" maxlength="150" value="{{ $filters['user'] }}" placeholder="Tên, email hoặc SĐT..."></label><label><span>Tạo vé từ ngày</span><input type="date" name="from" value="{{ $filters['from'] }}"></label><label><span>Tạo vé đến ngày</span><input type="date" name="to" value="{{ $filters['to'] }}"></label>
    </div></details><div class="ad-filter-footer"><a class="ad-reset" href="{{ route('admin.tickets.index') }}">Xóa bộ lọc</a></div>
</form>
<div class="ad-results"><strong>{{ number_format($tickets->total()) }} vé phù hợp</strong><span>Mới nhất trước</span></div>
<div class="ad-list">@forelse($tickets as $ticket)
@php
    $show = $ticket->showtime;
    $paymentStatus = $ticket->payment?->status ?? 'unpaid';
    $comboTotal = $ticket->foodOrders->sum('total_price');
    $amount = $ticket->payment?->amount ?? ($ticket->price + $comboTotal);
    $isExpired = $ticket->cancellation_reason === \App\Models\Ticket::EXPIRED_REASON;
    $group = $ticket->qr_code
        ? ($bookingTickets->get($ticket->showtime_id.'|'.$ticket->qr_code) ?? collect([$ticket]))
        : collect([$ticket]);
    $bookingComboTotal = (float) $group->flatMap(fn ($item) => $item->foodOrders)->unique('id')->sum('total_price');
    $bookingTotal = $group->count();
    $bookingWaiting = $group->filter(fn ($item) => $item->status === 'confirmed' && $item->payment?->status === 'paid');
@endphp
<article class="ad-record ad-ticket-record" data-ticket-id="{{ $ticket->id }}">
    <div class="ad-identity">
        <img class="ad-ticket-poster" src="{{ $show?->movie?->posterUrl() ?? asset('assets/images/ui/movie-placeholder.svg') }}" alt="Poster {{ $show?->movie?->title ?? 'phim' }}" loading="lazy" width="70" height="100">
        <div>
            <span class="ad-ticket-code">@include('admin.partials.icon',['name'=>'ticket-check','size'=>16]) #{{ $ticket->id }}</span>
            <h2>{{ $show?->movie?->title ?? 'Chưa có thông tin phim' }}</h2>
            <p>{{ $show?->start_time?->format('H:i · d/m/Y') ?? 'Chưa có lịch' }} · {{ $show?->room?->name }}</p>
            <p>{{ $show?->room?->cinema?->name }}</p>
            <span class="ad-ticket-seat">Ghế {{ $ticket->seat?->seat_row }}{{ $ticket->seat?->seat_number }}</span>
            <small class="ad-booking-ref">Lượt đặt {{ $ticket->qr_code ?? ('TICKET-'.$ticket->id) }} · {{ $bookingTotal }} vé</small>
            @if($bookingComboTotal > 0)<small class="ad-booking-combo">Có combo đi kèm · {{ number_format($bookingComboTotal, 0, ',', '.') }} đ</small>@endif
            @if($isExpired)<small class="ad-ticket-expiry">Ghi chú hủy vé: Vé quá hạn</small>@endif
        </div>
    </div>
    <div class="ad-meta">
        <div><span>Khách hàng</span><strong>{{ $ticket->user?->name ?? 'Khách vãng lai' }}</strong><small>{{ $ticket->user?->email }}</small></div>
        <div><span>Tạo vé lúc</span><time>{{ $ticket->created_at?->format('H:i · d/m/Y') ?? '—' }}</time></div>
        <div class="ad-ticket-status">
            <span class="ad-badge {{ $paymentTones[$paymentStatus] ?? '' }}">{{ $paymentLabels[$paymentStatus] ?? $paymentStatus }}</span>
        </div>
    </div>
    <div class="ad-meta">
        <div><span>Giá trị thanh toán</span><strong class="ad-money">{{ number_format($amount, 0, ',', '.') }} đ</strong></div>
        <div><span>Giá vé này / combo của lượt đặt</span><small>{{ number_format($ticket->price, 0, ',', '.') }} đ / {{ number_format($bookingComboTotal, 0, ',', '.') }} đ</small></div>
    </div>
    <div class="ad-record-actions">
        <a class="ad-button" href="{{ route('admin.tickets.show', $ticket) }}">@include('admin.partials.icon',['name'=>'eye','size'=>15]) Chi tiết</a>
        <span class="ad-badge {{ $ticketTones[$ticket->status] ?? '' }} ad-ticket-action-status">{{ $isExpired ? 'Quá hạn' : ($ticketLabels[$ticket->status] ?? $ticket->status) }}</span>
        @if($bookingWaiting->isNotEmpty())
            <form method="POST" action="{{ route('admin.tickets.booking-checkin', $ticket) }}" data-confirm="Check-in cả lượt đặt, gồm {{ $bookingWaiting->count() }} ghế còn lại đã thanh toán?">
                @csrf
                <button class="ad-button success" type="submit">@include('admin.partials.icon',['name'=>'ticket-check','size'=>15]) Check-in cả lượt ({{ $bookingWaiting->count() }} ghế)</button>
            </form>
        @endif
        @if(!in_array($ticket->status, ['cancelled', 'used'], true))
            <details class="ad-menu">
                <summary>Thao tác @include('admin.partials.icon',['name'=>'chevron-down','size'=>13])</summary>
                <div><form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" data-confirm="Hủy vé #{{ $ticket->id }}?">
                    @csrf<input type="hidden" name="status" value="cancelled">
                    <button class="danger" type="submit">@include('admin.partials.icon',['name'=>'circle-x','size'=>15]) Hủy vé</button>
                </form></div>
            </details>
        @endif
    </div>
</article>
@empty<div class="ad-empty">@include('admin.partials.icon',['name'=>'ticket-check','size'=>34])<h3>Không tìm thấy vé phù hợp</h3><p>Thử đổi mã vé, khách hàng hoặc bộ lọc.</p><a class="ad-button" href="{{ route('admin.tickets.index') }}">Xem tất cả vé</a></div>@endforelse</div>
@if($tickets->hasPages())<div class="ad-pagination"><span>Hiển thị {{ $tickets->firstItem() }}–{{ $tickets->lastItem() }} / {{ $tickets->total() }} vé</span>{{ $tickets->onEachSide(1)->links() }}</div>@endif
</section></div>
@endsection
