@extends('admin.layouts.app')
@section('title', 'Sơ đồ ghế - ' . $room->name)
@section('content')
<div class="max-w-6xl mx-auto px-4 text-slate-50 space-y-4">
    <style>
        .seat-grid { display:inline-block; user-select:none; }
        .seat-row { display:flex; align-items:center; margin-bottom:6px; }
        .seat-row-label { width:32px; text-align:right; margin-right:8px; font-weight:700; color:#94a3b8; }
        .seat {
            width:26px; height:26px; border-radius:6px; margin:2px;
            font-size:12px; display:flex; align-items:center; justify-content:center;
            cursor:pointer; color:#0f172a; font-weight:700;
        }
        .seat-normal  { background:#22c55e; }
        .seat-vip     { background:#ef4444; color:#fff; }
        .seat-couple  { background:#a855f7; color:#fff; }
        .seat-inactive{ background:#4b5563; color:#e5e7eb; }
        .seat-selected{ outline:2px solid #facc15; outline-offset:1px; }
        .legend-dot { width:16px; height:16px; border-radius:50%; display:inline-block; margin-right:6px; vertical-align:middle; }
        .overlay-modal{ position:fixed; inset:0; background:rgba(0,0,0,.5); display:none; align-items:center; justify-content:center; z-index:50; }
        .overlay-modal.active{ display:flex; }
    </style>

    <div>
        <p class="text-slate-400 text-sm">Quản lý sơ đồ ghế trực quan theo phòng.</p>
        <h1 class="text-2xl font-bold">Sơ đồ ghế - {{ $room->name }}</h1>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <select id="filterType" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm">
            <option value="">Tất cả loại ghế</option>
            <option value="normal">Thường</option>
            <option value="vip">VIP</option>
            <option value="sweetbox">Đôi</option>
        </select>
        <select id="filterStatus" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm">
            <option value="">Tất cả trạng thái</option>
            <option value="active">Đang hoạt động</option>
            <option value="inactive">Bảo trì</option>
        </select>
        <div class="flex items-center gap-4 text-sm text-slate-200 ml-auto">
            <span><span class="legend-dot" style="background:#22c55e;"></span>Thường</span>
            <span><span class="legend-dot" style="background:#ef4444;"></span>VIP</span>
            <span><span class="legend-dot" style="background:#a855f7;"></span>Đôi</span>
            <span><span class="legend-dot" style="background:#4b5563;"></span>Bảo trì</span>
        </div>
    </div>

    <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-4 shadow-lg shadow-black/30">
        <div id="seatGrid" class="seat-grid"></div>
    </div>

    <div class="flex items-center gap-2">
        <button id="btnBulkUpdate" class="bg-blue-600 hover:bg-blue-500 disabled:bg-slate-700 text-white text-sm font-semibold px-4 py-2 rounded-lg" disabled>
            Cập nhật hàng loạt
        </button>
        <button id="btnBulkDelete" class="bg-red-600 hover:bg-red-500 disabled:bg-slate-700 text-white text-sm font-semibold px-4 py-2 rounded-lg" disabled>
            Xoá / Khoá ghế đã chọn
        </button>
    </div>
</div>

<!-- Modal chỉnh 1 ghế -->
<div id="seatModal" class="overlay-modal">
    <div class="bg-[#0f172a] border border-slate-700 rounded-xl w-[420px] max-w-[92vw] p-5 relative">
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
            <button id="saveSeatModal" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-4 py-2 rounded-lg">Lưu</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const roomId = {{ $room->id }};
    const dataUrl = "{{ route('admin.seats.data', $room) }}";
    const bulkUpdateUrl = "{{ route('admin.seats.bulk-update') }}";
    const bulkDeleteUrl = "{{ route('admin.seats.bulk-delete') }}";
    const csrf = document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content');

    let seatRows = {};      // { A: [{id,...}], B: [...] }
    let selected = new Set();
    let isMouseDown = false;

    document.addEventListener('DOMContentLoaded', () => {
        loadSeats();
        document.addEventListener('mouseup', () => isMouseDown = false);
        document.getElementById('filterType').addEventListener('change', renderGrid);
        document.getElementById('filterStatus').addEventListener('change', renderGrid);
        document.getElementById('btnBulkUpdate').addEventListener('click', openBulkUpdate);
        document.getElementById('btnBulkDelete').addEventListener('click', bulkDelete);
        document.getElementById('saveSeatModal').addEventListener('click', saveSingleSeat);
        document.getElementById('closeSeatModal').addEventListener('click', closeSeatModal);
        document.getElementById('seatModal').addEventListener('click', (e)=>{ if(e.target.id==='seatModal') closeSeatModal(); });
    });

    function loadSeats() {
        fetch(dataUrl)
            .then(r => r.json())
            .then(json => {
                seatRows = json.rows || {};
                selected.clear();
                renderGrid();
            });
    }

    function seatClass(type) {
        if (type === 'vip') return 'seat-vip';
        if (type === 'sweetbox') return 'seat-couple';
        return 'seat-normal';
    }

    function renderGrid() {
        const filterType = document.getElementById('filterType').value;
        const filterStatus = document.getElementById('filterStatus').value;
        const grid = document.getElementById('seatGrid');
        grid.innerHTML = '';

        const rows = Object.keys(seatRows || {}).sort();
        rows.forEach(rowLabel => {
            const rowDiv = document.createElement('div');
            rowDiv.className = 'seat-row';

            const label = document.createElement('div');
            label.className = 'seat-row-label';
            label.textContent = rowLabel;
            rowDiv.appendChild(label);

            seatRows[rowLabel].forEach(seat => {
                if (filterType && seat.type !== filterType) return;
                if (filterStatus && seat.status !== filterStatus) return;

                const div = document.createElement('div');
                div.className = `seat ${seatClass(seat.type)}`;
                if (seat.status === 'inactive') div.classList.add('seat-inactive');
                if (selected.has(seat.id)) div.classList.add('seat-selected');
                div.textContent = seat.number;
                div.title = `${seat.code} • phụ thu: ${seat.extra_price.toLocaleString()}đ`;

                div.dataset.id = seat.id;
                div.dataset.code = seat.code;
                div.dataset.type = seat.type;
                div.dataset.status = seat.status;
                div.dataset.fee = seat.extra_price;

                div.addEventListener('mousedown', () => { isMouseDown = true; toggleSelect(seat.id, div); });
                div.addEventListener('mouseover', () => { if (isMouseDown) toggleSelect(seat.id, div); });
                div.addEventListener('click', (e) => {
                    if (e.metaKey || e.ctrlKey) {
                        toggleSelect(seat.id, div);
                    } else {
                        openSeatModal(div);
                    }
                });

                rowDiv.appendChild(div);
            });

            grid.appendChild(rowDiv);
        });
        updateBulkButtons();
    }

    function toggleSelect(id, el) {
        if (selected.has(id)) {
            selected.delete(id);
            el.classList.remove('seat-selected');
        } else {
            selected.add(id);
            el.classList.add('seat-selected');
        }
        updateBulkButtons();
    }

    function updateBulkButtons() {
        const has = selected.size > 0;
        document.getElementById('btnBulkUpdate').disabled = !has;
        document.getElementById('btnBulkDelete').disabled = !has;
    }

    function openSeatModal(el) {
        document.getElementById('seatModalId').value = el.dataset.id;
        document.getElementById('seatModalCode').textContent = el.dataset.code;
        document.getElementById('seatModalType').value = el.dataset.type;
        document.getElementById('seatModalStatus').value = el.dataset.status;
        document.getElementById('seatModalFee').value = el.dataset.fee || 0;
        document.getElementById('seatModal').classList.add('active');
    }
    function closeSeatModal() {
        document.getElementById('seatModal').classList.remove('active');
    }

    function saveSingleSeat() {
        const id     = document.getElementById('seatModalId').value;
        const type   = document.getElementById('seatModalType').value;
        const status = document.getElementById('seatModalStatus').value === 'active' ? 1 : 0;
        const fee    = parseInt(document.getElementById('seatModalFee').value || 0, 10);
        fetch(bulkUpdateUrl, {
            method: 'PATCH',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN': csrf, 'Accept':'application/json'},
            body: JSON.stringify({
                seats: [{ id, seat_type: type, status, extra_price: fee }]
            })
        }).then(() => {
            closeSeatModal();
            loadSeats();
        });
    }

    function openBulkUpdate() {
        if (!selected.size) return;
        const type = prompt('Loại ghế (normal/vip/sweetbox) - bỏ trống nếu không đổi:','');
        const status = prompt('Trạng thái (active/inactive) - bỏ trống nếu không đổi:','');
        const fee = prompt('Phụ thu (để trống nếu không đổi):','');

        const payload = Array.from(selected).map(id => {
            const item = { id };
            if (type) item.seat_type = type;
            if (status) item.status = status === 'active' ? 1 : (status === 'inactive' ? 0 : null);
            if (fee !== '') item.extra_price = parseInt(fee,10)||0;
            return item;
        });

        fetch(bulkUpdateUrl, {
            method:'PATCH',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({ seats: payload })
        }).then(()=>{ selected.clear(); loadSeats(); });
    }

    function bulkDelete() {
        if (!selected.size) return;
        if (!confirm('Ghế có vé sẽ chuyển về Bảo trì, ghế không vé sẽ bị xoá. Tiếp tục?')) return;
        fetch(bulkDeleteUrl, {
            method:'DELETE',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({ seat_ids: Array.from(selected) })
        }).then(()=>{ selected.clear(); loadSeats(); });
    }
</script>
@endpush
@endsection
