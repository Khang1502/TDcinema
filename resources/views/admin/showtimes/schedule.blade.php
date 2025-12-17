@extends('admin.layouts.app')
@php($showCinemaFilter = false)
@section('title', 'Quản lý lịch chiếu')

@section('content')
<style>
    .sc-wrap { max-width: 1100px; margin: 0 auto; color: #dbeafe; }
    .sc-card { background: #10192b; border: 1px solid #1f2937; border-radius: 14px; padding: 18px; }
    .sc-table { width: 100%; border-collapse: collapse; color: #e2e8f0; }
    .sc-table th { text-align: left; padding: 12px 14px; font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px; }
    .sc-table td { padding: 14px; border-top: 1px solid #1f2937; font-size: 14px; }
    .btn-primary { background: #2563eb; color: #fff; padding: 10px 14px; border-radius: 10px; font-weight: 600; }
</style>

<div class="sc-wrap space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Quản lý Lịch Chiếu Phim</h1>
            <p class="text-sm text-slate-400 mt-1">Xem, thêm, sửa đổi hoặc xóa các suất chiếu cho một bộ phim cụ thể theo ngày.</p>
        </div>
        <a href="{{ route('admin.showtimes.create', ['movie_id' => $movieId, 'date' => $date]) }}" class="btn-primary">+ Thêm suất chiếu</a>
    </div>

    <div class="sc-card space-y-4">
        <form method="GET" class="grid md:grid-cols-2 gap-4 items-center">
            <div class="flex items-center gap-3">
                <label class="text-sm text-slate-300">Chọn ngày</label>
                <input type="date" name="date" value="{{ $date }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
            </div>
            <div class="flex items-center gap-3">
                <label class="text-sm text-slate-300">Chọn phim</label>
                <select name="movie_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    @foreach ($movies as $movie)
                        <option value="{{ $movie->id }}" {{ $movieId == $movie->id ? 'selected' : '' }}>{{ $movie->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button class="btn-primary">Lọc</button>
            </div>
        </form>
    </div>

    @if($movieId)
    <div class="sc-card space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-white">Copy lịch ngày</h3>
                <p class="text-sm text-slate-400">Sao chép các suất của phim này từ ngày nguồn sang ngày đích (giữ nguyên phòng + giờ).</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.showtimes.clone-day') }}" class="grid md:grid-cols-4 gap-3 items-end">
            @csrf
            <input type="hidden" name="movie_id" value="{{ $movieId }}">
            <div>
                <label class="text-sm text-slate-300 mb-1 block">Ngày nguồn</label>
                <input type="date" name="source_date" value="{{ $date }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" required>
            </div>
            <div>
                <label class="text-sm text-slate-300 mb-1 block">Ngày đích</label>
                <input type="date" name="target_date" value="{{ \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d') }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" required>
            </div>
            <div class="md:col-span-2 flex items-end gap-3">
                <button class="btn-primary" type="submit">Sao chép</button>
                <p class="text-sm text-slate-400">Hệ thống bỏ qua suất trùng phòng/giờ và báo lại.</p>
            </div>
        </form>
    </div>
    @endif

    <div class="sc-card space-y-3">
        <p class="text-sm text-slate-300">
            Suất chiếu cho:
            <strong>{{ optional($movies->firstWhere('id', $movieId))->title }}</strong>
            - {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
        </p>
        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="sc-table">
                <thead>
                    <tr>
                        <th>Rạp chiếu</th>
                        <th>Phòng chiếu</th>
                        <th>Thời gian bắt đầu</th>
                        <th>Thời gian kết thúc</th>
                        <th style="text-align:right;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($showtimes as $showtime)
                        <tr>
                            <td>{{ $showtime->room->cinema->name ?? '-' }}</td>
                            <td>{{ $showtime->room->name ?? '-' }}</td>
                            <td>{{ \Carbon\Carbon::parse($showtime->start_time)->format('H:i') }}</td>
                            <td>{{ \Carbon\Carbon::parse($showtime->end_time)->format('H:i') }}</td>
                            <td style="text-align:right;">
                                <div class="inline-flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.showtimes.create', [
                                        'movie_id' => $showtime->movie_id,
                                        'room_id' => $showtime->room_id,
                                        'date' => \Carbon\Carbon::parse($showtime->start_time)->toDateString(),
                                        'start_time' => \Carbon\Carbon::parse($showtime->start_time)->format('Y-m-d\TH:i'),
                                        'end_time' => \Carbon\Carbon::parse($showtime->end_time)->format('Y-m-d\TH:i'),
                                    ]) }}" class="text-slate-200" title="Nhân bản suất này">📄</a>
                                    <a href="{{ route('admin.showtimes.edit', $showtime) }}" class="text-yellow-400" title="Sửa">✏️</a>
                                    <form action="{{ route('admin.showtimes.destroy', $showtime) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Xóa suất chiếu này?')" class="text-red-400" title="Xóa">🗑️</button>
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
