@extends('frontend.cinema-corner.layout')
@section('description', $item->displayExcerpt(155))
@section('breadcrumb')<span aria-hidden="true">›</span><span>Bài viết</span>@endsection
@section('content')
<article class="editorial-reading" data-corner-item="{{ $item->id }}">
    <a class="editorial-reading-category" href="{{ route('cinema-corner.index', $category) }}">{{ $item->categoryLabel() }}</a>
    <h1>{{ $category === 'review' ? '[Review] ' : '' }}{{ $item->displayTitle() }}</h1>
    <div class="editorial-reading-meta"><span>TD Cinema</span><span>·</span><time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('d/m/Y') }}</time></div>
    @include('frontend.cinema-corner.engagement')
    @if($item->excerpt)<p class="editorial-reading-lead">{{ $item->displayExcerpt(2000) }}</p>@endif
    <img class="editorial-reading-image" src="{{ $item->imageUrl(true) }}" alt="{{ $item->displayTitle() }}">
    <div class="editorial-reading-body">@foreach(preg_split('/\R\s*\R/u', $item->articleContent()) as $paragraph)<p>{!! nl2br(e($paragraph)) !!}</p>@endforeach</div>
    @if($item->movie || $item->trailerSource())<div class="editorial-reading-actions">@if($item->movie)<a href="{{ route('movies.show', $item->movie) }}">{{ $item->movie->status === 'upcoming' ? 'Xem thông tin phim' : 'Xem lịch chiếu & đặt vé' }} →</a>@endif @if($item->trailerSource())<button type="button" data-corner-trailer="{{ $item->trailerSource() }}">▷ Xem trailer</button>@endif</div>@endif
</article>
@if($related->isNotEmpty())<section class="editorial-related"><h2>Có thể bạn quan tâm</h2><div>@foreach($related as $relatedItem)<article><a href="{{ route('cinema-corner.show', $relatedItem) }}"><img src="{{ $relatedItem->imageUrl(true) }}" alt="{{ $relatedItem->displayTitle() }}" loading="lazy"><h3>{{ $relatedItem->displayTitle() }}</h3></a></article>@endforeach</div></section>@endif
@endsection
