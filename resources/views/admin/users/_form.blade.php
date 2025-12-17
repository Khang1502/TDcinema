@php
    $isEdit = isset($user);
@endphp
<div class="space-y-4">
    <div>
        <label class="block text-sm text-slate-300 mb-1">Họ tên</label>
        <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" class="users-input" required>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-slate-300 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="users-input" required>
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Số điện thoại</label>
            <input type="text" name="phone" value="{{ old('phone', $user->phone ?? '') }}" class="users-input">
        </div>
    </div>
<div class="grid md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm text-slate-300 mb-1">Vai trò</label>
            <select name="role" class="users-select" required id="roleField">
                @foreach (['admin'=>'Admin','manager'=>'Manager','staff'=>'Staff','customer'=>'Customer'] as $key=>$label)
                    <option value="{{ $key }}" {{ old('role', $user->role ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Trạng thái</label>
            <select name="status" class="users-select" required>
                <option value="active" {{ old('status', $user->status ?? 'active') === 'active' ? 'selected' : '' }}>Hoạt động</option>
                <option value="blocked" {{ old('status', $user->status ?? 'active') === 'blocked' ? 'selected' : '' }}>Bị khóa</option>
            </select>
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Mật khẩu {{ $isEdit ? '(để trống nếu giữ nguyên)' : '' }}</label>
            <input type="password" name="password" class="users-input" {{ $isEdit ? '' : 'required' }}>
            <input type="password" name="password_confirmation" class="users-input mt-2" placeholder="Nhập lại mật khẩu" {{ $isEdit ? '' : 'required' }}>
        </div>
    </div>
    <div id="cinemaWrapper">
        <label class="block text-sm text-slate-300 mb-1">Rạp phụ trách (chỉ Staff/Manager)</label>
        <select name="cinema_id" class="users-select">
            <option value="">— Chọn rạp —</option>
            @foreach($cinemas as $cinema)
                <option value="{{ $cinema->id }}" {{ (string)old('cinema_id', $user->cinema_id ?? '') === (string)$cinema->id ? 'selected' : '' }}>
                    {{ $cinema->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>
