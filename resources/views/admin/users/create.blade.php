@extends('admin.layouts.app')
@section('title', ($adminOnly ?? false) ? 'Thêm tài khoản admin' : 'Thêm người dùng')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/users.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/admin/users.js') }}?v={{ filemtime(public_path('assets/js/admin/users.js')) }}" defer></script>
@endpush
@section('content')
<div class="users-shell max-w-4xl mx-auto px-4 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-slate-400">Thêm tài khoản với vai trò và trạng thái cụ thể.</p>
            <h1 class="text-2xl font-bold text-white mt-1">{{ ($adminOnly ?? false) ? 'Thêm tài khoản admin' : 'Thêm người dùng' }}</h1>
        </div>
        <a href="{{ ($adminOnly ?? false) ? route('admin.permissions.index') : route('admin.users.index') }}" class="text-sm text-blue-300 hover:text-blue-200">@include('admin.partials.icon',['name'=>'arrow-left','size'=>16]) Quay lại danh sách</a>
    </div>

    @if ($errors->any())
        <div data-inline-notification class="bg-red-500/10 border border-red-600 text-red-200 px-4 py-3 rounded-lg space-y-1">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.store') }}" class="users-card p-5 space-y-4">
        @csrf
        @include('admin.users._form')
        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-500">Lưu</button>
            <a href="{{ ($adminOnly ?? false) ? route('admin.permissions.index') : route('admin.users.index') }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm hover:bg-slate-700">Hủy</a>
        </div>
    </form>
</div>
@endsection
