<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless(
            AdminAccess::allows($request->user(), $permission),
            Response::HTTP_FORBIDDEN,
            'Bạn không có quyền thực hiện chức năng này.'
        );

        return $next($request);
    }
}
