@include('admin.partials.form-menu-assets')
@php
    $selectedGenres = \App\Support\MovieGenres::parse(old('genres_present') ? old('genres', []) : old('genre', $movie->genre ?? ''));
    $pickerGenres = \App\Support\MovieGenres::parse([...$selectedGenres, ...$genreOptions]);
    $genreError = $errors->first('genre') ?: $errors->first('genres') ?: $errors->first('genres.*');
@endphp
<div class="am-field">
    <span class="label" id="movie-genre-label">Thể loại</span>
    <input type="hidden" name="genres_present" value="1">
    <details class="am-multiselect" data-genre-picker>
        <summary class="am-trigger" aria-labelledby="movie-genre-label" aria-describedby="movie-genre-help" @if($genreError) aria-invalid="true" @endif>
            <span class="am-trigger-text {{ $selectedGenres ? '' : 'is-placeholder' }}" data-genre-summary>{{ $selectedGenres ? implode(', ', $selectedGenres) : 'Chọn thể loại phim' }}</span>
            <span class="am-count" data-genre-count @if(!$selectedGenres) hidden @endif>{{ count($selectedGenres) }}</span>
            @include('admin.partials.icon',['name'=>'chevron-down','size'=>17])
        </summary>
        <div class="am-menu">
            <label class="am-menu-search"><span class="am-sr-only">Tìm thể loại</span>@include('admin.partials.icon',['name'=>'search','size'=>16])<input type="search" data-genre-search placeholder="Tìm thể loại..." autocomplete="off"></label>
            <div class="am-options" role="group" aria-label="Các thể loại phim">
                @foreach($pickerGenres as $genre)
                <label class="am-check-option" data-genre-option><input type="checkbox" name="genres[]" value="{{ $genre }}" @checked(in_array($genre,$selectedGenres,true))><span>{{ $genre }}@if(isset($genreCatalog[\App\Models\MovieGenre::key($genre)]) && !$genreCatalog[\App\Models\MovieGenre::key($genre)]->is_active)<small class="am-option-note">Đang tắt</small>@endif</span></label>
                @endforeach
                <p class="am-menu-empty" data-genre-empty hidden>Không tìm thấy thể loại phù hợp.</p>
            </div>
            <div class="am-menu-footer"><button type="button" class="am-clear" data-genre-clear>Bỏ chọn tất cả</button><button type="button" class="am-done" data-genre-done>Chọn xong @include('admin.partials.icon',['name'=>'check','size'=>14])</button></div>
        </div>
    </details>
    <p class="am-field-help" id="movie-genre-help">Có thể chọn nhiều thể loại cho một phim. <a href="{{ route('admin.genres.index') }}" target="_blank" rel="noopener">Quản lý thể loại @include('admin.partials.icon',['name'=>'external-link','size'=>11])</a></p>
    @if($genreError)<p class="am-field-error" role="alert">{{ $genreError }}</p>@endif
    <p class="am-field-error" data-genre-limit hidden>Bạn đã chọn quá nhiều thể loại. Vui lòng bỏ bớt để tiếp tục.</p>
</div>
