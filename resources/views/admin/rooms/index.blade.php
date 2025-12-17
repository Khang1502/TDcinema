@extends('admin.layouts.app')

@section('title', 'Quản lý phòng chiếu')

@section('content')
    <style>
        .shell { color: #e5e7eb; font-family: 'Manrope','Inter',system-ui,sans-serif; }
        .card { background: #0f172a; border: 1px solid #1f2937; border-radius: 16px; padding: 14px; }
        .table-wrap { background: #0f172a; border: 1px solid #1f2937; border-radius: 16px; overflow: hidden; }
        .table th, .table td { padding: 14px 12px; text-align: center; }
        .table th:first-child, .table td:first-child { text-align: left; }
        .table th { color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge { padding: 6px 12px; border-radius: 9999px; font-weight: 700; font-size: 12px; display: inline-block; }
        .badge-green { background: #16a34a33; color: #22c55e; border: 1px solid #22c55e33; }
        .badge-red { background: #b91c1c33; color: #f87171; border: 1px solid #f8717133; }
        .badge-amber { background: #f59e0b33; color: #fbbf24; border: 1px solid #fbbf2433; }
        .action-link { color: #93c5fd; }
        .action-link:hover { color: #bfdbfe; }
        .input-lite { background: #0b1224; border: 1px solid #1f2937; border-radius: 10px; padding: 10px 12px; color: #e5e7eb; min-width: 200px; }
        .input-lite option { color: #e5e7eb; background: #0b1224; }
        .btn-filter { background: #2563eb; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 700; }
        .btn-filter:hover { background: #1d4ed8; }
    </style>

    <div class="shell max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-sm text-slate-400">Quản lý phòng chiếu</p>
                <h1 class="text-2xl font-bold text-white">Quản lý phòng chiếu</h1>
            </div>
            <a href="{{ route('admin.rooms.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500">+ Thêm phòng</a>
        </div>

        @if (session('success'))
            <div class="mb-3 bg-emerald-500/10 border border-emerald-500/40 text-emerald-200 rounded-lg p-3">
                {{ session('success') }}
            </div>
        @endif

        <div class="card mb-4">
            <form method="GET" class="flex flex-col md:flex-row md:items-center gap-3">
                <select name="cinema_id" class="input-lite bg-[#0b1224] border border-slate-700 text-slate-200 md:w-64">
                    <option value="">Tất cả rạp</option>
                    @foreach($cinemas ?? [] as $cinema)
                        <option value="{{ $cinema->id }}" {{ ($cinemaId ?? null)==$cinema->id ? 'selected' : '' }}>{{ $cinema->name }}</option>
                    @endforeach
                </select>
                <div class="flex items-center gap-2">
                    <button class="btn-filter px-3 py-2 text-sm">Lọc</button>
                    @if($cinemaId)
                        <a href="{{ route('admin.rooms.index') }}" class="text-slate-300 hover:text-white text-sm">Bỏ lọc</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-wrap">
            <table class="table w-full text-sm">
                <thead>
                    <tr class="bg-[#0b1224]">
                        <th class="text-left">Tên phòng</th>
                        <th>Loại</th>
                        <th>Rạp</th>
                        <th>Số ghế</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rooms as $room)
                        @php
                            $statusClass = match($room->status) {
                                'inactive' => 'badge-red',
                                'maintenance' => 'badge-amber',
                                default => 'badge-green',
                            };
                            $statusLabel = match($room->status) {
                                'inactive' => 'Ngừng hoạt động',
                                'maintenance' => 'Sửa chữa',
                                default => 'Đang hoạt động',
                            };
                        @endphp
                        <tr class="border-t border-slate-800 hover:bg-[#111a2f] transition">
                            <td class="font-semibold text-white">{{ $room->name }}</td>
                            <td class="text-slate-300">{{ $room->type }}</td>
                            <td class="text-slate-200">{{ $room->cinema->name ?? '' }}</td>
                            <td class="text-slate-200">{{ $room->total_seats }}</td>
                            <td>
                                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="text-center space-x-3">
                                <a href="{{ route('admin.rooms.edit', $room) }}" class="action-link">Sửa</a>
                                <form action="{{ route('admin.rooms.destroy', $room) }}" method="POST" class="inline-block" onsubmit="return confirm('Xóa phòng này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-300 hover:text-red-200 text-sm">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-slate-400">Chưa có phòng chiếu.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between text-sm text-slate-400 mt-3">
            <span>Hiển thị {{ $rooms->firstItem() }} - {{ $rooms->lastItem() }} / {{ $rooms->total() }}</span>
            <div class="flex items-center gap-2">
                {{ $rooms->links() }}
            </div>
        </div>
    </div>
@endsection
