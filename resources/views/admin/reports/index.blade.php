@extends('admin.layouts.app')
@section('title', 'Doanh thu & báo cáo')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin/reports.css') }}?v={{ filemtime(public_path('assets/css/admin/reports.css')) }}">
@endpush
@php
    $money = fn ($value) => number_format((float)$value, abs((float)$value - round((float)$value)) > 0.001 ? 2 : 0, ',', '.').' ₫';
    $number = fn ($value) => number_format((float)$value, 0, ',', '.');
    $summary = $report['summary'];
    $paymentMethodLabels = \App\Models\PaymentMethod::labels();
    $query = array_filter($filters, fn ($value) => $value !== null);
    $movieLabels = ['now_showing'=>'Đang chiếu','upcoming'=>'Sắp chiếu','inactive'=>'Đã lưu trữ'];
    $series = $report['daily']->count() > 45
        ? $report['daily']->groupBy(fn ($row) => substr($row->date, 0, 7))->map(fn ($rows, $date) => (object)['date'=>$date,'revenue'=>$rows->sum('revenue')])->values()
        : $report['daily'];
    $maxRevenue = max(1, (float)$series->max('revenue'));
@endphp
@section('content')
<div class="report-page">
    <div class="report-heading">
        <div><span class="report-eyebrow">HIỆU QUẢ KINH DOANH</span><h2>Doanh thu & báo cáo</h2><p>Xem kết quả của từng phim, từng rạp và đối chiếu các giao dịch.</p></div>
        <div class="report-actions"><button type="button" class="report-button report-button--ghost" onclick="window.print()">@include('admin.partials.icon', ['name'=>'printer', 'size'=>18]) In báo cáo</button><a class="report-button" href="{{ route('admin.reports.export', $query) }}">@include('admin.partials.icon', ['name'=>'download', 'size'=>18]) Xuất Excel đầy đủ</a></div>
    </div>
    <form action="{{ route('admin.reports.index') }}" method="GET" class="report-filters">
        <input type="hidden" name="tab" value="{{ $filters['tab'] }}">
        <label>Từ ngày<input type="date" name="from" value="{{ $filters['from'] }}" required></label>
        <label>Đến ngày<input type="date" name="to" value="{{ $filters['to'] }}" required></label>
        <label>Phim<select name="movie_id"><option value="">Tất cả phim</option>@foreach($movieOptions as $movie)<option value="{{ $movie->id }}" @selected((int)$filters['movie_id'] === $movie->id)>{{ $movie->title }}{{ $movie->status === 'inactive' ? ' · Lưu trữ' : '' }}</option>@endforeach</select></label>
        <label>Rạp<select name="cinema_id"><option value="">Tất cả rạp</option>@foreach($cinemaOptions as $cinema)<option value="{{ $cinema->id }}" @selected((int)$filters['cinema_id'] === $cinema->id)>{{ $cinema->name }}</option>@endforeach</select></label>
        <div class="report-filter-actions"><button class="report-button" type="submit">Xem báo cáo</button><a href="{{ route('admin.reports.index') }}">Đặt lại</a></div>
        <div class="report-presets">
            <span>Xem nhanh</span>
            @foreach(['Hôm nay'=>now()->toDateString(),'7 ngày'=>now()->subDays(6)->toDateString(),'Tháng này'=>now()->startOfMonth()->toDateString(),'Năm nay'=>now()->startOfYear()->toDateString()] as $label=>$from)
                <a href="{{ route('admin.reports.index', array_merge($query,['from'=>$from,'to'=>now()->toDateString()])) }}">{{ $label }}</a>
            @endforeach
            <a href="{{ route('admin.reports.index', array_merge(collect($query)->except(['from','to'])->all(),['period'=>'all'])) }}">Toàn thời gian</a>
        </div>
    </form>
    <div class="report-period"><span>{{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}</span><span>{{ $filters['movie_id'] ? $movieOptions->firstWhere('id',(int)$filters['movie_id'])?->title : 'Tất cả phim' }} · {{ $filters['cinema_id'] ? $cinemaOptions->firstWhere('id',(int)$filters['cinema_id'])?->name : 'Toàn hệ thống' }}</span></div>
    <section class="report-metrics" aria-label="Chỉ số doanh thu">
        @foreach([
            ['revenue','Doanh thu còn lại','Sau khi trừ tiền đã hoàn','orange',true],
            ['received','Tổng tiền đã thu','Gồm khoản đã thanh toán và đã hoàn','blue',true],
            ['refunded','Tiền đã hoàn',$number($summary['refunded_transactions']).' giao dịch hoàn tiền','red',true],
            ['sold_tickets','Vé đã bán còn hiệu lực',$number($summary['used_tickets']).' vé đã check-in','green',false],
        ] as [$key,$label,$hint,$tone,$currency])
            <article class="report-metric report-metric--{{ $tone }}"><span>{{ $label }}</span><strong>{{ $currency ? $money($summary[$key]) : $number($summary[$key]) }}</strong><small>{{ $hint }}</small></article>
        @endforeach
    </section>
    <nav class="report-tabs" aria-label="Loại báo cáo">
        @foreach(['overview'=>'Tổng quan','movies'=>'Theo phim','cinemas'=>'Theo rạp','transactions'=>'Chi tiết giao dịch'] as $tab=>$label)
        <a href="{{ route('admin.reports.index',array_merge($query,['tab'=>$tab])) }}" class="{{ $filters['tab'] === $tab ? 'is-active' : '' }}" @if($filters['tab'] === $tab) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @if($filters['tab'] === 'overview')
    <div class="report-overview-grid">
        <section class="report-card report-trend"><div class="report-card-heading"><div><h3>Xu hướng doanh thu</h3><p>{{ $report['daily']->count() > 45 ? 'Tổng hợp theo tháng' : 'Các ngày có giao dịch' }}</p></div><span>{{ $money($summary['revenue']) }}</span></div>
            @if($series->isEmpty())<div class="report-empty">Chưa có giao dịch trong khoảng thời gian này.</div>@else
            <div class="report-chart" role="list" aria-label="Doanh thu theo thời gian">
            @foreach($series as $row)
                <div class="report-chart-column" role="listitem" title="{{ $row->date }}: {{ $money($row->revenue) }}"><div class="report-chart-bar"><span style="height:{{ max(0, min(100, (float)$row->revenue / $maxRevenue * 100)) }}%"></span></div><small>{{ strlen($row->date) === 7 ? \Carbon\Carbon::parse($row->date.'-01')->format('m/Y') : \Carbon\Carbon::parse($row->date)->format('d/m') }}</small></div>
            @endforeach
            </div>@endif
        </section>
        <section class="report-card"><div class="report-card-heading"><div><h3>Phương thức thanh toán</h3><p>Các giao dịch trong bộ lọc</p></div></div>
            <div class="report-methods">@forelse($report['methods'] as $method)<div><span><b>{{ $paymentMethodLabels[$method->method] ?? $method->method }}</b><small>{{ $number($method->transactions) }} giao dịch · {{ $number($method->failed_transactions) }} thất bại</small></span><strong>{{ $money($method->revenue) }}</strong></div>@empty<div class="report-empty">Chưa có dữ liệu thanh toán.</div>@endforelse</div>
        </section>
    </div>
    <section class="report-card"><div class="report-card-heading"><div><h3>Vé & combo</h3><p>Vé theo ngày đặt; combo hoàn tất gắn với vé có thanh toán trong báo cáo.</p></div></div>
        <div class="report-operation-grid">
            <div><span>Vé tạo trong kỳ</span><strong>{{ $number($summary['booked_tickets']) }}</strong></div>
            <div><span>Vé đã hủy</span><strong>{{ $number($summary['cancelled_tickets']) }}</strong></div>
            <div><span>Vé chờ thanh toán</span><strong>{{ $number($summary['pending_tickets']) }}</strong></div>
            <div><span>Giao dịch thất bại</span><strong>{{ $number($summary['failed_transactions']) }}</strong></div>
            <div><span>Đơn combo hoàn tất</span><strong>{{ $number($summary['combo_orders']) }}</strong></div>
            <div><span>Giá trị combo trước giảm</span><strong>{{ $money($summary['combo_value']) }}</strong></div>
        </div>
    </section>
    <div class="report-overview-grid">
        @foreach(['movies'=>['Phim có doanh thu cao nhất','title'],'cinemas'=>['Rạp có doanh thu cao nhất','name']] as $key=>[$title,$name])
        <section class="report-card"><div class="report-card-heading"><h3>{{ $title }}</h3><a href="{{ route('admin.reports.index',array_merge($query,['tab'=>$key])) }}">Xem tất cả @include('admin.partials.icon',['name'=>'arrow-right','size'=>16])</a></div><ol class="report-ranking">@forelse($report[$key]->filter(fn($row)=>(float)$row['received']>0)->take(5) as $row)<li><span><b>{{ $row[$name] }}</b><small>{{ $number($row['sold_tickets']) }} vé đã bán</small></span><strong>{{ $money($row['revenue']) }}</strong></li>@empty<li class="report-empty">Chưa có doanh thu trong kỳ.</li>@endforelse</ol></section>
        @endforeach
    </div>
    @elseif(in_array($filters['tab'], ['movies','cinemas']))
    @php $isMovie = $filters['tab'] === 'movies'; $rows = $isMovie ? $movieRows : $cinemaRows; @endphp
    <section class="report-card"><div class="report-card-heading"><div><h3>{{ $isMovie ? 'Doanh thu của từng phim' : 'Doanh thu của từng rạp' }}</h3><p>{{ $number($rows->total()) }} {{ $isMovie ? 'phim, gồm cả phim đã lưu trữ' : 'rạp trong hệ thống' }}</p></div></div>
        <div class="report-table-wrap" tabindex="0" role="region" aria-label="Bảng doanh thu"><table class="report-table"><thead><tr><th>{{ $isMovie ? 'Phim' : 'Rạp' }}</th><th>Vé đã bán</th><th>Check-in</th><th>Tổng thu</th><th>Hoàn tiền</th><th>Doanh thu còn lại</th><th>Chi tiết</th></tr></thead><tbody>
        @forelse($rows as $row)<tr><td><strong>{{ $row[$isMovie ? 'title' : 'name'] }}</strong><small>{{ $isMovie ? ($movieLabels[$row['status']] ?? $row['status']) : $row['city'] }}</small></td><td>{{ $number($row['sold_tickets']) }}</td><td>{{ $number($row['used_tickets']) }}</td><td>{{ $money($row['received']) }}</td><td>{{ $money($row['refunded']) }}</td><td class="report-revenue">{{ $money($row['revenue']) }}</td><td>@if($row['id'])<a class="report-detail-link" href="{{ route('admin.reports.index', array_merge($query,[$isMovie ? 'movie_id' : 'cinema_id'=>$row['id'],'tab'=>'overview'])) }}">Xem @include('admin.partials.icon',['name'=>'arrow-right','size'=>16])</a>@endif</td></tr>@empty<tr><td colspan="7" class="report-empty">Không có dữ liệu phù hợp.</td></tr>@endforelse
        </tbody><tfoot><tr><th>Tổng trong bộ lọc</th><td>{{ $number($summary['sold_tickets']) }}</td><td>{{ $number($summary['used_tickets']) }}</td><td>{{ $money($summary['received']) }}</td><td>{{ $money($summary['refunded']) }}</td><td>{{ $money($summary['revenue']) }}</td><td></td></tr></tfoot></table></div>
        <div class="report-pagination">{{ $rows->links() }}</div>
    </section>
    @if($filters['movie_id'] || $filters['cinema_id'])
    <section class="report-card"><div class="report-card-heading"><h3>Phim tại từng rạp</h3></div><div class="report-table-wrap"><table class="report-table"><thead><tr><th>Phim</th><th>Rạp</th><th>Vé đã bán</th><th>Tổng thu</th><th>Hoàn tiền</th><th>Doanh thu còn lại</th></tr></thead><tbody>@forelse($report['matrix'] as $row)<tr><td>{{ $row->title ?? 'Không còn thông tin' }}</td><td>{{ $row->name ?? 'Không còn thông tin' }}</td><td>{{ $number($row->sold_tickets) }}</td><td>{{ $money($row->received) }}</td><td>{{ $money($row->refunded) }}</td><td class="report-revenue">{{ $money($row->revenue) }}</td></tr>@empty<tr><td colspan="6" class="report-empty">Chưa có giao dịch phù hợp.</td></tr>@endforelse</tbody></table></div></section>
    @endif
    @else
    <section class="report-card"><div class="report-card-heading"><div><h3>Chi tiết giao dịch</h3><p>{{ $number($transactions->total()) }} giao dịch · Excel xuất toàn bộ kết quả, gồm tất cả các trang.</p></div></div>
        <div class="report-table-wrap" tabindex="0" role="region" aria-label="Chi tiết giao dịch"><table class="report-table report-transactions"><thead><tr><th>Giao dịch / vé</th><th>Ngày ghi nhận</th><th>Phim / rạp</th><th>Khách hàng</th><th>Phương thức</th><th>Trạng thái</th><th>Số tiền</th></tr></thead><tbody>
        @forelse($transactions as $row)<tr><td><strong>#{{ $row->id }}</strong><small>Vé #{{ $row->ticket_id }} · {{ $row->seat_row }}{{ $row->seat_number }}</small>@if(auth()->user()->hasAdminPermission('tickets.manage'))<a href="{{ route('admin.tickets.show',$row->ticket_id) }}">Xem vé @include('admin.partials.icon',['name'=>'arrow-right','size'=>16])</a>@endif</td><td>{{ \Carbon\Carbon::parse($row->recorded_at)->format('d/m/Y') }}<small>{{ \Carbon\Carbon::parse($row->recorded_at)->format('H:i') }}</small></td><td><strong>{{ $row->movie_title ?? 'Không còn thông tin' }}</strong><small>{{ $row->cinema_name ?? 'Không còn thông tin' }} · {{ $row->room_name }}</small></td><td>{{ $row->customer_name ?? 'Khách không còn thông tin' }}</td><td>{{ $paymentMethodLabels[$row->method] ?? $row->method }}</td><td><span class="report-status report-status--{{ $row->status }}">{{ \App\Services\RevenueReportService::STATUSES[$row->status] ?? $row->status }}</span></td><td class="report-revenue">{{ $money($row->amount) }}</td></tr>@empty<tr><td colspan="7" class="report-empty">Chưa có giao dịch phù hợp với bộ lọc.</td></tr>@endforelse
        </tbody></table></div><div class="report-pagination">{{ $transactions->links() }}</div>
    </section>
    @endif
    <aside class="report-note"><strong>Cách đọc báo cáo</strong><p>Thống kê theo ngày thanh toán, hoặc ngày tạo giao dịch nếu chưa có ngày thanh toán, với trạng thái hiện tại. Tổng thu gồm các khoản đã thanh toán và đã hoàn; doanh thu còn lại = tổng thu − tiền hoàn. Giá trị combo trước giảm đã nằm trong khoản thanh toán, không cộng thêm vào doanh thu.</p><p>Báo cáo phản ánh dữ liệu thanh toán của hệ thống; giao dịch mô phỏng của đồ án không phải tiền thu qua ngân hàng.</p></aside>
</div>
@endsection
