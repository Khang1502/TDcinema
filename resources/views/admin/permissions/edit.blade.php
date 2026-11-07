@extends('admin.layouts.app')

@section('title', 'Chỉnh quyền tài khoản')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/permissions.css') }}">
@endpush

@section('content')
<div class="permission-page permission-edit-page">
    <div class="permission-edit-heading">
        <div>
            <span class="permission-eyebrow">SUPER ADMIN CONTROL</span>
            <h2>Chỉnh quyền tài khoản</h2>
            <p>{{ $adminUser->name }} · {{ $adminUser->email }}</p>
        </div>
        <a href="{{ route('admin.permissions.index') }}" class="permission-action">@include('admin.partials.icon',['name'=>'arrow-left','size'=>16]) Quay lại</a>
    </div>

    @if ($errors->any())
        <div data-inline-notification class="admin-alert admin-alert--error" role="alert">
            <span class="admin-alert__icon">!</span>
            <div><strong>Không thể lưu</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.permissions.update', $adminUser) }}" class="permission-panel permission-edit-form">
        @csrf
        @method('PATCH')
        <div class="permission-panel__head">
            <div><span>Vai trò</span><h3>Cấp quản trị và quyền truy cập</h3></div>
            <p>Quyền riêng sẽ thay thế bộ quyền mặc định của vai trò</p>
        </div>

        <div class="permission-edit-body">
            <label class="permission-role-field">
                <span>Loại tài khoản admin</span>
                <select name="role" @disabled($adminUser->is(auth()->user()))>
                    @foreach($roles as $roleKey => $role)
                        <option value="{{ $roleKey }}" @selected(old('role', $adminUser->role) === $roleKey)>{{ $role['label'] }}</option>
                    @endforeach
                </select>
                @if($adminUser->is(auth()->user()))
                    <input type="hidden" name="role" value="admin">
                @endif
            </label>

            <fieldset class="permission-checkbox-grid" @disabled($adminUser->role === 'admin')>
                <legend>Quyền chức năng</legend>
                @foreach($features as $permission => $label)
                    @php($sensitive = in_array($permission, ['permissions.manage', 'users.manage'], true))
                    <label class="permission-checkbox {{ $sensitive ? 'is-protected' : '' }}">
                        <input type="checkbox" name="permissions[]" value="{{ $permission }}"
                               @checked(in_array('*', old('permissions', $selectedPermissions), true) || in_array($permission, old('permissions', $selectedPermissions), true))
                               @disabled($sensitive)>
                        <span><strong>{{ $label }}</strong><small>{{ $sensitive ? 'Chỉ Super admin' : $permission }}</small></span>
                    </label>
                @endforeach
            </fieldset>

            @if($adminUser->role === 'admin')
                <p class="permission-super-note">Super admin luôn có toàn quyền. Hãy đổi sang Manager hoặc Basic admin nếu cần giới hạn quyền.</p>
            @endif
        </div>

        <div class="permission-edit-actions">
            <button type="submit" class="permission-primary-action">Lưu phân quyền</button>
            <a href="{{ route('admin.users.edit', ['user' => $adminUser, 'from' => 'permissions']) }}" class="permission-action">Chỉnh sửa thông tin tài khoản</a>
        </div>
    </form>
</div>
@endsection
