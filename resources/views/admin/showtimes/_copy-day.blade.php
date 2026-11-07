@php $copyMovie=$movies->firstWhere('id',$movieId); @endphp
<section class="schedule-copy" aria-labelledby="schedule-copy-title">
    <header><span class="schedule-copy-icon">@include('admin.partials.icon',['name'=>'copy','size'=>25])</span><div><h2 id="schedule-copy-title">Sao chép lịch chiếu</h2><p>Giữ nguyên phòng chiếu, khung giờ và giá vé sang ngày khác.</p></div><span class="schedule-copy-movie">@include('admin.partials.icon',['name'=>'clapperboard','size'=>15]) {{ $copyMovie?->title }}</span></header>
    @if($copyMovie?->status === 'now_showing')
    <form method="POST" action="{{ route('admin.showtimes.clone-day') }}" data-schedule-copy>@csrf<input type="hidden" name="movie_id" value="{{ $movieId }}">
        <label for="copy-source-date"><span>Ngày nguồn <small>Lấy lịch từ ngày này</small></span><input id="copy-source-date" type="date" name="source_date" value="{{ old('source_date',$date) }}" required>@error('source_date')<small class="schedule-copy-error">{{ $message }}</small>@enderror</label>
        <span class="schedule-copy-arrow" aria-hidden="true">@include('admin.partials.icon',['name'=>'arrow-right','size'=>22])</span>
        <label for="copy-target-date"><span>Ngày đích <small>Tạo lịch vào ngày này</small></span><input id="copy-target-date" type="date" name="target_date" value="{{ old('target_date',\Carbon\Carbon::parse($date)->addDay()->toDateString()) }}" required>@error('target_date')<small class="schedule-copy-error">{{ $message }}</small>@enderror</label>
        <button class="schedule-copy-button" type="submit">@include('admin.partials.icon',['name'=>'copy','size'=>17]) Sao chép lịch</button>
    </form>
    <p class="schedule-copy-note">@include('admin.partials.icon',['name'=>'info','size'=>16]) Suất trùng phòng/giờ sẽ được bỏ qua. Kết quả hiển thị sau khi sao chép.</p>
    @else<p class="schedule-copy-note">@include('admin.partials.icon',['name'=>'calendar-clock','size'=>18]) Chọn phim đang chiếu để sao chép lịch. Phim sắp chiếu hoặc đã lưu trữ chưa mở bán vé.</p>@endif
</section>
