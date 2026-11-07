@extends('frontend.cinema-corner.layout')
@section('content')
<div class="editorial-page-heading"><h1>{{ $pageTitle }}</h1><nav class="editorial-category-links" aria-label="Chuyên mục">@foreach(\App\Models\CinemaCornerItem::CATEGORIES as $key => $label)<a href="{{ route('cinema-corner.index', $key) }}" @class(['is-active' => $category === $key])>{{ $label }}</a>@endforeach</nav></div>
<form action="{{ route('cinema-corner.index', $category) }}" method="GET" class="editorial-search"><label for="article-search" class="editorial-sr-only">Tìm bài viết</label><input id="article-search" name="q" value="{{ $search }}" placeholder="Tìm bài viết, tên phim..." maxlength="150"><button type="submit">Tìm kiếm</button></form>
<div class="editorial-listing">
    @forelse($items as $item)
    <article class="editorial-list-item" data-corner-item="{{ $item->id }}">
        <a class="editorial-list-image" href="{{ route('cinema-corner.show', $item) }}"><img src="{{ $item->imageUrl(true) }}" alt="{{ $item->displayTitle() }}" loading="lazy" decoding="async"></a>
        <div><h2><a href="{{ route('cinema-corner.show', $item) }}">{{ $category === 'review' ? '[Review] ' : '' }}{{ $item->displayTitle() }}</a></h2>@include('frontend.cinema-corner.engagement')<p>{{ $item->displayExcerpt(240) }}</p><a class="editorial-read-link" href="{{ route('cinema-corner.show', $item) }}">Đọc bài viết <span aria-hidden="true">→</span></a></div>
    </article>
    @empty
    <div class="editorial-empty"><h2>{{ $search ? 'Không tìm thấy bài viết phù hợp' : 'Bài viết đang được cập nhật' }}</h2><p>{{ $search ? 'Thử một từ khóa khác hoặc xem tất cả bài viết.' : 'Các câu chuyện mới sẽ sớm xuất hiện tại đây.' }}</p>@if($search)<a href="{{ route('cinema-corner.index', $category) }}">Xem tất cả bài viết</a>@endif</div>
    @endforelse
</div>
@if($items->hasPages())<nav class="editorial-pagination" aria-label="Phân trang bài viết">@if($items->onFirstPage())<span>‹</span>@else<a href="{{ $items->previousPageUrl() }}" aria-label="Trang trước">‹</a>@endif @foreach($items->getUrlRange(max(1,$items->currentPage()-2),min($items->lastPage(),$items->currentPage()+2)) as $page => $url)<a href="{{ $url }}" @class(['is-active' => $page === $items->currentPage()]) @if($page === $items->currentPage()) aria-current="page" @endif>{{ $page }}</a>@endforeach @if($items->hasMorePages())<a href="{{ $items->nextPageUrl() }}" aria-label="Trang sau">›</a>@else<span>›</span>@endif</nav>@endif
@endsection
