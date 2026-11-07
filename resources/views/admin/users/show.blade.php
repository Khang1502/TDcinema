@extends('admin.layouts.app')
@section('title', 'Chi tiết người dùng')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/users.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/admin/users.js') }}?v={{ filemtime(public_path('assets/js/admin/users.js')) }}" defer></script>
@endpush
@php use Illuminate\Support\Str; @endphp
@section('content')
<div class="users-shell max-w-4xl mx-auto px-4 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-slate-400">Thông tin chi tiết tài khoản.</p>
            <h1 class="text-2xl font-bold text-white mt-1">{{ $user->name }}</h1>
        </div>
        <a href="{{ route('admin.users.index') }}" class="text-sm text-blue-300 hover:text-blue-200">@include('admin.partials.icon',['name'=>'arrow-left','size'=>16]) Quay lại danh sách</a>
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

