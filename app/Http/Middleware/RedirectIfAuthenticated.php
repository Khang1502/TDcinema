<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  string|null  ...$guards
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                return redirect()->intended($this->homeForRole($user?->role));
            }
        }

        return $next($request);
    }

    private function homeForRole(?string $role): string
    {
        return match ($role) {
            'admin'   => route('admin.dashboard', absolute: false),
            'manager' => route('manager.dashboard', absolute: false),
            'staff'   => route('staff.dashboard', absolute: false),
            'customer' => route('dashboard', absolute: false),
            default   => route('dashboard', absolute: false),
        };
    }
}
