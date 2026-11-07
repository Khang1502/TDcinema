@extends('admin.layouts.app')
@section('title','Quà tặng thành viên')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin/gifts.css') }}?v={{ filemtime(public_path('assets/css/admin/gifts.css')) }}">
@endpush
@include('shared.membership-color-assets')
@section('content')
@php
    $filterQuery = array_filter($filters,fn ($value)=>$value !== '');
    $tierUrl = fn ($level=null)=>route('admin.gifts.index',array_merge($filterQuery,['level'=>$level]));
    $states = ['active'=>['Đang áp dụng','circle-check'],'upcoming'=>['Sắp diễn ra','calendar-clock'],
        'expired'=>['Hết hạn / đã tắt','circle-x'],'exhausted'=>['Hết lượt','circle-alert']];
@endphp
<div class="gifts-page">
    <header class="gifts-heading">
        <div><span class="gifts-eyebrow">TD STARS · ĐẶC QUYỀN THÀNH VIÊN</span><h1>Quà tặng thành viên</h1><p>Phân bổ ưu đãi theo hạng. Theo dõi điều kiện, thời hạn và lượt dùng ở một nơi.</p></div>
        <div class="gifts-heading-actions">
            @if(auth()->user()->hasAdminPermission('membership.manage'))<a class="gifts-button" href="{{ route('admin.membership.index') }}">@include('admin.partials.icon',['name'=>'crown','size'=>17]) Cấp bậc & quyền lợi</a>@endif
            <a class="gifts-button primary" href="{{ route('admin.gifts.create', $filters['level'] && $filters['level'] !== 'other' ? ['level'=>$filters['level']] : []) }}">@include('admin.partials.icon',['name'=>'plus','size'=>18]) Thêm quà tặng</a>
        </div>
    </header>
    <div class="gifts-stats">
        @foreach([['total','gift','Tổng quà tặng','Tất cả voucher thành viên',''],['active','circle-check','Đang áp dụng','Khách đủ Stars có thể sử dụng','active'],['upcoming','calendar-clock','Sắp diễn ra','Đang chờ đến ngày bắt đầu','upcoming'],['attention','circle-alert','Cần kiểm tra','Hết lượt, hết hạn hoặc đã tắt','attention']] as [$key,$icon,$label,$hint,$tone])
        <div class="gifts-stat {{ $tone }}"><span class="gifts-stat-icon">@include('admin.partials.icon',['name'=>$icon,'size'=>21])</span><div><span>{{ $label }}</span><strong>{{ number_format($counts[$key]) }}</strong><small>{{ $hint }}</small></div></div>
        @endforeach
    </div>
    <section class="gifts-catalog" aria-labelledby="gifts-catalog-title">
        <div class="gifts-section-title"><div><h2 id="gifts-catalog-title">Ưu đãi theo cấp bậc</h2><p>Chọn một hạng để xem những quà được mở khóa từ mốc Stars tương ứng.</p></div><span class="gifts-total">{{ number_format($levels->count()) }} hạng đang bật</span></div>
        <nav class="gifts-tiers" aria-label="Lọc quà theo cấp bậc thành viên">
            <a class="gifts-tier all {{ !$filters['level'] ? 'is-selected' : '' }}" href="{{ $tierUrl() }}" @if(!$filters['level']) aria-current="page" @endif><span class="gifts-tier-icon">@include('admin.partials.icon',['name'=>'gift','size'=>22])</span><strong>Tất cả hạng</strong><span>Tổng quan ưu đãi</span><b>{{ array_sum($tierCounts) }} <small>quà phù hợp</small></b></a>
            @foreach($levels as $level)
            <a class="gifts-tier {{ $level->color }} {{ $filters['level'] === (string)$level->id ? 'is-selected' : '' }}" href="{{ $tierUrl($level->id) }}" data-gift-tier="{{ $level->id }}" @if($filters['level'] === (string)$level->id) aria-current="page" @endif>
                <span class="gifts-tier-icon">@include('admin.partials.icon',['name'=>'crown','size'=>22])</span><strong>{{ $level->name }}</strong><span>Từ {{ number_format($level->min_points,0,',','.') }} Stars</span><b>{{ $tierCounts[(string)$level->id] }} <small>quà phù hợp</small></b>
            </a>
            @endforeach
            @if($tierCounts['other'] || $filters['level'] === 'other')<a class="gifts-tier all {{ $filters['level'] === 'other' ? 'is-selected' : '' }}" href="{{ $tierUrl('other') }}" @if($filters['level'] === 'other') aria-current="page" @endif><span class="gifts-tier-icon">@include('admin.partials.icon',['name'=>'settings-2','size'=>22])</span><strong>Mốc Stars khác</strong><span>Chưa thuộc hạng đang bật</span><b>{{ $tierCounts['other'] }} <small>quà phù hợp</small></b></a>@endif
        </nav>
        <form class="gifts-filters" method="GET" action="{{ route('admin.gifts.index') }}">
            @if($filters['level'])<input type="hidden" name="level" value="{{ $filters['level'] }}">@endif
            <label class="gifts-search"><span>Tìm quà tặng</span><div>@include('admin.partials.icon',['name'=>'search','size'=>18])<input type="search" name="q" maxlength="150" value="{{ $filters['q'] }}" placeholder="Tên quà hoặc mã voucher..."></div></label>
            <label><span>Loại ưu đãi</span><select name="type"><option value="">Tất cả loại</option><option value="fixed" @selected($filters['type'] === 'fixed')>Giảm theo số tiền</option><option value="percent" @selected($filters['type'] === 'percent')>Giảm theo phần trăm</option></select></label>
            <label><span>Trạng thái</span><select name="status"><option value="">Tất cả trạng thái</option>@foreach($states as $state=>[$label])<option value="{{ $state }}" @selected($filters['status'] === $state)>{{ $label }}</option>@endforeach</select></label>
            <div class="gifts-filter-actions"><button class="gifts-button primary" type="submit">@include('admin.partials.icon',['name'=>'list-filter','size'=>16]) Lọc</button>@if($filterQuery)<a class="gifts-button" href="{{ route('admin.gifts.index') }}">Đặt lại</a>@endif</div>
        </form>
        <div class="gifts-results-heading"><span><strong>{{ number_format($promotions->total()) }}</strong> quà phù hợp @if($filters['level']) · {{ $levels->first(fn ($level)=>(string)$level->id === $filters['level'])?->name ?? 'Mốc Stars khác' }} @endif</span><p>@include('admin.partials.icon',['name'=>'info','size'=>15]) Khách đủ Stars có thể dùng quà của các hạng thấp hơn.</p></div>
        @forelse($groups as $key=>$gifts)
        @php $level = $levels->first(fn ($level)=>(string)$level->id === (string)$key); @endphp
        <section class="gifts-group {{ $level?->color ?? 'all' }}" data-gift-group="{{ $key }}" aria-label="{{ $level?->name ?? 'Mốc Stars khác' }}">
            <header class="gifts-group-heading"><span class="gifts-group-emblem">@include('admin.partials.icon',['name'=>'crown','size'=>23])</span><div><h3>{{ $level?->name ?? 'Mốc Stars khác' }}</h3><p>{{ $level ? 'Mốc hạng từ '.number_format($level->min_points,0,',','.').' Stars' : 'Quà chưa được xếp vào hạng đang bật' }} <span>· {{ $tierCounts[(string)$key] }} quà phù hợp</span></p></div>@if($level)<a class="gifts-button subtle" href="{{ route('admin.gifts.create',['level'=>$level->id]) }}">@include('admin.partials.icon',['name'=>'plus','size'=>16]) Thêm quà cho hạng</a>@endif</header>
            <div class="gifts-group-list">
                @foreach($gifts as $promotion)
                @php $state=$promotion->display_status; $endingSoon=$state === 'active' && $promotion->end_date->lte(today()->addDays(7)); @endphp
                <article class="gifts-voucher member-gift-admin-card" data-promotion-id="{{ $promotion->id }}" data-gift-status="{{ $state }}">
                    <div class="gifts-voucher-value {{ $promotion->type === 'fixed' && $promotion->value >= 1000000 ? 'large-value' : '' }}"><span>ƯU ĐÃI</span><strong>{{ $promotion->type === 'percent' ? $promotion->value.'%' : number_format($promotion->value,0,',','.') }}</strong><small>{{ $promotion->type === 'percent' ? 'giảm tổng đơn' : 'đồng / đơn' }}</small></div>
                    <div class="gifts-voucher-identity"><div class="gifts-voucher-title"><h4>{{ $promotion->name }}</h4><span class="gifts-status {{ $state }}">@include('admin.partials.icon',['name'=>$states[$state][1],'size'=>13]) {{ $states[$state][0] }}</span></div><code>{{ $promotion->code }}</code>@if($promotion->description)<p class="gifts-description">{{ $promotion->description }}</p>@endif
                        <details class="gifts-details"><summary>Điều kiện & hình ảnh @include('admin.partials.icon',['name'=>'chevron-down','size'=>14])</summary><div>@if($promotion->description)<p>{{ $promotion->description }}</p>@endif<p>Chỉ tài khoản khách hàng có ít nhất <strong>{{ number_format($promotion->min_member_points,0,',','.') }} Stars</strong> dùng được voucher. Lượt giảm sau khi thanh toán thành công trên toàn hệ thống.</p>@if($image=$promotion->imageUrl())<a href="{{ $image }}" target="_blank" rel="noopener"><img src="{{ $image }}" alt="{{ $promotion->name }}" loading="lazy"></a>@else<span class="gifts-no-image">Chưa có ảnh riêng</span>@endif</div></details>
                    </div>
                    <div class="gifts-voucher-meta"><div><span>Stars tối thiểu</span><strong>{{ number_format($promotion->min_member_points,0,',','.') }} Stars</strong></div><div><span>Thời gian áp dụng</span><time>{{ $promotion->start_date->format('d/m/Y') }} – {{ $promotion->end_date->format('d/m/Y') }}</time>@if($endingSoon)<small class="gifts-alert">Sắp hết hạn</small>@endif</div><div><span>Lượt còn lại toàn hệ thống</span><strong class="{{ $state === 'exhausted' ? 'gifts-alert' : '' }}">{{ $promotion->usage_limit === null ? 'Không giới hạn' : number_format($promotion->usage_limit) . ' lượt' }}</strong></div></div>
                    <div class="gifts-voucher-actions"><a class="gifts-button" href="{{ route('admin.gifts.edit',$promotion) }}" aria-label="Chỉnh sửa {{ $promotion->name }}">@include('admin.partials.icon',['name'=>'pencil','size'=>16]) Chỉnh sửa</a><form method="POST" action="{{ route('admin.gifts.destroy',$promotion) }}" data-confirm="Xóa quà tặng {{ $promotion->name }}?">@csrf @method('DELETE')<button class="gifts-button delete" type="submit" aria-label="Xóa {{ $promotion->name }}">@include('admin.partials.icon',['name'=>'trash-2','size'=>16]) Xóa</button></form></div>
                </article>
                @endforeach
            </div>
        </section>
        @empty
        <div class="gifts-empty">@include('admin.partials.icon',['name'=>'gift','size'=>34])<h3>Chưa có quà tặng phù hợp</h3><p>{{ $filterQuery ? 'Thử đổi cấp bậc hoặc đặt lại bộ lọc để xem các ưu đãi khác.' : 'Tạo voucher đầu tiên và chọn mốc Stars để phân bổ cho thành viên.' }}</p><a class="gifts-button" href="{{ $filterQuery ? route('admin.gifts.index') : route('admin.gifts.create') }}">{{ $filterQuery ? 'Xóa bộ lọc' : 'Thêm quà tặng' }}</a></div>
        @endforelse
        @if($promotions->hasPages())<div class="gifts-pagination"><span>Hiển thị {{ $promotions->firstItem() }}–{{ $promotions->lastItem() }} / {{ $promotions->total() }} quà</span>{{ $promotions->onEachSide(1)->links() }}</div>@endif
    </section>
</div>
@endsection
