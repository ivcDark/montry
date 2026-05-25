<?php

namespace App\Modules\Admin\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        return $next($request);
    }
}
