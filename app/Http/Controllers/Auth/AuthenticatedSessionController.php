<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $role = $request->user()?->role;
        $home = $this->homeForRole($role);

        if ($role && $role !== 'customer') {
            return redirect($home)->with('success', 'Đăng nhập thành công.');
        }

        return redirect()->intended($home)->with('success', 'Đăng nhập thành công.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Đã đăng xuất.');
    }

    private function homeForRole(?string $role): string
    {
        return match ($role) {
            'admin'   => route('admin.dashboard', absolute: false),
            'manager', 'staff' => route('admin.dashboard', absolute: false),
            'customer' => route('home', absolute: false),
            default   => route('home', absolute: false),
        };
    }
}
