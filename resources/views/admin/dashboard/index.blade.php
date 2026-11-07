@extends('admin.layouts.app')
@section('title', 'Bảng điều khiển')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/admin/dashboard.css') }}?v={{ filemtime(public_path('assets/css/admin/dashboard.css')) }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/admin/dashboard.js') }}?v={{ filemtime(public_path('assets/js/admin/dashboard.js')) }}" defer></script>@endpush
@section('content')
@php
    $reportParams = array_filter(['from'=>$filters['from'],'to'=>$filters['to'],'cinema_id'=>$cinemaId]);
    $selectedCinema = $cinemas->firstWhere('id', (int)$cinemaId);
    $paymentMethodLabels = \App\Models\PaymentMethod::labels();
    $money = fn ($value) => number_format($value,0,',','.').' ₫';
    $axis = fn ($value) => $value >= 1000000000 ? number_format($value / 1000000000,1,',','.').' tỷ' : ($value >= 1000000 ? number_format($value / 1000000,1,',','.').' tr' : ($value >= 1000 ? number_format($value / 1000,0,',','.').'k' : number_format($value,0,',','.')));
@endphp
<div class="db-page" data-dashboard>
    <header class="db-heading">
        <div><span class="db-eyebrow">TỔNG QUAN HỆ THỐNG</span><h2>Xin chào, {{ auth()->user()->name }}</h2><p>Những thông tin cần thiết để bắt đầu ngày làm việc.</p></div>
        <div class="db-actions">
            @if($canReport)
                <a class="db-button" href="{{ route('admin.reports.index',$reportParams) }}">@include('admin.partials.icon',['name'=>'chart-no-axes-combined','size'=>17]) Báo cáo chi tiết</a>
                <a class="db-button primary" href="{{ route('admin.reports.export',$reportParams) }}">@include('admin.partials.icon',['name'=>'download','size'=>17]) Tải báo cáo Excel</a>
            @elseif(auth()->user()->hasAdminPermission('tickets.manage'))
                <a class="db-button primary" href="{{ route('admin.tickets.create') }}">@include('admin.partials.icon',['name'=>'plus','size'=>17]) Tạo vé tại quầy</a>
            @endif
        </div>
    </header>
    @if($canReport)
    <section class="db-overview" aria-label="Số liệu trong kỳ">
        <form class="db-filter" action="{{ route('admin.dashboard') }}" method="GET">
            <div class="db-period"><span>Kỳ thống kê</span><div class="db-segments">@foreach(['day'=>'Hôm nay','month'=>'Tháng này','year'=>'Năm nay'] as $value=>$label)<button type="submit" name="range" value="{{ $value }}" class="{{ $range === $value ? 'is-active' : '' }}" @if($range === $value) aria-current="true" @endif>{{ $label }}</button>@endforeach</div></div>
            <div class="db-cinema-filter"><label for="dashboard-cinema">Rạp chiếu</label><div><select id="dashboard-cinema" name="cinema_id"><option value="">Tất cả rạp</option>@foreach($cinemas as $cinema)<option value="{{ $cinema->id }}" @selected((string)$cinemaId === (string)$cinema->id)>{{ $cinema->name }}</option>@endforeach</select><button class="db-button" name="range" value="{{ $range }}" type="submit">@include('admin.partials.icon',['name'=>'list-filter','size'=>16]) Xem</button>@if($cinemaId)<a class="db-reset" href="{{ route('admin.dashboard',['range'=>$range]) }}" aria-label="Bỏ lọc rạp" title="Bỏ lọc rạp">@include('admin.partials.icon',['name'=>'x','size'=>17])</a>@endif</div></div>
        </form>
        <div class="db-context"><span>@include('admin.partials.icon',['name'=>'calendar-days','size'=>15]) {{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}{{ $filters['from'] !== $filters['to'] ? ' – '.\Carbon\Carbon::parse($filters['to'])->format('d/m/Y') : '' }} <i>·</i> {{ $selectedCinema?->name ?? 'Toàn hệ thống' }}</span><small title="Kỳ đối chiếu: {{ $comparisonLabel }}">So với cùng kỳ trước</small></div>
        @php $metrics = [
            ['key'=>'revenue','label'=>'Tổng doanh thu','value'=>$money($stats['revenue']),'hint'=>'Giao dịch đã thanh toán','icon'=>'wallet','tone'=>'orange'],
            ['key'=>'bookings','label'=>'Vé đã thanh toán','value'=>number_format($stats['bookings'],0,',','.'),'hint'=>'Vé còn hiệu lực, gồm đã check-in','icon'=>'ticket-check','tone'=>'blue'],
            ['key'=>'active_users','label'=>'Khách đặt vé','value'=>number_format($stats['active_users'],0,',','.'),'hint'=>'Khách có vé đã thanh toán','icon'=>'users','tone'=>'purple'],
            ['key'=>'success_rate','label'=>'Thanh toán thành công','value'=>number_format($stats['success_rate']*100,1,',','.').'%','hint'=>number_format($stats['paid_transactions'],0,',','.').' / '.number_format($stats['transactions'],0,',','.').' giao dịch','icon'=>'circle-check','tone'=>'green'],
        ]; @endphp
        <div class="db-metrics">
            @foreach($metrics as $metric)
            @php $change=$stats[$metric['key'].'_change']; $previous=$stats[$metric['key'].'_previous']; @endphp
            <article class="db-metric {{ $metric['tone'] }}" data-metric="{{ $metric['key'] }}">
                <div class="db-metric-label"><span>{{ $metric['label'] }}</span><i>@include('admin.partials.icon',['name'=>$metric['icon'],'size'=>19])</i></div>
                <strong>{{ $metric['value'] }}</strong><p>{{ $metric['hint'] }}</p>
                <div class="db-metric-comparison" title="Kỳ đối chiếu: {{ $comparisonLabel }}">
                    @if($change === null)<span class="neutral">{{ $stats[$metric['key']] > 0 ? 'Kỳ trước chưa có dữ liệu' : 'Chưa phát sinh trong hai kỳ' }}</span>
                    @elseif($metric['key'] === 'success_rate' && !$stats['transactions'])<span class="neutral">Chưa phát sinh giao dịch</span>
                    @else<span class="{{ $change > 0 ? 'positive' : ($change < 0 ? 'negative' : 'neutral') }}">@if($change != 0)@include('admin.partials.icon',['name'=>$change > 0 ? 'trending-up' : 'trending-down','size'=>14])@endif {{ $change > 0 ? '+' : '' }}{{ number_format($change,1,',','.') }}{{ $metric['key'] === 'success_rate' ? ' điểm %' : '%' }}</span><small>cùng kỳ trước</small>@endif
                </div>
            </article>
            @endforeach
        </div>
    </section>
    @endif

    <div class="db-main-grid {{ !$canReport ? 'db-main-grid--basic' : '' }}">
        @if($canReport)
        @php $revenueMax=max(1,max(array_column($chart,'revenue'))); $bookingMax=max(1,max(array_column($chart,'bookings'))); @endphp
        <section class="db-panel db-chart" aria-labelledby="db-chart-heading" data-chart data-revenue-max="{{ $revenueMax }}" data-bookings-max="{{ $bookingMax }}" data-revenue-total="{{ $stats['revenue'] }}" data-bookings-total="{{ $stats['bookings'] }}">
            <div class="db-panel-heading"><div><span class="db-eyebrow">HIỆU QUẢ KINH DOANH</span><h3 id="db-chart-heading">Xu hướng trong kỳ</h3></div><div class="db-segments" role="tablist" aria-label="Chỉ số biểu đồ"><button type="button" class="is-active" id="db-revenue-tab" data-chart-metric="revenue" role="tab" aria-controls="db-chart-plot" aria-selected="true">Doanh thu</button><button type="button" id="db-bookings-tab" data-chart-metric="bookings" role="tab" aria-controls="db-chart-plot" aria-selected="false" tabindex="-1">Vé</button></div></div>
            <div class="db-chart-summary"><strong data-chart-total>{{ $money($stats['revenue']) }}</strong><span>{{ $periodLabel }} <i>·</i> {{ ['day'=>'Theo giờ','month'=>'Theo ngày','year'=>'Theo tháng'][$range] }}</span></div>
            <div id="db-chart-plot" class="db-chart-plot" role="tabpanel" aria-labelledby="db-revenue-tab">
                <div class="db-chart-axis" aria-hidden="true">@foreach([1,.75,.5,.25,0] as $fraction)<span data-axis-fraction="{{ $fraction }}">{{ $axis($revenueMax*$fraction) }}</span>@endforeach</div>
                <div class="db-chart-scroll"><div class="db-chart-columns" style="--db-points:{{ count($chart) }}">
                    @foreach($chart as $point)<div class="db-chart-point"><button type="button" class="db-chart-bar" data-label="{{ $point['label'] }}" data-revenue="{{ $point['revenue'] }}" data-bookings="{{ $point['bookings'] }}" aria-label="{{ $point['label'] }}: {{ $money($point['revenue']) }}, {{ $point['bookings'] }} vé"><span style="--db-bar-height:{{ $point['revenue']/$revenueMax*100 }}%"></span></button><small>{{ $point['label'] }}</small></div>@endforeach
                </div></div>
                <div class="db-chart-empty" data-chart-empty @if($stats['revenue'] > 0) hidden @endif>@include('admin.partials.icon',['name'=>'chart-no-axes-combined','size'=>30])<p>Chưa có doanh thu trong kỳ này.</p></div>
            </div>
            <output class="db-chart-detail" data-chart-detail aria-live="polite">Chọn một cột để xem số liệu chi tiết.</output>
        </section>
        @endif

        @if(count($operations))
        <section class="db-panel db-operations" aria-labelledby="db-operations-heading">
            <div class="db-panel-heading"><div><span class="db-eyebrow">VẬN HÀNH HÔM NAY</span><h3 id="db-operations-heading">{{ now()->format('d/m/Y') }}</h3></div><span class="db-live">Hôm nay</span></div>
            <div class="db-operation-stats">
                @foreach(['shows_today'=>['Suất chiếu','calendar-clock','admin.showtimes.index',['date'=>now()->toDateString(),'cinema_id'=>$cinemaId]],'shows_week'=>['Suất trong 7 ngày','calendar-days','admin.showtimes.index',['cinema_id'=>$cinemaId]],'ready'=>['Chờ check-in','ticket-check','admin.tickets.index',['status'=>'confirmed','payment_status'=>'paid','cinema_id'=>$cinemaId]],'used'=>['Đã check-in','circle-check','admin.tickets.index',['status'=>'used','cinema_id'=>$cinemaId]]] as $key=>[$label,$icon,$destination,$params])
                @if(isset($operations[$key]))
                    @if($key === 'shows_today')<a href="{{ route($destination,array_filter($params)) }}">@else<div>@endif
                        <i>@include('admin.partials.icon',['name'=>$icon,'size'=>17])</i><strong>{{ number_format($operations[$key],0,',','.') }}</strong><span>{{ $label }}</span>
                    @if($key === 'shows_today')</a>@else</div>@endif
                @endif
                @endforeach
            </div>
            @if(auth()->user()->hasAdminPermission('showtimes.manage'))
            <div class="db-next-heading"><h4>Sắp bắt đầu hôm nay</h4><a href="{{ route('admin.showtimes.index',array_filter(['date'=>now()->toDateString(),'cinema_id'=>$cinemaId])) }}" aria-label="Xem lịch chiếu hôm nay">@include('admin.partials.icon',['name'=>'arrow-right','size'=>17])</a></div>
            <div class="db-next-shows">@forelse($nextShows as $show)<a href="{{ route('admin.showtimes.edit',$show) }}"><time>{{ \Carbon\Carbon::parse($show->start_time)->format('H:i') }}</time><div><strong>{{ $show->movie?->title ?? 'Phim không còn thông tin' }}</strong><span>{{ $show->room?->name }} · {{ $show->room?->cinema?->name }}</span></div></a>@empty<div class="db-small-empty">@include('admin.partials.icon',['name'=>'calendar-days','size'=>22])<p>Không còn suất sắp bắt đầu hôm nay.</p><a href="{{ route('admin.showtimes.create') }}">Thêm suất chiếu @include('admin.partials.icon',['name'=>'plus','size'=>14])</a></div>@endforelse</div>
            @endif
            @if(isset($operations['movies']) || isset($operations['cinemas']) || isset($operations['rooms']))<div class="db-catalog-counts">@foreach(['movies'=>'phim đang chiếu','cinemas'=>'rạp hoạt động','rooms'=>'phòng hoạt động'] as $key=>$label)@if(isset($operations[$key]))<span><b>{{ number_format($operations[$key],0,',','.') }}</b> {{ $label }}</span>@endif @endforeach</div>@endif
        </section>
        @endif
    </div>

    @if($canReport)
    <div class="db-bottom-grid">
        <section class="db-panel db-ranking" aria-labelledby="db-ranking-heading" data-ranking>
            <div class="db-panel-heading"><div><span class="db-eyebrow">DOANH THU NỔI BẬT</span><h3 id="db-ranking-heading">Đóng góp trong kỳ</h3></div><div class="db-segments" role="tablist" aria-label="Xếp hạng doanh thu"><button type="button" id="db-rank-movies-tab" class="is-active" data-rank-tab="movies" role="tab" aria-controls="db-rank-movies" aria-selected="true">Theo phim</button><button type="button" id="db-rank-cinemas-tab" data-rank-tab="cinemas" role="tab" aria-controls="db-rank-cinemas" aria-selected="false" tabindex="-1">Theo rạp</button></div></div>
            @foreach(['movies'=>$topMovies,'cinemas'=>$topCinemas] as $key=>$rows)
            <div id="db-rank-{{ $key }}" data-rank-panel="{{ $key }}" role="tabpanel" aria-labelledby="db-rank-{{ $key }}-tab" @if($key !== 'movies') hidden @endif>
                <ol class="db-ranking-list">@forelse($rows as $row)
                    @php $title=$key === 'movies' ? ($row->title ?? 'Phim không còn thông tin') : ($row->name ?? 'Rạp không còn thông tin'); @endphp
                    <li><span class="db-rank-number">{{ $loop->iteration }}</span>@if($key === 'movies' && $row->poster)<img src="{{ $row->poster }}" alt="Poster {{ $title }}" loading="lazy" width="36" height="50">@else<span class="db-rank-symbol">@include('admin.partials.icon',['name'=>$key === 'movies' ? 'clapperboard' : 'building-2','size'=>21])</span>@endif<div class="db-rank-name"><strong>{{ $title }}</strong><span>{{ number_format($row->bookings,0,',','.') }} vé đã thanh toán{{ $key === 'movies' && $row->status === 'inactive' ? ' · Đã lưu trữ' : '' }}</span></div><div class="db-rank-value"><b>{{ $money($row->revenue) }}</b><span><i style="width:{{ $stats['revenue'] > 0 ? min(100,$row->revenue/$stats['revenue']*100) : 0 }}%"></i></span></div>@if($row->id)<a href="{{ route('admin.reports.index',array_replace($reportParams,[$key === 'movies' ? 'movie_id' : 'cinema_id'=>$row->id,'tab'=>$key])) }}" aria-label="Xem báo cáo {{ $title }}">@include('admin.partials.icon',['name'=>'chevron-right','size'=>17])</a>@endif</li>
                @empty<li class="db-ranking-empty"><span class="db-empty-icon">@include('admin.partials.icon',['name'=>$key === 'movies' ? 'clapperboard' : 'building-2','size'=>30])</span><h4>Chưa có dữ liệu xếp hạng</h4><p>{{ $key === 'movies' ? 'Phim' : 'Rạp' }} có doanh thu sẽ xuất hiện tại đây.</p></li>@endforelse</ol>
            </div>
            @endforeach
            <a class="db-panel-footer" href="{{ route('admin.reports.index',$reportParams) }}">Xem báo cáo đầy đủ @include('admin.partials.icon',['name'=>'arrow-right','size'=>16])</a>
        </section>
        <section class="db-panel db-payments" aria-labelledby="db-payments-heading"><div class="db-panel-heading"><div><span class="db-eyebrow">GIAO DỊCH ĐÃ THANH TOÁN</span><h3 id="db-payments-heading">Phương thức thanh toán</h3></div>@include('admin.partials.icon',['name'=>'wallet','size'=>21])</div><p class="db-payments-caption">Tỷ trọng theo doanh thu trong kỳ đã chọn.</p>
            <div class="db-methods">@forelse($paymentBreakdown as $method)<div class="db-method" style="--db-method-color:{{ ['#ff8d71','#72b4ff','#67d4bc','#bca1f6'][$loop->index % 4] }}"><div><span><i></i>{{ $paymentMethodLabels[$method->method] ?? (strtoupper($method->method ?: 'Khác')) }}</span><b>{{ $money($method->total) }}</b></div><div class="db-method-bar"><span style="width:{{ $stats['revenue'] > 0 ? min(100,$method->total/$stats['revenue']*100) : 0 }}%"></span></div><small>{{ number_format($method->count,0,',','.') }} giao dịch <span>{{ $stats['revenue'] > 0 ? number_format($method->total/$stats['revenue']*100,1,',','.') : 0 }}%</span></small></div>@empty<div class="db-small-empty">@include('admin.partials.icon',['name'=>'wallet','size'=>28])<p>Chưa có giao dịch đã thanh toán trong kỳ.</p></div>@endforelse</div>
        </section>
    </div>
    @endif
    @if($quickLinks->isNotEmpty())<section class="db-shortcuts" aria-label="Truy cập nhanh"><div class="db-shortcuts-heading"><h3>Truy cập nhanh</h3><span>Các tác vụ thường dùng</span></div><div class="db-shortcut-grid">@foreach($quickLinks as $link)<a href="{{ route($link['route']) }}"><i>@include('admin.partials.icon',['name'=>$link['icon'],'size'=>21])</i><div><strong>{{ $link['label'] }}</strong><span>{{ $link['hint'] }}</span></div>@include('admin.partials.icon',['name'=>'arrow-right','size'=>15])</a>@endforeach</div></section>@elseif(!$canReport && !count($operations))<section class="db-panel db-access-empty"><span class="db-empty-icon">@include('admin.partials.icon',['name'=>'shield-check','size'=>30])</span><h3>Chưa có tác vụ quản lý</h3><p>Liên hệ quản trị viên để được cấp quyền phù hợp với công việc.</p></section>@endif
    @if($canReport)<p class="db-data-note">@include('admin.partials.icon',['name'=>'info','size'=>14]) Doanh thu, vé và khách đặt vé tính theo ngày thanh toán. Giao dịch thiếu ngày thanh toán dùng ngày tạo; tiền đã hoàn không tính vào doanh thu.</p>@endif
</div>
@endsection
