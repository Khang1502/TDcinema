<?php

namespace App\Support;

use App\Models\User;

class AdminAccess
{
    public static function allows(?User $user, string $permission): bool
    {
        if (!$user || $user->status === 'blocked' || $user->is_active === false || $user->is_active === 0) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        $permissions = $user->admin_permissions;
        if ($permissions === null) {
            $permissions = config("admin_permissions.roles.{$user->role}.permissions", []);
        }

        return in_array($permission, $permissions, true);
    }

    public static function roleLabel(?string $role): string
    {
        return config("admin_permissions.roles.{$role}.label", 'Khách hàng');
    }
}
