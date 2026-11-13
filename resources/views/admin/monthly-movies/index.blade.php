@extends('admin.layouts.app')
@section('title', 'Quản lý Phim hay tháng')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/admin/monthly-movies.css') }}?v={{ filemtime(public_path('assets/css/admin/monthly-movies.css')) }}">@endpush
@section('content')
<div class="monthly-page">
    <header class="monthly-heading">
        <div>
            <span class="monthly-eyebrow">TD CINEMA · NỘI DUNG TRANG KHÁCH</span>
            <h1>Phim hay tháng</h1>
            <p>Chỉ phim đang chiếu đạt tiêu chí đánh giá và được bật hiển thị mới xuất hiện trên trang khách. Thứ tự tự động xếp theo điểm, rồi đến lượt đánh giá.</p>
        </div>
        <div class="monthly-heading-actions">
            <a class="monthly-button" href="{{ route('movies.index', ['status' => 'top_rated']) }}" target="_blank" rel="noopener">Xem trang khách @include('admin.partials.icon', ['name' => 'external-link', 'size' => 16])</a>
            <a class="monthly-button" href="{{ route('admin.movies.index') }}">Danh mục phim</a>
        </div>
    </header>

    <div class="monthly-stats">
        <div><span>Đang hiển thị</span><strong>{{ number_format($visibleCount) }}</strong><small>Phim đạt điều kiện</small></div>
        <div><span>Phim đang chiếu</span><strong>{{ number_format($nowShowingCount) }}</strong><small>Có thể quản lý tại đây</small></div>
        <div><span>Điểm tối thiểu</span><strong>{{ number_format($settings->min_rating, 1) }} / 5</strong><small>Ưu tiên điểm cao nhất</small></div>
    </div>

    <section class="monthly-panel" aria-labelledby="monthly-settings-title">
        <div class="monthly-panel-heading"><div><h2 id="monthly-settings-title">Tiêu chí hiển thị</h2><p>Điều chỉnh ngưỡng chung; danh sách trang khách cập nhật ngay sau khi lưu.</p></div></div>
        <form class="monthly-settings-form" method="POST" action="{{ route('admin.monthly-movies.settings') }}">
            @csrf @method('PUT')
            <label>Điểm đánh giá tối thiểu <input type="number" name="min_rating" min="0.1" max="5" step="0.1" value="{{ old('min_rating', $settings->min_rating) }}" required><small>Thang điểm 0–5, mặc định từ 4.0.</small></label>
            <label>Lượt đánh giá tối thiểu <input type="number" name="min_reviews" min="0" max="1000000" step="1" value="{{ old('min_reviews', $settings->min_reviews) }}" required><small>Đặt 0 để vẫn dùng điểm của phim chưa có số lượt đánh giá.</small></label>
            <button class="monthly-button primary" type="submit">Lưu tiêu chí</button>
        </form>
    </section>

    <section class="monthly-panel" aria-labelledby="monthly-list-title">
        <div class="monthly-panel-heading"><div><h2 id="monthly-list-title">Phim đang chiếu</h2><p>Sửa điểm, lượt đánh giá hoặc ẩn từng phim khỏi mục Phim hay tháng.</p></div><form class="monthly-search" method="GET" action="{{ route('admin.monthly-movies.index') }}"><label for="monthly-search">Tìm tên phim</label><input id="monthly-search" name="q" type="search" value="{{ $search }}" maxlength="150" placeholder="Nhập tên phim..."><button class="monthly-button" type="submit">Tìm</button></form></div>
        @if($search !== '')<p class="monthly-search-result">{{ $movies->total() }} phim khớp “{{ $search }}” · <a href="{{ route('admin.monthly-movies.index') }}">Xóa tìm kiếm</a></p>@endif
        <div class="monthly-list">
            @forelse($movies as $movie)
                @php $isVisible = $movie->is_monthly_featured && (float) $movie->rating_avg >= $settings->min_rating && (int) $movie->review_count >= $settings->min_reviews; @endphp
                <article class="monthly-movie">
                    <img src="{{ $movie->posterUrl() }}" alt="Poster {{ $movie->title }}" loading="lazy" width="76" height="108">
                    <div class="monthly-movie-info"><span class="monthly-state {{ $isVisible ? 'visible' : '' }}">{{ $isVisible ? 'Đang hiển thị' : ($movie->is_monthly_featured ? 'Chưa đạt tiêu chí' : 'Đã ẩn') }}</span><h3>{{ $movie->title }}</h3><p>{{ $movie->genre ?: 'Chưa có thể loại' }} · {{ $movie->duration }} phút</p><a href="{{ route('admin.movies.edit', $movie) }}">Chỉnh sửa thông tin phim</a></div>
                    <form class="monthly-movie-form" method="POST" action="{{ route('admin.monthly-movies.update', $movie) }}">
                        @csrf @method('PATCH')
                        <label>Điểm / 5 <input type="number" name="rating_avg" min="0" max="5" step="0.1" value="{{ $movie->rating_avg }}" required></label>
                        <label>Lượt đánh giá <input type="number" name="review_count" min="0" max="1000000" step="1" value="{{ $movie->review_count }}" required></label>
                        <label>Hiển thị <select name="is_monthly_featured"><option value="1" @selected($movie->is_monthly_featured)>Bật</option><option value="0" @selected(!$movie->is_monthly_featured)>Ẩn</option></select></label>
                        <button class="monthly-button primary" type="submit">Lưu phim</button>
                    </form>
                </article>
            @empty
                <div class="monthly-empty"><h3>{{ $search ? 'Không tìm thấy phim' : 'Chưa có phim đang chiếu' }}</h3><p>{{ $search ? 'Thử từ khóa khác để tìm phim đang chiếu.' : 'Thêm hoặc chuyển phim sang trạng thái đang chiếu trong danh mục phim.' }}</p><a class="monthly-button" href="{{ $search ? route('admin.monthly-movies.index') : route('admin.movies.index') }}">{{ $search ? 'Xóa tìm kiếm' : 'Mở danh mục phim' }}</a></div>
            @endforelse
        </div>
        @if($movies->hasPages())<div class="monthly-pagination">{{ $movies->onEachSide(1)->links() }}</div>@endif
    </section>
</div>
@endsection
