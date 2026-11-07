<form class="header-search-form" action="{{ route('movies.index') }}" method="GET" role="search">
    <input type="hidden" name="status" value="all">
    @if(!empty($cinemaId))
        <input type="hidden" name="cinema_id" value="{{ $cinemaId }}">
    @endif
    <label class="sr-only" for="{{ $searchId ?? 'movie-search' }}">Tìm phim</label>
    <input id="{{ $searchId ?? 'movie-search' }}" name="q" type="search"
           value="{{ request('q') }}" placeholder="{{ $headerSettings->search_placeholder ?? 'Tên phim, thể loại...' }}" autocomplete="off">
    <button type="submit" class="header-search-btn" aria-label="Tìm phim">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.15A7.5 7.5 0 1 1 4 11.5a7.5 7.5 0 0 1 15 0Z"/></svg>
    </button>
</form>
