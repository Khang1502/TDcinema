@extends('admin.layouts.app')

@section('title', 'Bảng phân quyền')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/permissions.css') }}">
@endpush

@section('content')
<div class="permission-page">
    <section class="permission-hero">
        <div>
            <span class="permission-eyebrow">SUPER ADMIN CONTROL</span>
            <h2>Bảng phân quyền quản trị</h2>
            <p>Phân vai trò rõ ràng cho nhân viên, quản lý và chủ hệ thống. Mọi quyền đều được kiểm tra ở phía máy chủ.</p>
        </div>
        <div class="permission-hero__stat">
            <strong>{{ $adminUsers->count() }}</strong>
            <span>Tài khoản quản trị</span>
        </div>
        <a href="{{ route('admin.users.create', ['admin_only' => 1]) }}" class="permission-primary-action">@include('admin.partials.icon',['name'=>'plus','size'=>16]) Thêm tài khoản admin</a>
    </section>

    <section class="role-cards" aria-label="Các vai trò quản trị">
        @foreach($roles as $roleKey => $role)
            <article class="role-card role-card--{{ $roleKey }}">
                <span>{{ $role['label'] }}</span>
                <h3>{{ $roleKey === 'staff' ? 'Nhân viên cơ bản' : ($roleKey === 'manager' ? 'Quản lý' : 'Chủ hệ thống') }}</h3>
                <p>{{ $role['description'] }}</p>
                <strong>{{ in_array('*', $role['permissions'], true) ? 'Toàn quyền' : count($role['permissions']).' quyền' }}</strong>
            </article>
        @endforeach
    </section>

    <section class="permission-panel">
        <div class="permission-panel__head">
            <div><span>Ma trận quyền</span><h3>Quyền theo từng loại admin</h3></div>
            <p>✓ Có quyền</p>
        </div>
        <div class="permission-table-wrap">
            <table class="permission-table">
                <thead><tr><th>Chức năng</th>@foreach($roles as $role)<th>{{ $role['label'] }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach($features as $permission => $label)
                        <tr>
                            <td>{{ $label }}</td>
                            @foreach($roles as $role)
                                @php($allowed = in_array('*', $role['permissions'], true) || in_array($permission, $role['permissions'], true))
                                <td><span class="permission-mark {{ $allowed ? 'is-allowed' : 'is-denied' }}">{{ $allowed ? '✓' : '—' }}</span></td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="permission-panel">
        <div class="permission-panel__head">
            <div><span>Tài khoản</span><h3>Gán vai trò admin</h3></div>
            <p>Chỉ Super admin thay đổi được mục này</p>
        </div>
        <div class="admin-account-list">
            <div class="admin-account-list__head" aria-hidden="true">
                <span>Tài khoản</span>
                <span>Vai trò & quyền</span>
                <span>Thao tác</span>
            </div>
            @forelse($adminUsers as $adminUser)
                <article class="admin-account">
                    <div class="admin-account__profile">
                        <div class="admin-account__avatar">{{ mb_strtoupper(mb_substr($adminUser->name, 0, 1)) }}</div>
                        <div class="admin-account__identity">
                            <strong>{{ $adminUser->name }}</strong>
                            <span>{{ $adminUser->email }}</span>
                        </div>
                    </div>
                    <div class="admin-account__meta">
                        <span class="admin-account__role">{{ $roles[$adminUser->role]['label'] }}</span>
                        <span>{{ $adminUser->admin_permissions === null ? 'Quyền mặc định' : count($adminUser->admin_permissions).' quyền riêng' }}</span>
                    </div>
                    <div class="admin-account__actions">
                        @if($adminUser->is(auth()->user()))
                            <span class="admin-account__self">Tài khoản hiện tại</span>
                        @else
                            <a href="{{ route('admin.permissions.edit', $adminUser) }}" class="permission-action permission-action--primary">Chỉnh quyền</a>
                            <a href="{{ route('admin.users.edit', ['user' => $adminUser, 'from' => 'permissions']) }}" class="permission-action">Sửa tài khoản</a>
                            <form method="POST" action="{{ route('admin.users.destroy', $adminUser) }}" data-confirm="Xóa tài khoản {{ $adminUser->name }}? Thao tác này không thể hoàn tác.">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="return_to" value="permissions">
                                <button type="submit" class="permission-action permission-action--danger">Xóa tài khoản</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="permission-empty">Chưa có tài khoản quản trị.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
