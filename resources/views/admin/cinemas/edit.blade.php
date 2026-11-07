@extends('admin.layouts.app')

@section('title', 'Quản lý rạp phim')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/cinemas.css') }}?v={{ filemtime(public_path('assets/css/admin/cinemas.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/admin/cinemas.js') }}?v={{ filemtime(public_path('assets/js/admin/cinemas.js')) }}" defer></script>
@endpush

@section('content')
    <div class="page-shell max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-sm text-slate-400">Quản lý rạp phim · Sửa rạp</p>
                <h1 class="text-2xl font-bold text-white">Sửa Rạp</h1>
            </div>
        </div>

        @if ($errors->any())
            <div data-inline-notification class="mb-4 bg-red-500/10 border border-red-400/60 text-red-200 rounded-lg p-3 font-semibold">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <form action="{{ route('admin.cinemas.update', $cinema) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Tên rạp *</label>
                        <input name="name" value="{{ old('name', $cinema->name) }}" class="input" required>
                    </div>
                    @include('admin.cinemas._location-fields')
                    <div class="md:col-span-2">
                        <label class="label">Địa chỉ</label>
                        <input name="address" value="{{ old('address', $cinema->address) }}" class="input">
                    </div>
                    <div>
                        <label class="label">Trạng thái</label>
                        <select name="status" class="input">
                            <option value="active" {{ old('status', $cinema->status ?? 'active')==='active' ? 'selected' : '' }}>Đang hoạt động</option>
                            <option value="inactive" {{ old('status', $cinema->status)==='inactive' ? 'selected' : '' }}>Ngừng hoạt động</option>
                            <option value="maintenance" {{ old('status', $cinema->status)==='maintenance' ? 'selected' : '' }}>Sửa chữa</option>
                        </select>
                    </div>
                </div>

                @include('admin.cinemas._image-field')

                <div class="flex items-center gap-3">
                    <button class="btn-primary" type="submit">Lưu thay đổi</button>
                    <a href="{{ route('admin.cinemas.index') }}" class="btn-ghost hover:text-white">Hủy</a>
                </div>
            </form>
        </div>

        {{-- Quản lý phòng chiếu --}}
        <div class="card mt-6">
            @php
                $editingRoom = request()->filled('edit_room') ? $cinema->rooms->firstWhere('id', request('edit_room')) : null;
            @endphp

            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm text-slate-400">Phòng chiếu của rạp</p>
                    <h2 class="text-xl font-semibold text-white">Danh sách phòng ({{ $cinema->rooms_count }})</h2>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-slate-400 uppercase text-xs">
                            <th class="py-2 text-center w-1/4">Tên phòng</th>
                            <th class="py-2 text-center w-1/4">Loại</th>
                            <th class="py-2 text-center w-1/6">Số ghế</th>
                            <th class="py-2 text-center w-1/6">Trạng thái</th>
                            <th class="py-2 text-center w-1/6">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cinema->rooms as $room)
                            <tr class="border-t border-slate-800">
                                <td class="py-2 text-white font-semibold text-center">{{ $room->name }}</td>
                                <td class="py-2 text-slate-300 text-center">{{ $room->type }}</td>
                                <td class="py-2 text-center text-slate-200">{{ $room->total_seats }}</td>
                                <td class="py-2 text-center">
                                    @php
                                        $rStatus = match($room->status) {
                                            'inactive' => 'bg-red-500/20 text-red-200 border border-red-500/30',
                                            'maintenance' => 'bg-amber-500/20 text-amber-200 border border-amber-500/30',
                                            default => 'bg-emerald-500/20 text-emerald-200 border border-emerald-500/30',
                                        };
                                        $rLabel = match($room->status) {
                                            'inactive' => 'Ngừng hoạt động',
                                            'maintenance' => 'Sửa chữa',
                                            default => 'Đang hoạt động',
                                        };
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $rStatus }}">{{ $rLabel }}</span>
                                </td>
                                <td class="py-2 text-center admin-actions-cell">
                                    <div class="admin-actions">
                                        <a href="{{ route('admin.cinemas.edit', [$cinema, 'edit_room' => $room->id]) }}" class="admin-action admin-action--edit">Sửa</a>
                                        <form action="{{ route('admin.cinemas.rooms.destroy', [$cinema, $room]) }}" method="POST" data-confirm="Xóa phòng này?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="admin-action admin-action--danger">Xóa</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-slate-400">Chưa có phòng chiếu nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5 border-t border-slate-800 pt-4">
                <h3 class="text-lg font-semibold text-white mb-3">Thêm phòng chiếu</h3>
                <form action="{{ route('admin.cinemas.rooms.store', $cinema) }}" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                    @csrf
                    <div class="md:col-span-2">
                        <label class="label">Tên phòng *</label>
                        <input name="name" class="input" required placeholder="Rạp 1, Rạp 2, Rạp 3...">
                    </div>
                    <div>
                        <label class="label">Loại phòng</label>
                        <input name="type" class="input" placeholder="2D, 3D, IMAX...">
                    </div>
                    <div>
                        <label class="label">Số ghế</label>
                        <input type="number" name="total_seats" class="input" min="0" placeholder="0">
                    </div>
                    <div>
                        <label class="label">Trạng thái</label>
                        <select name="status" class="input">
                            <option value="active">Đang hoạt động</option>
                            <option value="inactive">Ngừng hoạt động</option>
                            <option value="maintenance">Sửa chữa</option>
                        </select>
                    </div>
                    <div class="md:col-span-2 flex items-center gap-3">
                        <button class="btn-primary" type="submit">Thêm phòng</button>
                    </div>
                </form>

                @if($editingRoom)
                    <div class="mt-6 border-t border-slate-800 pt-4">
                        <h3 class="text-lg font-semibold text-white mb-3">Sửa phòng: {{ $editingRoom->name }}</h3>
                        <form action="{{ route('admin.cinemas.rooms.update', [$cinema, $editingRoom]) }}" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                            @csrf
                            @method('PUT')
                            <div class="md:col-span-2">
                                <label class="label">Tên phòng *</label>
                                <input name="name" class="input" required value="{{ $editingRoom->name }}">
                            </div>
                            <div>
                                <label class="label">Loại phòng</label>
                                <input name="type" class="input" value="{{ $editingRoom->type }}">
                            </div>
                            <div>
                                <label class="label">Số ghế</label>
                                <input type="number" name="total_seats" class="input" min="0" value="{{ $editingRoom->total_seats }}">
                            </div>
                            <div>
                                <label class="label">Trạng thái</label>
                                <select name="status" class="input">
                                    <option value="active" {{ $editingRoom->status === 'active' ? 'selected' : '' }}>Đang hoạt động</option>
                                    <option value="inactive" {{ $editingRoom->status === 'inactive' ? 'selected' : '' }}>Ngừng hoạt động</option>
                                    <option value="maintenance" {{ $editingRoom->status === 'maintenance' ? 'selected' : '' }}>Sửa chữa</option>
                                </select>
                            </div>
                            <div class="md:col-span-2 flex items-center gap-3">
                                <button class="btn-primary" type="submit">Lưu phòng</button>
                                <a href="{{ route('admin.cinemas.edit', $cinema) }}" class="text-slate-300 hover:text-white text-sm">Hủy</a>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
