@extends('admin.layouts.app')

@section('title', 'Quản lý phòng chiếu')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/rooms.css') }}">
@endpush

@section('content')
    <div class="shell max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-sm text-slate-400">Quản lý phòng chiếu</p>
                <h1 class="text-2xl font-bold text-white">Quản lý phòng chiếu</h1>
            </div>
            <a href="{{ route('admin.rooms.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500">@include('admin.partials.icon',['name'=>'plus','size'=>16]) Thêm phòng</a>
        </div>

        

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
                        <th class="admin-actions-cell">Hành động</th>
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
                            <td class="text-center admin-actions-cell">
                                <div class="admin-actions">
                                    <a href="{{ route('admin.rooms.edit', $room) }}" class="admin-action admin-action--edit">Sửa</a>
                                    <form action="{{ route('admin.rooms.destroy', $room) }}" method="POST" data-confirm="Xóa phòng này?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="admin-action admin-action--danger">Xóa</button>
                                    </form>
                                </div>
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

