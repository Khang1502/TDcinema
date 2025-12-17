@extends('admin.layouts.app')
@section('title', 'Quản lý người dùng')

@section('content')
@php use Illuminate\Support\Str; @endphp
<style>
    .users-shell { color: #e2e8f0; font-family: 'Manrope','Inter',system-ui,sans-serif; }
    .users-card { background: #0e1627; border: 1px solid #1f2937; border-radius: 14px; }
    .users-input { background: #0b1221; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 14px; color: #e5e7eb; width: 100%; }
    .users-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
    .users-select { background: #0b1221; border: 1px solid #1f2937; border-radius: 10px; padding: 12px 12px; color: #e5e7eb; min-width: 160px; }
    .users-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
    .user-row { display: grid; grid-template-columns: 1fr 140px 140px 180px 170px; gap: 12px; align-items: center; padding: 14px 16px; border-bottom: 1px solid #1f2937; }
    .user-row:last-child { border-bottom: none; }
    .avatar { width: 44px; height: 44px; border-radius: 50%; background: #1f2937; display: grid; place-items: center; font-weight: 700; color: #cbd5e1; }
    .role-badge { display: inline-flex; align-items: center; gap: 6px; background: #1f2937; color: #cbd5e1; padding: 6px 10px; border-radius: 999px; font-size: 12px; border: 1px solid #26334c; }
    .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .status-active { background: #0b3c2d; color: #22c55e; border: 1px solid #0f6142; }
    .status-blocked { background: #3b0d0d; color: #f87171; border: 1px solid #b91c1c; }
    .action-btn { width: 34px; height: 34px; border-radius: 10px; border: 1px solid #1f2937; background: #0b1221; color: #e2e8f0; display: grid; place-items: center; font-size: 14px; }
    .action-btn:hover { background: #132038; border-color: #334155; }
    .action-btn.muted { color: #94a3b8; cursor: not-allowed; }
    .email-text { color: #9ca3af; font-size: 13px; }
    .filter-row { display: grid; grid-template-columns: 1fr repeat(4, minmax(150px, 1fr)); gap: 12px; }
    .filter-row .date { min-width: 150px; }
    .chip-group { display: flex; flex-wrap: wrap; gap: 8px; }
    .chip { padding: 8px 12px; border-radius: 999px; border: 1px solid #1f2937; background: #0b1221; color: #e2e8f0; font-size: 12px; }
    .chip.active { background: #1d4ed8; border-color: #2563eb; color: #e2e8f0; }
    .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; z-index: 50; }
    .modal-card { background: #0e1627; border: 1px solid #1f2937; border-radius: 16px; padding: 20px; width: 420px; max-width: 92vw; }
    .toast { position: fixed; right: 20px; bottom: 20px; background: #0f172a; color: #e2e8f0; border: 1px solid #1f2937; border-radius: 12px; padding: 12px 14px; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.45); z-index: 60; }
    .toast.success { border-color: #0f6142; color: #34d399; }
    .toast.error { border-color: #b91c1c; color: #fca5a5; }
    @media (max-width: 1024px) {
        .user-row { grid-template-columns: 1fr; align-items: start; }
        .filter-row { grid-template-columns: 1fr; }
    }
</style>

<div class="users-shell max-w-7xl mx-auto px-4 space-y-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <p class="text-sm text-slate-400">Xem, chỉnh sửa, và quản lý quyền truy cập của người dùng.</p>
            <h1 class="text-2xl font-bold text-white mt-1">Quản lý Người Dùng</h1>
        </div>
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500 self-start md:self-end md:ml-auto">
            + Thêm Người Dùng Mới
        </a>
    </div>

    <div class="users-card p-4 space-y-3">
        <form method="GET" action="{{ route('admin.users.index') }}" class="space-y-3" id="filterForm">
            <div class="flex flex-wrap gap-2">
                @php
                    $presetRoles = ['' => 'All', 'staff' => 'Staff', 'manager' => 'Manager', 'customer' => 'Customer'];
                @endphp
                @foreach($presetRoles as $value => $label)
                    <button type="button" data-role="{{ $value }}" class="chip chip-role {{ $filters['role']===$value ? 'active' : '' }}">{{ $label }}</button>
                @endforeach
                <div class="chip-group">
                    <button type="button" data-status="active" class="chip chip-status {{ $filters['status']==='active' ? 'active' : '' }}">Hoạt động</button>
                    <button type="button" data-status="blocked" class="chip chip-status {{ $filters['status']==='blocked' ? 'active' : '' }}">Bị khóa</button>
                </div>
            </div>
            <div class="filter-row">
                <div>
                    <input type="text" name="q" value="{{ $filters['q'] }}" class="users-input" placeholder="Tìm kiếm theo tên, email, hoặc SĐT...">
                </div>
                <div>
                    <select name="role" class="users-select" id="roleSelect">
                        <option value="">Vai trò: Tất cả</option>
                        <option value="admin" {{ $filters['role']==='admin' ? 'selected' : '' }}>Admin</option>
                        <option value="manager" {{ $filters['role']==='manager' ? 'selected' : '' }}>Manager</option>
                        <option value="staff" {{ $filters['role']==='staff' ? 'selected' : '' }}>Staff</option>
                        <option value="customer" {{ $filters['role']==='customer' ? 'selected' : '' }}>Customer</option>
                    </select>
                </div>
                <div>
                    <select name="status" class="users-select" id="statusSelect">
                        <option value="">Trạng thái: Tất cả</option>
                        <option value="active" {{ $filters['status']==='active' ? 'selected' : '' }}>Hoạt động</option>
                        <option value="blocked" {{ $filters['status']==='blocked' ? 'selected' : '' }}>Bị khóa</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <input type="date" name="registered_from" value="{{ $filters['registered_from'] }}" class="users-input date" aria-label="Ngày từ">
                    <input type="date" name="registered_to" value="{{ $filters['registered_to'] }}" class="users-input date" aria-label="Ngày đến">
                </div>
                <div>
                    <select name="sort" class="users-select">
                        <option value="created_desc" {{ $filters['sort']==='created_desc' ? 'selected' : '' }}>Sắp xếp: Mới nhất</option>
                        <option value="created_asc" {{ $filters['sort']==='created_asc' ? 'selected' : '' }}>Sắp xếp: Cũ nhất</option>
                        <option value="role_asc" {{ $filters['sort']==='role_asc' ? 'selected' : '' }}>Sắp xếp: Vai trò</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500">Lọc</button>
                <a href="{{ route('admin.users.index') }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm hover:bg-slate-700">Reset</a>
            </div>
        </form>
    </div>

    <div class="flex items-center justify-between text-sm text-slate-400">
        @php
            $filterParts = [];
            if ($filters['role'])   $filterParts[] = 'Vai trò = '.ucfirst($filters['role']);
            if ($filters['status']) $filterParts[] = 'Trạng thái = '.($filters['status']==='active' ? 'Hoạt động' : 'Bị khóa');
            if ($filters['registered_from'] || $filters['registered_to']) {
                $from = $filters['registered_from'] ?: '—';
                $to   = $filters['registered_to'] ?: '—';
                $filterParts[] = "Ngày: {$from} → {$to}";
            }
        @endphp
        <div>
            @if(count($filterParts))
                Đang lọc: {{ implode(' · ', $filterParts) }} ({{ $filteredCount }} users)
            @else
                Không có bộ lọc — tổng {{ $totalUsers }} users
            @endif
        </div>
        <div class="text-slate-300">
            @php
                $from = ($users->currentPage() - 1) * $users->perPage() + 1;
                $to = $from + $users->count() - 1;
            @endphp
            Showing {{ $from }}–{{ $to }} of {{ $users->total() }}
        </div>
    </div>

    @if (session('success'))
        <div class="bg-emerald-500/10 border border-emerald-600 text-emerald-200 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="users-card overflow-hidden">
        @php
            $roleIcons = [
                'admin' => '👑',
                'manager' => '🛠',
                'staff' => '🛠',
                'customer' => '👤',
            ];
        @endphp
        @forelse ($users as $user)
            <div class="user-row hover:bg-[#0f1b2f] transition">
                <div class="flex items-center gap-3">
                    <div class="avatar">
                        {{ Str::upper(Str::substr($user->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="space-y-1">
                        <div class="text-base font-semibold text-white leading-tight">{{ $user->name }}</div>
                        <div class="email-text">{{ $user->email }}</div>
                        <div class="flex items-center gap-2 text-xs text-slate-400">
                            <span class="role-badge">
                                <span>{{ $roleIcons[$user->role] ?? '👤' }}</span>
                                <span class="capitalize">{{ $user->role }}</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <span>📅</span>
                                <span>Đăng ký: {{ $user->created_at?->format('d/m/Y') }}</span>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="text-sm text-slate-300">
                    <div class="text-xs text-slate-400">SĐT</div>
                    <div class="font-medium">{{ $user->phone ?: '—' }}</div>
                </div>
                <div>
                    <span class="status-badge {{ $user->status === 'active' ? 'status-active' : 'status-blocked' }}">
                        <span>{{ $user->status === 'active' ? '🟢' : '🔴' }}</span>
                        <span>{{ $user->status === 'active' ? 'Hoạt động' : 'Bị khóa' }}</span>
                    </span>
                </div>
                <div class="text-sm text-slate-300">
                    <div class="text-xs text-slate-400">Rạp phụ trách</div>
                    @php
                        $cinemaName = in_array($user->role, ['staff','manager'], true) ? ($user->cinema?->name ?: '—') : '';
                    @endphp
                    <div class="font-medium {{ $cinemaName === '' ? 'text-slate-600' : '' }}">{{ $cinemaName !== '' ? $cinemaName : '—' }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            class="action-btn btn-view"
                            title="Xem nhanh"
                            data-name="{{ $user->name }}"
                            data-email="{{ $user->email }}"
                            data-role="{{ $user->role }}"
                            data-status="{{ $user->status }}"
                            data-phone="{{ $user->phone ?: '—' }}"
                            data-cinema="{{ $user->cinema?->name ?: '—' }}"
                            data-registered="{{ $user->created_at?->format('d/m/Y H:i') }}">
                        👁
                    </button>
                    <a href="{{ route('admin.users.edit', $user) }}" class="action-btn" title="Chỉnh sửa">✏</a>
                    <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}" data-confirm="Bạn có chắc muốn {{ $user->status === 'active' ? 'khóa' : 'mở khóa' }} tài khoản này?">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="action-btn" title="{{ $user->status === 'active' ? 'Khóa' : 'Mở khóa' }}">
                            {{ $user->status === 'active' ? '🔒' : '🔓' }}
                        </button>
                    </form>
                    @if($user->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Xóa người dùng này?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="action-btn" title="Xóa">🗑</button>
                    </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-6 text-center text-slate-400">Không có người dùng nào.</div>
        @endforelse
    </div>

    <div class="flex items-center justify-center">
        {{ $users->onEachSide(1)->links() }}
    </div>
</div>

{{-- Toast --}}
<div id="toast" class="toast {{ session('success') ? 'success' : '' }}">{{ session('success') }}</div>

{{-- Modal confirm --}}
<div id="confirmModal" class="modal-backdrop">
    <div class="modal-card space-y-4">
        <h3 class="text-lg font-semibold text-white">Xác nhận</h3>
        <p id="confirmMessage" class="text-slate-300 text-sm"></p>
        <div class="flex justify-end gap-2">
            <button type="button" id="confirmCancel" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm hover:bg-slate-700">Hủy</button>
            <button type="button" id="confirmOk" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500">Đồng ý</button>
        </div>
    </div>
</div>

{{-- Modal xem nhanh --}}
<div id="detailModal" class="modal-backdrop">
    <div class="modal-card space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div id="modalAvatar" class="w-10 h-10 rounded-full bg-slate-800 grid place-items-center text-sm font-semibold"></div>
                <div>
                    <div id="modalName" class="text-white font-semibold"></div>
                    <div id="modalEmail" class="text-slate-400 text-sm"></div>
                </div>
            </div>
            <button type="button" id="detailClose" class="text-slate-400 hover:text-white text-lg">✕</button>
        </div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-400">Vai trò</span> <span id="modalRole" class="capitalize text-white"></span></div>
            <div class="flex justify-between"><span class="text-slate-400">Trạng thái</span> <span id="modalStatus"></span></div>
            <div class="flex justify-between"><span class="text-slate-400">SĐT</span> <span id="modalPhone" class="text-white"></span></div>
            <div class="flex justify-between"><span class="text-slate-400">Rạp phụ trách</span> <span id="modalCinema" class="text-white"></span></div>
            <div class="flex justify-between"><span class="text-slate-400">Đăng ký</span> <span id="modalRegistered" class="text-white"></span></div>
        </div>
    </div>
</div>

<script>
    (function() {
        const toastEl = document.getElementById('toast');
        if (toastEl && toastEl.textContent.trim() !== '') {
            toastEl.style.display = 'block';
            setTimeout(() => toastEl.style.display = 'none', 3000);
        }

        const filterForm = document.getElementById('filterForm');
        const roleSelect = document.getElementById('roleSelect');
        const statusSelect = document.getElementById('statusSelect');
        document.querySelectorAll('.chip-role').forEach(btn => {
            btn.addEventListener('click', () => {
                if (roleSelect) roleSelect.value = btn.dataset.role ?? '';
                filterForm?.submit();
            });
        });
        document.querySelectorAll('.chip-status').forEach(btn => {
            btn.addEventListener('click', () => {
                if (statusSelect) statusSelect.value = btn.dataset.status ?? '';
                filterForm?.submit();
            });
        });

        let pendingForm = null;
        const confirmModal = document.getElementById('confirmModal');
        const confirmMsg = document.getElementById('confirmMessage');
        const confirmOk = document.getElementById('confirmOk');
        const confirmCancel = document.getElementById('confirmCancel');

        document.querySelectorAll('form[data-confirm]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                pendingForm = form;
                confirmMsg.textContent = form.dataset.confirm || 'Xác nhận thao tác?';
                confirmModal.style.display = 'flex';
            });
        });
        confirmOk?.addEventListener('click', function() {
            if (pendingForm) pendingForm.submit();
            confirmModal.style.display = 'none';
        });
        confirmCancel?.addEventListener('click', function() {
            confirmModal.style.display = 'none';
            pendingForm = null;
        });

        const detailModal = document.getElementById('detailModal');
        const detailClose = document.getElementById('detailClose');
        const fill = (id, value) => document.getElementById(id).textContent = value;
        document.querySelectorAll('.btn-view').forEach(btn => {
            btn.addEventListener('click', () => {
                fill('modalName', btn.dataset.name || '');
                fill('modalEmail', btn.dataset.email || '');
                fill('modalRole', btn.dataset.role || '');
                fill('modalPhone', btn.dataset.phone || '—');
                fill('modalCinema', btn.dataset.cinema || '—');
                fill('modalRegistered', btn.dataset.registered || '—');
                const status = btn.dataset.status === 'active'
                    ? '<span class="status-badge status-active">🟢 Hoạt động</span>'
                    : '<span class="status-badge status-blocked">🔴 Bị khóa</span>';
                document.getElementById('modalStatus').innerHTML = status;
                document.getElementById('modalAvatar').textContent = (btn.dataset.name || 'U').trim().charAt(0).toUpperCase();
                detailModal.style.display = 'flex';
            });
        });
        detailClose?.addEventListener('click', () => detailModal.style.display = 'none');
        detailModal?.addEventListener('click', (e) => {
            if (e.target === detailModal) detailModal.style.display = 'none';
        });
    })();
</script>
@endsection
