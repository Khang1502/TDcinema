@extends('admin.layouts.app')

@section('title', 'Quản lý phim')

@section('content')
    <style>
        .movies-shell { color: #dbeafe; font-family: 'Manrope', 'Inter', system-ui, sans-serif; }
        .movies-card { background: #111a2f; border: 1px solid #1f2937; border-radius: 12px; }
        .movies-table { table-layout: fixed; }
        .movies-table th, .movies-table td { padding: 12px 10px; vertical-align: middle; }
        .badge { border-radius: 9999px; padding: 4px 10px; font-size: 12px; font-weight: 600; }
        .movies-row { height: 96px; }
        .poster-cell { display: flex; align-items: center; }
        .input-lite { background: #0f172a; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 14px; color: #e5e7eb; }
        .input-lite:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
        .btn-soft { background: #1f2937; border: 1px solid #1f2937; color: #e5e7eb; padding: 12px 14px; border-radius: 10px; min-width: 140px; text-align: center; }
        .btn-soft:hover { background: #243049; }
        .action-btn { padding: 6px 12px; border-radius: 10px; border: 1px solid #334155; color: #e5e7eb; font-size: 13px; }
        .action-btn.edit { border-color: #60a5fa; color: #93c5fd; }
        .action-btn.delete { border-color: #f87171; color: #fecdd3; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .filter-row .search { flex: 1; min-width: 260px; }
        .filter-row .filters { display: flex; gap: 8px; margin-left: auto; }
        .btn-add { width: fit-content; }
    </style>

    <div class="movies-shell max-w-7xl mx-auto px-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div class="flex-1">
                <p class="text-sm text-slate-400">Thêm, sửa, và xóa phim từ cơ sở dữ liệu.</p>
                <h1 class="text-2xl font-bold text-white mt-1">Quản lý Phim</h1>
            </div>
            <a href="{{ route('admin.movies.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500 self-start md:self-end md:ml-auto btn-add">
                + Thêm phim mới
            </a>
        </div>

        <div class="movies-card p-4 mb-4 flex flex-col gap-3">
            <form method="GET" action="{{ route('admin.movies.index') }}" class="filter-row">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm kiếm theo tên phim..." class="search input-lite">
                <div class="filters">
                    <select name="status" class="btn-soft">
                        <option value="">Trạng thái</option>
                        <option value="now_showing" {{ ($filters['status'] ?? '') === 'now_showing' ? 'selected' : '' }}>Đang chiếu</option>
                        <option value="upcoming" {{ ($filters['status'] ?? '') === 'upcoming' ? 'selected' : '' }}>Sắp chiếu</option>
                        <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Ngừng</option>
                    </select>
                    <button type="submit" class="btn-soft">Lọc</button>
                    <a href="{{ route('admin.movies.index') }}" class="btn-soft">Reset</a>
                </div>
            </form>
        </div>

        <div class="movies-card overflow-hidden">
            <table class="w-full movies-table text-sm text-slate-200">
                <thead class="text-slate-400 uppercase text-xs">
                    <tr class="bg-[#0f172a]">
                        <th class="text-center w-24">Poster</th>
                        <th class="text-center w-64">Tên phim</th>
                        <th class="text-center w-40">Thể loại</th>
                        <th class="text-center w-24">Thời lượng</th>
                        <th class="text-center w-32">Ngày phát hành</th>
                        <th class="text-center w-28">Số suất chiếu</th>
                        <th class="text-center w-28">Trạng thái</th>
                        <th class="text-center w-24">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movies as $movie)
                        @php
                            $status = $movie->status ?? 'upcoming';
                        @endphp
                        <tr class="border-t border-slate-800 hover:bg-[#0f172a] transition movies-row align-middle">
                            <td class="py-3 poster-cell justify-center text-center">
                                @php
                                    $poster = $movie->poster_path ? asset('storage/'.$movie->poster_path) : 'https://via.placeholder.com/60x80?text=Poster';
                                @endphp
                                <img src="{{ $poster }}" alt="{{ $movie->title }}" class="w-16 h-20 object-cover rounded-md border border-slate-700 mx-auto block">
                            </td>
                            <td class="font-semibold text-center align-middle">{{ $movie->title }}</td>
                            <td class="text-slate-300 text-center align-middle">{{ $movie->genre }}</td>
                            <td class="text-slate-300 text-center align-middle">{{ $movie->duration }} phút</td>
                            <td class="text-slate-300 text-center align-middle">
                                {{ $movie->release_date ? \Carbon\Carbon::parse($movie->release_date)->format('M d, Y') : '' }}
                            </td>
                            <td class="text-slate-300 text-center align-middle">{{ $movie->showtimes_count }}</td>
                            <td class="whitespace-nowrap text-center align-middle">
                                @php
                                    $label = [
                                        'now_showing' => 'Đang chiếu',
                                        'upcoming' => 'Sắp chiếu',
                                        'inactive' => 'Ngừng',
                                    ][$status] ?? 'Sắp chiếu';
                                    $class = match($status) {
                                        'now_showing' => 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30',
                                        'inactive' => 'bg-slate-500/20 text-slate-300 border border-slate-500/30',
                                        default => 'bg-amber-500/20 text-amber-300 border border-amber-500/30',
                                    };
                                @endphp
                                <span class="badge {{ $class }}">{{ $label }}</span>
                            </td>
                            <td class="text-center space-x-2 align-middle">
                                <a href="{{ route('admin.movies.edit', $movie) }}" class="action-btn edit">Sửa</a>
                                <form action="{{ route('admin.movies.destroy', $movie) }}" method="POST" class="inline-block" onsubmit="return confirm('Xóa phim này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-btn delete">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-slate-400">Chưa có phim nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between text-sm text-slate-400 mt-4">
            <div>Hiển thị {{ $movies->firstItem() ?? 0 }} - {{ $movies->lastItem() ?? 0 }} / {{ $movies->total() }} phim</div>
            <div class="space-x-2">
                {{ $movies->links() }}
            </div>
        </div>
    </div>
@endsection
