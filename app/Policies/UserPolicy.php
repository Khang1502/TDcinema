<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    private function isAdmin(?User $user): bool
    {
        return (bool) ($user && $user->role === 'admin');
    }

    private function sameCinema(?User $user, User $model): bool
    {
        if (!$user) return false;
        // Nếu có gán cinema_id, yêu cầu trùng nhau
        if ($user->cinema_id && $model->cinema_id) {
            return $user->cinema_id === $model->cinema_id;
        }
        return false;
    }

    public function viewAny(?User $user): bool
    {
        return (bool) ($user && in_array($user->role, ['admin', 'manager'], true));
    }

    public function view(?User $user, User $model): bool
    {
        return (bool) ($user && in_array($user->role, ['admin', 'manager'], true));
    }

    public function create(?User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(?User $user, User $model): bool
    {
        if (!$user) return false;
        // Chỉ admin được quyền chung
        if ($user->role === 'admin') {
            return true;
        }
        // Manager/Staff chỉ được cập nhật người cùng rạp, và không đụng admin
        if (in_array($user->role, ['manager', 'staff'], true)) {
            if ($model->role === 'admin') return false;
            return $this->sameCinema($user, $model);
        }
        return false;
    }

    public function delete(?User $user, User $model): bool
    {
        if (!$user) return false;
        if ($user->role === 'admin') {
            return $user->id !== $model->id;
        }
        if (in_array($user->role, ['manager', 'staff'], true)) {
            if ($model->role === 'admin') return false;
            return $this->sameCinema($user, $model);
        }
        return false;
    }

    public function toggleStatus(?User $user, User $model): bool
    {
        if (!$user) return false;
        if ($user->role === 'admin') {
            return $user->id !== $model->id;
        }
        if (in_array($user->role, ['manager', 'staff'], true)) {
            if ($model->role === 'admin') return false;
            return $this->sameCinema($user, $model);
        }
        return false;
    }
}
