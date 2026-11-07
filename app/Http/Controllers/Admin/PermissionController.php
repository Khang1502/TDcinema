<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(): View
    {
        return view('admin.permissions.index', [
            'adminUsers' => User::whereIn('role', ['staff', 'manager', 'admin'])
                ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 ELSE 3 END")
                ->orderBy('name')
                ->get(),
            'roles' => config('admin_permissions.roles'),
            'features' => config('admin_permissions.features'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array($user->role, ['staff', 'manager', 'admin'], true), 404);

        $featureKeys = array_keys(config('admin_permissions.features', []));
        $data = $request->validate([
            'role' => ['required', Rule::in(['staff', 'manager', 'admin'])],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [Rule::in($featureKeys)],
        ]);

        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return back()->with('error', 'Bạn không thể tự hạ quyền Super admin của chính mình.');
        }

        $result = DB::transaction(function () use ($user, $data, $request) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($lockedUser->role === 'admin' && $data['role'] !== 'admin'
                && User::where('role', 'admin')->lockForUpdate()->count() <= 1) {
                return 'Hệ thống phải luôn còn ít nhất một Super admin.';
            }

            $permissions = null;
            if ($data['role'] !== 'admin' && $request->has('permissions')) {
                $permissions = collect($data['permissions'] ?? [])
                    ->reject(fn (string $permission) => in_array($permission, ['permissions.manage', 'users.manage'], true))
                    ->unique()->values()->all();
            }

            $lockedUser->update([
                'role' => $data['role'],
                'admin_permissions' => $permissions,
            ]);

            return null;
        });

        if ($result) {
            return back()->with('error', $result);
        }

        return redirect()->route('admin.permissions.index')
            ->with('success', "Đã cập nhật quyền cho {$user->name}.");
    }

    public function edit(User $user): View
    {
        abort_unless(in_array($user->role, ['staff', 'manager', 'admin'], true), 404);

        $roles = config('admin_permissions.roles');

        return view('admin.permissions.edit', [
            'adminUser' => $user,
            'roles' => $roles,
            'features' => config('admin_permissions.features'),
            'selectedPermissions' => $user->admin_permissions
                ?? ($roles[$user->role]['permissions'] ?? []),
        ]);
    }
}
