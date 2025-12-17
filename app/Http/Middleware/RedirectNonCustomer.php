<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectNonCustomer
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $role = $user->role ?? null;

        return match ($role) {
            'customer' => $next($request),
            'admin'    => redirect()->route('admin.dashboard'),
            'manager'  => redirect()->route('manager.dashboard'),
            'staff'    => redirect()->route('staff.dashboard'),
            default    => $next($request),
        };
    }
}
