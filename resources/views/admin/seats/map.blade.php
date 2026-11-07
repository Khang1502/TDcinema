@extends('admin.layouts.app')
@section('title', 'Sơ đồ ghế - ' . $room->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/seats.css') }}?v={{ filemtime(public_path('assets/css/admin/seats.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/admin/seats.js') }}?v={{ filemtime(public_path('assets/js/admin/seats.js')) }}" defer></script>
@endpush
@section('content')
<div class="seat-admin-shell max-w-6xl mx-auto text-slate-50 space-y-5"
     id="seatMapConfig"
     data-data-url="{{ route('admin.seats.data', $room) }}"
     data-bulk-update-url="{{ route('admin.seats.bulk-update') }}"
     data-bulk-delete-url="{{ route('admin.seats.bulk-delete') }}"
     data-msg-type-prompt="Loại ghế (normal/vip/sweetbox) - để trống nếu không đổi:"
     data-msg-status-prompt="Trạng thái (active/inactive) - để trống nếu không đổi:"
     data-msg-fee-prompt="Phụ thu (để trống nếu không đổi):"
     data-msg-bulk-delete-confirm="Ghế đã có vé sẽ chuyển sang bảo trì; ghế chưa có vé sẽ bị xóa. Bạn muốn tiếp tục?"
     data-seat-fee-label="phụ thu"
     data-currency-suffix="VND">
    <div class="seat-admin-heading">
        <div><span>BIÊN TẬP SƠ ĐỒ</span><h1>{{ $room->name }}</h1><p>Nhấn một ghế để chỉnh chi tiết, giữ Ctrl để chọn nhiều ghế.</p></div>
        <div class="seat-selection-pill"><b id="seatSelectionCount">0</b><small>ghế đang chọn</small></div>
    </div>

    <div class="seat-map-toolbar">
        <select id="filterType" class="seat-control">
            <option value="">Tất cả loại ghế</option>
            <option value="normal">Thường</option>
            <option value="vip">VIP</option>
            <option value="sweetbox">Đôi</option>
        </select>
        <select id="filterStatus" class="seat-control">
            <option value="">Tất cả trạng thái</option>
            <option value="active">Đang hoạt động</option>
            <option value="inactive">Bảo trì</option>
        </select>
        <div class="seat-legend seat-legend--inline" aria-label="Chú thích loại ghế">
            <span class="seat-legend__item"><i class="legend-dot legend-dot--normal"></i>Thường</span>
            <span class="seat-legend__item"><i class="legend-dot legend-dot--vip"></i>VIP</span>
            <span class="seat-legend__item"><i class="legend-dot legend-dot--couple"></i>Đôi</span>
            <span class="seat-legend__item"><i class="legend-dot legend-dot--inactive"></i>Bảo trì</span>
        </div>
    </div>

    <div class="admin-auditorium">
        <div class="admin-screen"><span>Màn hình</span></div>
        <div id="seatGrid" class="seat-grid" role="region" tabindex="0" aria-label="Sơ đồ ghế có lối đi giữa các cụm ghế"></div>
    </div>
    <p class="seat-map-hint">Lối đi giữa các cụm ghế · Cuộn ngang để xem toàn bộ sơ đồ trên màn hình nhỏ.</p>

    <div class="seat-bulk-actions">
        <button id="btnBulkUpdate" class="seat-action-btn seat-action-btn--primary" disabled>
            Cập nhật hàng loạt
        </button>
        <button id="btnBulkDelete" class="seat-action-btn seat-action-btn--danger" disabled>
            Xoá / Khoá ghế đã chọn
        </button>
    </div>
</div>

<!-- Modal chỉnh 1 ghế -->
<div id="seatModal" class="overlay-modal">
    <div class="seat-modal-card w-[420px] max-w-[92vw] p-5 relative">
        <button class="absolute top-2 right-2 text-slate-400 hover:text-white" id="closeSeatModal">✕</button>
        <h3 class="text-xl font-bold mb-1 text-white">Chỉnh ghế <span id="seatModalCode"></span></h3>
        <p class="text-slate-400 text-sm mb-4">Cập nhật loại, trạng thái và phụ thu.</p>
        <input type="hidden" id="seatModalId">
        <div class="space-y-3">
            <div>
                <label class="block text-sm text-slate-200 mb-1">Loại ghế</label>
                <select id="seatModalType" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="normal">Thường</option>
                    <option value="vip">VIP</option>
                    <option value="sweetbox">Đôi</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-slate-200 mb-1">Trạng thái</label>
                <select id="seatModalStatus" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                    <option value="active">Đang hoạt động</option>
                    <option value="inactive">Bảo trì</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-slate-200 mb-1">Phụ thu (VNĐ)</label>
                <input type="number" id="seatModalFee" min="0" step="1000" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
            </div>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <button id="saveSeatModal" class="seat-action-btn seat-action-btn--primary">Lưu thay đổi</button>
        </div>
    </div>
</div>

@endsection
