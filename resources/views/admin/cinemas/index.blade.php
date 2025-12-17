@extends('admin.layouts.app')

@section('title', 'Quản lý rạp phim')

@section('content')
    <style>
        .cinema-shell { color: #e5e7eb; font-family: 'Manrope','Inter',system-ui,sans-serif; }
        .card { background: #0f172a; border: 1px solid #1f2937; border-radius: 16px; padding: 14px; }
        .input-lite { background: #0b1224; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 14px; color: #e5e7eb; width: 100%; }
        .input-lite:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
        .btn-soft { background: #111827; border: 1px solid #1f2937; color: #e5e7eb; padding: 10px 14px; border-radius: 10px; min-width: 140px; }
        .btn-soft:hover { background: #19243a; }
        .btn-primary { background: #2563eb; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 700; }
        .btn-primary:hover { background: #1d4ed8; }
        .table-wrap { background: #0f172a; border: 1px solid #1f2937; border-radius: 16px; overflow: hidden; }
        .table { width: 100%; }
        .table th, .table td { padding: 14px 12px; text-align: left; }
        .table th { color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .table tr { border-bottom: 1px solid #1f2937; }
        .badge { padding: 6px 12px; border-radius: 9999px; font-weight: 700; font-size: 12px; display: inline-block; }
        .badge-green { background: #16a34a33; color: #22c55e; border: 1px solid #22c55e33; }
        .badge-red { background: #b91c1c33; color: #f87171; border: 1px solid #f8717133; }
        .badge-amber { background: #f59e0b33; color: #fbbf24; border: 1px solid #fbbf2433; }
        .icon-btn { background: transparent; border: 1px solid #1f2937; color: #cbd5e1; padding: 8px 10px; border-radius: 10px; }
        .icon-btn:hover { background: #1f2937; }
    </style>

    <div class="cinema-shell max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-sm text-slate-400">Quản lý rạp phim</p>
                <h1 class="text-2xl font-bold text-white">Quản Lý Rạp Phim</h1>
            </div>
            <a href="{{ route('admin.cinemas.create') }}" class="btn-primary">+ Thêm Rạp Mới</a>
        </div>

        @if (session('success'))
            <div class="mb-3 bg-emerald-500/10 border border-emerald-500/40 text-emerald-200 rounded-lg p-3">
                {{ session('success') }}
            </div>
        @endif

        <div class="card mb-4">
            <div class="flex flex-col md:flex-row md:items-center gap-3">
                <input type="text" placeholder="Tìm theo tên rạp, địa chỉ..." class="input-lite flex-1 min-w-[240px]" disabled>
                <div class="flex items-center gap-2 md:ml-auto">
                    <select class="btn-soft" disabled>
                        <option>Tỉnh/Thành phố</option>
                    </select>
                    <select class="btn-soft min-w-[120px]" disabled>
                        <option>Trạng thái</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table text-sm">
                <thead>
                    <tr class="bg-[#0b1224]">
                        <th class="w-64">Tên rạp</th>
                        <th>Địa chỉ</th>
                        <th class="w-32">Tỉnh/Thành phố</th>
                        <th class="w-28 text-center">Số phòng chiếu</th>
                        <th class="w-32 text-center">Trạng thái</th>
                        <th class="w-28 text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cinemas as $cinema)
                        @php
                            $statusClass = match($cinema->status) {
                                'inactive' => 'badge-red',
                                'maintenance' => 'badge-amber',
                                default => 'badge-green',
                            };
                            $statusLabel = match($cinema->status) {
                                'inactive' => 'Ngừng hoạt động',
                                'maintenance' => 'Sửa chữa',
                                default => 'Đang hoạt động',
                            };
                        @endphp
                        <tr class="hover:bg-[#111a2f] transition">
                            <td class="font-semibold text-white">{{ $cinema->name }}</td>
                            <td class="text-slate-300">{{ $cinema->address }}</td>
                            <td class="text-slate-200">{{ $cinema->city }}</td>
                            <td class="text-center text-slate-200">{{ $cinema->rooms_count }}</td>
                            <td class="text-center">
                                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="text-center space-x-2">
                                <a href="{{ route('admin.cinemas.edit', $cinema) }}" class="icon-btn" title="Sửa">✏️</a>
                                <form action="{{ route('admin.cinemas.destroy', $cinema) }}" method="POST" class="inline-block" onsubmit="return confirm('Xóa rạp này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn" title="Xóa">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-slate-400">Chưa có rạp nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between text-sm text-slate-400 mt-3">
            <span>Hiển thị {{ $cinemas->firstItem() }} - {{ $cinemas->lastItem() }} / {{ $cinemas->total() }}</span>
            <div class="flex items-center gap-2">
                {{ $cinemas->links() }}
            </div>
        </div>
    </div>
@endsection
