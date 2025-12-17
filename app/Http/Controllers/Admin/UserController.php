<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $query = User::query()->with('cinema');

        $search = trim((string) $request->get('q', ''));
        $role = $request->get('role');
        $status = $request->get('status');
        $dateFrom = $request->get('registered_from');
        $dateTo = $request->get('registered_to');
        $sort = $request->get('sort', 'created_desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (in_array($role, ['admin', 'manager', 'staff', 'customer'], true)) {
            $query->where('role', $role);
        }

        if (in_array($status, ['active', 'blocked'], true)) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)->toDateString());
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', Carbon::parse($dateTo)->toDateString());
        }

        $query = match ($sort) {
            'created_asc' => $query->orderBy('created_at', 'asc'),
            'role_asc' => $query->orderBy('role', 'asc')->orderBy('name', 'asc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $users = $query->paginate(10)->withQueryString();

        $filters = [
            'q' => $search,
            'role' => $role,
            'status' => $status,
            'registered_from' => $dateFrom,
            'registered_to' => $dateTo,
            'sort' => $sort,
        ];

        $totalUsers = User::count();
        $filteredCount = $users->total();

        return view('admin.users.index', compact('users', 'filters', 'totalUsers', 'filteredCount'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);
        $cinemas = Cinema::orderBy('name')->get(['id','name']);
        return view('admin.users.create', compact('cinemas'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['admin', 'manager', 'staff', 'customer'])],
            'status' => ['required', Rule::in(['active', 'blocked'])],
            'cinema_id' => ['nullable', 'exists:cinemas,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $data['status'] === 'active';
        if (!in_array($data['role'], ['staff', 'manager'], true)) {
            $data['cinema_id'] = null;
        }

        User::create($data);

        return redirect()->route('admin.users.index')->with('success', 'Tạo người dùng thành công.');
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);
        $cinemas = Cinema::orderBy('name')->get(['id','name']);
        return view('admin.users.edit', compact('user','cinemas'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['admin', 'manager', 'staff', 'customer'])],
            'status' => ['required', Rule::in(['active', 'blocked'])],
            'cinema_id' => ['nullable', 'exists:cinemas,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['is_active'] = $data['status'] === 'active';

        if ($user->id === auth()->id()) {
            unset($data['role'], $data['status'], $data['is_active']);
        }
        if (!in_array($data['role'] ?? $user->role, ['staff', 'manager'], true)) {
            $data['cinema_id'] = null;
        }

        $user->update($data);

        Log::info('user.updated', [
            'by' => auth()->id(),
            'target' => $user->id,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Cập nhật người dùng thành công.');
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        if ($user->id === auth()->id()) {
            throw new AuthorizationException('Không thể xóa chính mình.');
        }

        $user->delete();

        Log::info('user.deleted', [
            'by' => auth()->id(),
            'target' => $user->id,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Đã xóa người dùng.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        Gate::authorize('toggleStatus', $user);

        $nextStatus = $user->status === 'active' ? 'blocked' : 'active';
        $user->status = $nextStatus;
        $user->is_active = $nextStatus === 'active';
        $user->save();

        // Nếu bị khóa: đá hết phiên đăng nhập của user đó
        if ($nextStatus === 'blocked') {
            try {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            } catch (\Throwable $e) {
                Log::warning('clear_sessions_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
            if (Auth::id() === $user->id) {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }
        }

        Log::info('user.toggle_status', [
            'by' => auth()->id(),
            'target' => $user->id,
            'status' => $nextStatus,
        ]);

        return back()->with('success', 'Đã cập nhật trạng thái tài khoản.');
    }
}
