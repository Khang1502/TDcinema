@extends('admin.layouts.app')
@section('title', 'Phim lưu trữ')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/admin/movie-archive.css') }}?v={{ filemtime(public_path('assets/css/admin/movie-archive.css')) }}">@endpush
@section('content')
<div class="movie-archive">
    <div class="archive-heading"><div><span class="archive-kicker">LỊCH SỬ ĐIỆN ẢNH</span><h1>Phim lưu trữ</h1><p>Phim đã ngừng chiếu vẫn giữ đầy đủ lịch sử suất chiếu, vé và thanh toán.</p></div><a class="archive-button" href="{{ route('admin.movies.index') }}">@include('admin.partials.icon',['name'=>'arrow-left','size'=>16]) Quản lý phim</a></div>
    <form method="GET" class="archive-search"><label for="archive-search">Tìm phim trong kho lưu trữ</label><div><input id="archive-search" name="q" value="{{ $search }}" placeholder="Nhập tên phim..."><button class="archive-button primary" type="submit">Tìm kiếm</button>@if($search !== '')<a class="archive-button" href="{{ route('admin.movies.archive') }}">Xóa lọc</a>@endif</div></form>
    <div class="archive-panel archive-table-scroll"><table class="archive-table"><thead><tr><th>Phim</th><th>Suất chiếu</th><th>Vé đã bán</th>@if($financial)<th>Doanh thu đã thanh toán</th>@endif<th>Chi tiết</th></tr></thead><tbody>
    @forelse($movies as $movie)
    @php $stats = $summaries[$movie->id]; @endphp
    <tr><td><div class="archive-movie"><img src="{{ $movie->posterUrl() }}" alt="{{ $movie->title }}" loading="lazy"><div><strong>{{ $movie->title }}</strong><small>{{ $movie->genre ?: 'Chưa có thể loại' }} · {{ $movie->duration }} phút</small><small>Khởi chiếu {{ $movie->release_date?->format('d/m/Y') ?? '—' }}</small></div></div></td><td>{{ number_format($stats['showtimes_count']) }}</td><td>{{ number_format($stats['sold_count']) }}</td>@if($financial)<td class="archive-money">{{ number_format($stats['revenue'],0,',','.') }} đ</td>@endif<td><a class="archive-button" href="{{ route('admin.movies.archive.show', $movie) }}">Xem lịch sử @include('admin.partials.icon',['name'=>'arrow-right','size'=>16])</a></td></tr>
    @empty<tr><td colspan="{{ $financial ? 5 : 4 }}" class="archive-empty">Chưa có phim lưu trữ phù hợp. Chọn “Lưu trữ” trong danh sách phim để giữ lại lịch sử khi phim ngừng chiếu.</td></tr>@endforelse
    </tbody></table></div>
    <div class="archive-pagination"><span>{{ $movies->total() }} phim lưu trữ</span>{{ $movies->onEachSide(1)->links() }}</div>
</div>
@endsection
