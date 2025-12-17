@extends('admin.layouts.app')
@section('title', 'Chi tiết người dùng')
@php use Illuminate\Support\Str; @endphp
@section('content')
<style>
    .users-shell { color: #e2e8f0; font-family: 'Manrope','Inter',system-ui,sans-serif; }
    .users-card { background: #0e1627; border: 1px solid #1f2937; border-radius: 14px; }
    .detail-row { display: grid; grid-template-columns: 180px 1fr; gap: 12px; padding: 10px 0; border-bottom: 1px solid #1f2937; }
    .detail-row:last-child { border-bottom: none; }
    .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .status-active { background: #0b3c2d; color: #22c55e; border: 1px solid #0f6142; }
    .status-blocked { background: #3b0d0d; color: #f87171; border: 1px solid #b91c1c; }
</style>
<div class="users-shell max-w-4xl mx-auto px-4 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-slate-400">Thông tin chi tiết tài khoản.</p>
            <h1 class="text-2xl font-bold text-white mt-1">{{ $user->name }}</h1>
        </div>
        <a href="{{ route('admin.users.index') }}" class="text-sm text-blue-300 hover:text-blue-200">← Quay lại danh sách</a>
    </div>

    <div class="users-card p-5 space-y-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-slate-800 grid place-items-center text-lg font-bold">
                {{ Str::upper(Str::substr($user->name ?? 'U', 0, 1)) }}
            </div>
            <div>
                <div class="text-white font-semibold">{{ $user->name }}</div>
                <div class="text-slate-400 text-sm">{{ $user->email }}</div>
            </div>
        </div>

        <div class="detail-row">
            <div class="text-slate-400 text-sm">Vai trò</div>
            <div class="capitalize">{{ $user->role }}</div>
        </div>
        <div class="detail-row">
            <div class="text-slate-400 text-sm">Trạng thái</div>
            <div>
                <span class="status-badge {{ $user->status === 'active' ? 'status-active' : 'status-blocked' }}">
                    {{ $user->status === 'active' ? '🟢 Hoạt động' : '🔴 Bị khóa' }}
                </span>
            </div>
        </div>
        <div class="detail-row">
            <div class="text-slate-400 text-sm">Số điện thoại</div>
            <div>{{ $user->phone ?: '—' }}</div>
        </div>
        <div class="detail-row">
            <div class="text-slate-400 text-sm">Thuộc rạp</div>
            <div>{{ $user->cinema?->name ?: '—' }}</div>
        </div>
        <div class="detail-row">
            <div class="text-slate-400 text-sm">Ngày đăng ký</div>
            <div>{{ $user->created_at?->format('d/m/Y H:i') }}</div>
        </div>
        <div class="detail-row">
            <div class="text-slate-400 text-sm">Cập nhật lần cuối</div>
            <div>{{ $user->updated_at?->format('d/m/Y H:i') }}</div>
        </div>
    </div>
</div>
@endsection
