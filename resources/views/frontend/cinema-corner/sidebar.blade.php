<aside class="editorial-sidebar">
    <section class="editorial-quick-booking" aria-labelledby="editorialBookingTitle">
        <h2 id="editorialBookingTitle">Mua vé nhanh</h2>
        <form data-editorial-booking>
            <label for="editorial-movie">1. Chọn phim</label><select id="editorial-movie" name="movie_id" required><option value="">Chọn phim</option>@foreach($nowShowing as $movie)<option value="{{ $movie->id }}">{{ $movie->title }}</option>@endforeach</select>
            <label for="editorial-cinema">2. Chọn rạp</label><select id="editorial-cinema" name="cinema_id" required disabled><option value="">Chọn rạp</option></select>
            <label for="editorial-date">3. Chọn ngày</label><select id="editorial-date" name="date" required disabled><option value="">Chọn ngày</option></select>
            <label for="editorial-showtime">4. Chọn suất</label><select id="editorial-showtime" name="showtime_id" required disabled><option value="">Chọn suất chiếu</option></select>
            <button type="submit" disabled>Chọn ghế →</button><p class="editorial-booking-message" role="status" aria-live="polite"></p>
        </form>
    </section>
    <section class="editorial-current-movies"><h2>Phim đang chiếu</h2>@foreach($nowShowing->take(3) as $movie)@php $sample = new \App\Models\CinemaCornerItem(); $sample->setRelation('movie', $movie); @endphp<article><a href="{{ route('movies.show', $movie) }}"><img src="{{ $sample->imageUrl(true) }}" alt="{{ $movie->title }}" loading="lazy"><div><h3>{{ $movie->title }}</h3><span>@if($movie->rating_avg)★ {{ number_format($movie->rating_avg,1) }}@endif <small>{{ $movie->duration }} phút</small></span></div></a></article>@endforeach</section>
</aside>
