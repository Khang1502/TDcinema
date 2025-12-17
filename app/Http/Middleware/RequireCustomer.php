<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $role = $request->user()->role ?? null;
        return match ($role) {
            'customer' => $next($request),
            'admin'    => redirect()->route('admin.dashboard'),
            'manager'  => redirect()->route('manager.dashboard'),
            'staff'    => redirect()->route('staff.dashboard'),
            default    => redirect()->route('home'),
        };
    }
}
