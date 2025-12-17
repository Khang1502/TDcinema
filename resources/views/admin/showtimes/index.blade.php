@extends('admin.layouts.app')
@php($showCinemaFilter = false)
@section('title', 'Quản lý suất chiếu')

@section('content')
<style>
    .st-wrap { max-width: 1200px; margin: 0 auto; color: #dbeafe; }
    .st-card { background: #10192b; border: 1px solid #1f2937; border-radius: 14px; padding: 18px; }
    .st-table { width: 100%; border-collapse: collapse; color: #e2e8f0; }
    .st-table th { text-align: left; padding: 12px 14px; font-size: 13px; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px; }
    .st-table td { padding: 14px; border-top: 1px solid #1f2937; font-size: 14px; }
    .btn-primary { background: #2563eb; color: #fff; padding: 10px 14px; border-radius: 10px; font-weight: 600; }
    .btn-ghost { border: 1px solid #1f2937; padding: 10px 12px; border-radius: 10px; color: #dbeafe; background: #0f172a; }
</style>

<div class="st-wrap space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Quản lý Suất Chiếu</h1>
            <p class="text-sm text-slate-400 mt-1">Thêm, chỉnh sửa, và xóa các suất chiếu phim.</p>
        </div>
        <a href="{{ route('admin.showtimes.create') }}" class="btn-primary">+ Thêm suất chiếu mới</a>
    </div>

    <div class="st-card space-y-3">
        <form method="GET" class="grid md:grid-cols-5 gap-3 items-center">
            <div class="md:col-span-2">
                <input type="text" name="q" value="{{ $keyword }}" placeholder="Tìm kiếm theo tên phim..."
                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
            </div>
            <div>
                <select name="movie_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="">Tất cả phim</option>
                    @foreach ($movies as $movie)
                        <option value="{{ $movie->id }}" {{ $movieId == $movie->id ? 'selected' : '' }}>{{ $movie->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="cinema_id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="">Tất cả rạp</option>
                    @foreach ($cinemas as $cinema)
                        <option value="{{ $cinema->id }}" {{ $cinemaId == $cinema->id ? 'selected' : '' }}>{{ $cinema->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="date" name="date" value="{{ $date }}" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
            </div>
            <div class="md:col-span-5 flex justify-end gap-3">
                <button class="btn-primary">Lọc</button>
                @if($keyword || $movieId || $cinemaId || $date)
                    <a href="{{ route('admin.showtimes.index') }}" class="btn-ghost">Xóa bộ lọc</a>
                @endif
            </div>
        </form>
    </div>

    <div class="st-card space-y-3">
        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="st-table">
                <thead>
                    <tr>
                        <th>Phim</th>
                        <th>Rạp chiếu</th>
                        <th>Phòng</th>
                        <th>Ngày chiếu</th>
                        <th>Bắt đầu</th>
                        <th>Kết thúc</th>
                        <th>Giá vé</th>
                        <th style="text-align:right;">Vé đã bán</th>
                        <th style="text-align:right;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($showtimes as $showtime)
                        <tr>
                            <td>{{ $showtime->movie->title ?? '-' }}</td>
                            <td>{{ $showtime->room->cinema->name ?? '-' }}</td>
                            <td>{{ $showtime->room->name ?? '-' }}</td>
                            <td>{{ \Carbon\Carbon::parse($showtime->start_time)->format('Y-m-d') }}</td>
                            <td>{{ \Carbon\Carbon::parse($showtime->start_time)->format('H:i') }}</td>
                            <td>{{ \Carbon\Carbon::parse($showtime->end_time)->format('H:i') }}</td>
                            <td>{{ number_format($showtime->base_price ?? 0, 0, ',', '.') }} đ</td>
                            <td style="text-align:right;">—</td>
                             <td style="text-align:right;">
                                <div class="inline-flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.showtimes.create', [
                                        'movie_id' => $showtime->movie_id,
                                        'room_id' => $showtime->room_id,
                                        'date' => \Carbon\Carbon::parse($showtime->start_time)->toDateString(),
                                        'start_time' => \Carbon\Carbon::parse($showtime->start_time)->format('Y-m-d\TH:i'),
                                        'end_time' => \Carbon\Carbon::parse($showtime->end_time)->format('Y-m-d\TH:i'),
                                    ]) }}" class="text-slate-200" title="Nhân bản suất này">📄</a>
                                    <a href="{{ route('admin.showtimes.edit', $showtime) }}" class="text-slate-200" title="Sửa">✏️</a>
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
                            <td colspan="9" class="text-center text-slate-400 py-6">Chưa có suất chiếu.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($showtimes instanceof \Illuminate\Pagination\AbstractPaginator)
            <div class="flex items-center justify-between text-sm text-slate-300">
                <span>Showing {{ $showtimes->firstItem() }} to {{ $showtimes->lastItem() }} of {{ $showtimes->total() }} results</span>
                {{ $showtimes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
