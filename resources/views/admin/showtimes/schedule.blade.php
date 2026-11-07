@extends('admin.layouts.app')
@php
    $showCinemaFilter = false;
@endphp
@section('title', 'Quản lý lịch chiếu')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/showtimes.css') }}?v={{ filemtime(public_path('assets/css/admin/showtimes.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/admin/schedule-copy.css') }}?v={{ filemtime(public_path('assets/css/admin/schedule-copy.css')) }}">
@endpush

@section('content')
<div class="sc-wrap space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Quản lý Lịch Chiếu Phim</h1>
            <p class="text-sm text-slate-400 mt-1">Xem, thêm, sửa đổi hoặc xóa các suất chiếu cho một bộ phim cụ thể theo ngày.</p>
        </div>
        <a href="{{ route('admin.showtimes.create', ['movie_id' => $movieId, 'date' => $date]) }}" class="btn-primary">@include('admin.partials.icon',['name'=>'plus','size'=>16]) Thêm suất chiếu</a>
    </div>

    <div class="sc-card space-y-4">
        <form method="GET" class="sc-filter">
            <div class="sc-filter-field">
                <label for="schedule-date">Chọn ngày</label>
                <input id="schedule-date" type="date" name="date" value="{{ $date }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
            </div>
            <div class="sc-filter-field">
                <label for="schedule-movie">Chọn phim</label>
                <select id="schedule-movie" name="movie_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    @foreach ($movies as $movie)
                        <option value="{{ $movie->id }}" {{ $movieId == $movie->id ? 'selected' : '' }}>{{ $movie->title }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary sc-filter-submit">Lọc</button>
        </form>
    </div>

    @if($movieId) @include('admin.showtimes._copy-day') @endif

    <div class="sc-card space-y-3">
        <p class="text-sm text-slate-300">
            Suất chiếu cho:
            <strong>{{ optional($movies->firstWhere('id', $movieId))->title }}</strong>
            - {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
        </p>
        <div class="overflow-x-auto rounded-xl border border-slate-800">
            <table class="sc-table">
                <thead>
                    <tr>
                        <th>Rạp chiếu</th>
                        <th>Phòng chiếu</th>
                        <th>Thời gian bắt đầu</th>
                        <th>Thời gian kết thúc</th>
                        <th class="admin-actions-cell" style="text-align:right;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($showtimes as $showtime)
                        <tr>
                            <td>{{ $showtime->room->cinema->name ?? '-' }}</td>
                            <td>{{ $showtime->room->name ?? '-' }}</td>
                            <td>{{ \Carbon\Carbon::parse($showtime->start_time)->format('H:i') }}</td>
                            <td>{{ \Carbon\Carbon::parse($showtime->end_time)->format('H:i') }}</td>
                            <td class="admin-actions-cell" style="text-align:right;">
                                <div class="admin-actions admin-actions--end">
                                    <a href="{{ route('admin.showtimes.create', [
                                        'movie_id' => $showtime->movie_id,
                                        'room_id' => $showtime->room_id,
                                        'date' => \Carbon\Carbon::parse($showtime->start_time)->toDateString(),
                                        'start_time' => \Carbon\Carbon::parse($showtime->start_time)->format('Y-m-d\TH:i'),
                                        'end_time' => \Carbon\Carbon::parse($showtime->end_time)->format('Y-m-d\TH:i'),
                                    ]) }}" class="admin-action admin-action--icon" title="Nhân bản suất này" aria-label="Nhân bản suất này">@include('admin.partials.icon',['name'=>'copy','size'=>18])</a>
                                    <a href="{{ route('admin.showtimes.edit', $showtime) }}" class="admin-action admin-action--icon admin-action--edit" title="Sửa" aria-label="Sửa">@include('admin.partials.icon', ['name'=>'pencil', 'size'=>18])</a>
                                    <form action="{{ route('admin.showtimes.destroy', $showtime) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" data-confirm-submit="Xóa suất chiếu này?" class="admin-action admin-action--icon admin-action--danger" title="Xóa" aria-label="Xóa">@include('admin.partials.icon', ['name'=>'trash-2', 'size'=>18])</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-slate-400 py-6">Chưa có suất chiếu cho ngày này.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@push('scripts')<script src="{{ asset('assets/js/admin/schedule-copy.js') }}?v={{ filemtime(public_path('assets/js/admin/schedule-copy.js')) }}" defer></script>@endpush
