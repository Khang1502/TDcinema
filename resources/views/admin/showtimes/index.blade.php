@extends('admin.layouts.app')
@php
    $showCinemaFilter = false;
@endphp
@section('title', 'Quản lý suất chiếu')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/showtimes.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/admin/showtime-search.css') }}?v={{ filemtime(public_path('assets/css/admin/showtime-search.css')) }}">
@endpush

@section('content')
<div class="st-wrap space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Quản lý suất chiếu</h1>
            <p class="text-sm text-slate-400 mt-1">Theo dõi suất chiếu theo phim, rạp và thời gian.</p>
        </div>
        <a href="{{ route('admin.showtimes.create') }}" class="btn-primary">@include('admin.partials.icon',['name'=>'plus','size'=>16]) Thêm suất chiếu mới</a>
    </div>

    <div class="st-card space-y-3">
        <form method="POST" action="{{ route('admin.showtimes.refresh-schedule') }}" class="schedule-refresh-form">@csrf
            <div><strong>Đồng bộ theo trạng thái phim</strong><p>Bổ sung lịch cho phim đang chiếu; gỡ lịch sắp chiếu, giữ nguyên vé đã đặt.</p></div>
            <label>Số ngày<select name="days"><option value="7">7 ngày</option><option value="14">14 ngày</option><option value="30">30 ngày</option></select></label><button type="submit" class="btn-primary">Bổ sung lịch chiếu</button>
        </form>
    </div>
    @include('admin.showtimes._search')

    <div class="st-card space-y-3">
        <div class="sf-table-scroll rounded-xl border border-slate-800">
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
                        <th style="text-align:right;">Vé xác nhận</th>
                        <th class="admin-actions-cell" style="text-align:right;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($showtimes as $showtime)
                        <tr>
                            <td>{{ $showtime->movie->title ?? '-' }}<small class="sf-result-state {{ $showtime->status }}">{{ $showtime->status === 'cancelled' ? 'Đã hủy' : 'Đang hoạt động' }}</small></td>
                            <td>{{ $showtime->room->cinema->name ?? '-' }}</td>
                            <td>{{ $showtime->room->name ?? '-' }}</td>
                            <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($showtime->start_time)->format('d/m/Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($showtime->start_time)->format('H:i') }}</td>
                            <td>{{ \Carbon\Carbon::parse($showtime->end_time)->format('H:i') }}</td>
                            <td>{{ number_format($showtime->base_price ?? 0, 0, ',', '.') }} đ</td>
                            <td style="text-align:right;">{{ number_format($showtime->confirmed_tickets_count) }}</td>
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
                            <td colspan="9" class="text-center text-slate-400 py-6">Không có suất phù hợp. Thử đổi ngày hoặc chọn “Tất cả ngày” để xem thêm lịch.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($showtimes->hasPages())
            <div class="flex items-center justify-between text-sm text-slate-300">
                <span>Hiển thị {{ $showtimes->firstItem() }}–{{ $showtimes->lastItem() }} / {{ $showtimes->total() }} suất</span>
                {{ $showtimes->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
