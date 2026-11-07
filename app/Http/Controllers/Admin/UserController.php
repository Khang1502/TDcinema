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
        $validated = $request->validate([
            'q'=>['nullable','string','max:150'],'role'=>['nullable','in:admin,manager,staff,customer'],
            'status'=>['nullable','in:active,blocked'],'registered_from'=>['nullable','date_format:Y-m-d'],
            'registered_to'=>array_filter(['nullable','date_format:Y-m-d',$request->filled('registered_from') ? 'after_or_equal:registered_from' : null]),
            'sort'=>['nullable','in:created_desc,created_asc,role_asc,name'],'cinema_id'=>['nullable','integer','exists:cinemas,id'],
        ]);
        $request->merge($validated);
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
        if ($request->filled('cinema_id')) $query->where('cinema_id',$request->integer('cinema_id'));

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)->toDateString());
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', Carbon::parse($dateTo)->toDateString());
        }

        $query = match ($sort) {
            'created_asc' => $query->orderBy('created_at', 'asc'),
            'role_asc' => $query->orderBy('role', 'asc')->orderBy('name', 'asc'),
            'name' => $query->orderBy('name'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $users = $query->orderByDesc('id')->paginate(10)->withQueryString();

        $filters = [
            'q' => $search,
            'role' => $role,
            'status' => $status,
            'registered_from' => $dateFrom,
            'registered_to' => $dateTo,
            'sort' => $sort,
            'cinema_id'=>$request->query('cinema_id'),
        ];

        $totalUsers = User::count();
        $filteredCount = $users->total();
        $roleCounts = User::selectRaw('role, COUNT(*) as aggregate')->groupBy('role')->pluck('aggregate','role');
        $stateCounts = User::selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate','status');
        $cinemas = Cinema::orderBy('name')->get(['id','name']);
        return view('admin.users.index', compact('users','filters','totalUsers','filteredCount','roleCounts','stateCounts','cinemas'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);
        $cinemas = Cinema::orderBy('name')->get(['id','name']);
        $adminOnly = request()->boolean('admin_only');
        return view('admin.users.create', compact('cinemas', 'adminOnly'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birthday' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'manager', 'staff', 'customer'])],
            'status' => ['required', Rule::in(['active', 'blocked'])],
            'cinema_id' => ['nullable', 'exists:cinemas,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'return_to' => ['nullable', Rule::in(['permissions', 'users'])],
        ]);

        $returnTo = $data['return_to'] ?? 'users';
        unset($data['return_to']);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $data['status'] === 'active';
        if (!in_array($data['role'], ['staff', 'manager'], true)) {
            $data['cinema_id'] = null;
        }

        User::create($data);

        return redirect()->route($returnTo === 'permissions' ? 'admin.permissions.index' : 'admin.users.index')
            ->with('success', 'Tạo tài khoản thành công.');
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
            'birthday' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'manager', 'staff', 'customer'])],
            'status' => ['required', Rule::in(['active', 'blocked'])],
            'cinema_id' => ['nullable', 'exists:cinemas,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'return_to' => ['nullable', Rule::in(['permissions', 'users'])],
        ]);

        $returnTo = $data['return_to'] ?? 'users';
        unset($data['return_to']);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['is_active'] = $data['status'] === 'active';

        if ($user->id === Auth::id()) {
            unset($data['role'], $data['status'], $data['is_active']);
        }
        if ($user->role === 'admin' && ($data['role'] ?? 'admin') !== 'admin'
            && User::where('role', 'admin')->count() <= 1) {
            return back()->withInput()->withErrors(['role' => 'Hệ thống phải luôn còn ít nhất một Super admin.']);
        }
        if ($user->role === 'admin' && ($data['status'] ?? $user->status) === 'blocked'
            && User::where('role', 'admin')->where('status', 'active')->count() <= 1) {
            return back()->withInput()->withErrors(['status' => 'Không thể khóa Super admin đang hoạt động cuối cùng.']);
        }
        if (!in_array($data['role'] ?? $user->role, ['staff', 'manager'], true)) {
            $data['cinema_id'] = null;
        }

        $resetEmailVerification = isset($data['email']) && $data['email'] !== $user->email;

        $user->fill($data);
        if ($resetEmailVerification) {
            $user->email_verified_at = null;
        }
        $user->save();

        Log::info('user.updated', [
            'by' => Auth::id(),
            'target' => $user->id,
        ]);

        return redirect()->route($returnTo === 'permissions' ? 'admin.permissions.index' : 'admin.users.index')
            ->with('success', 'Cập nhật tài khoản thành công.');
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        if ($user->id === Auth::id()) {
            throw new AuthorizationException('Không thể xóa chính mình.');
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Không thể xóa Super admin cuối cùng.');
        }

        $user->delete();

        Log::info('user.deleted', [
            'by' => Auth::id(),
            'target' => $user->id,
        ]);

        $returnRoute = $request->input('return_to') === 'permissions'
            ? 'admin.permissions.index'
            : 'admin.users.index';

        return redirect()->route($returnRoute)->with('success', 'Đã xóa tài khoản. Dữ liệu giao dịch cũ vẫn được giữ lại.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        Gate::authorize('toggleStatus', $user);

        $nextStatus = $user->status === 'active' ? 'blocked' : 'active';
        if ($user->role === 'admin' && $nextStatus === 'blocked'
            && User::where('role', 'admin')->where('status', 'active')->count() <= 1) {
            return back()->withErrors(['user' => 'Không thể khóa Super admin đang hoạt động cuối cùng.']);
        }
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
            'by' => Auth::id(),
            'target' => $user->id,
            'status' => $nextStatus,
        ]);

        return back()->with('success', 'Đã cập nhật trạng thái tài khoản.');
    }
}
