@php
    $searchParams=['q'=>$keyword,'movie_id'=>$movieId,'cinema_id'=>$cinemaId,'date'=>$date ?? ''];
    $searchUrl=fn ($changes=[])=>route('admin.showtimes.index',array_replace($searchParams,$changes));
@endphp
<section class="sf-panel" aria-labelledby="showtime-search-title">
    <header><div><span>TÌM LỊCH CHIẾU</span><h2 id="showtime-search-title">Chọn phim, rạp và ngày</h2></div><b>{{ number_format($showtimes->total()) }} suất phù hợp</b></header>
    <form method="GET" action="{{ route('admin.showtimes.index') }}" class="sf-form">
        <label class="sf-keyword"><span>Tên phim</span><div>@include('admin.partials.icon',['name'=>'search','size'=>18])<input name="q" type="search" maxlength="150" value="{{ $keyword }}" placeholder="Nhập tên có dấu hoặc không dấu..."></div></label>
        <label><span>Phim cụ thể</span><select name="movie_id"><option value="">Tất cả phim</option>@foreach($movies as $movie)<option value="{{ $movie->id }}" @selected((string)$movieId === (string)$movie->id)>{{ $movie->title }}</option>@endforeach</select></label>
        <label><span>Rạp chiếu</span><select name="cinema_id"><option value="">Tất cả rạp</option>@foreach($cinemas as $cinema)<option value="{{ $cinema->id }}" @selected((string)$cinemaId === (string)$cinema->id)>{{ $cinema->name }}</option>@endforeach</select></label>
        <label class="sf-date"><span>Ngày chiếu</span><input type="date" name="date" value="{{ $date }}"></label>
        <button type="submit" class="sf-button primary">@include('admin.partials.icon',['name'=>'list-filter','size'=>16]) Tìm suất</button>
    </form>
    @if($keyword || $movieId || $cinemaId)<div class="sf-chips">@foreach(['q'=>$keyword,'movie_id'=>$movies->firstWhere('id',$movieId)?->title,'cinema_id'=>$cinemas->firstWhere('id',$cinemaId)?->name] as $key=>$label)@if($label)<a href="{{ $searchUrl([$key=>'']) }}" aria-label="Bỏ lọc {{ $label }}">{{ $label }} @include('admin.partials.icon',['name'=>'x','size'=>12])</a>@endif @endforeach</div>@endif
</section>
